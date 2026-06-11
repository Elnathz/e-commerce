import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
}));

const { default: PriorityFeed } = await import('../PriorityFeed.vue');

const globalMountOptions = {
    config: {
        globalProperties: {
            route: (name) => `#${name ?? ''}`,
        },
    },
};

const mountFeed = (priorityActions, activeFilter = null) => mount(PriorityFeed, {
    props: {
        metrics: { priority_actions: priorityActions },
        activeFilter,
    },
    global: globalMountOptions,
});

describe('PriorityFeed.vue severity treatment (§2.6)', () => {
    it('renders 3 cross-category items with the correct treatment per severity', () => {
        const wrapper = mountFeed([
            { type: 'return_received', id: 1, severity: 'critical', priority: 'critical', title: 'Retur diterima terlambat diinspeksi', message: 'msg', timestamp: null, action_url: '#' },
            { type: 'order_paid', id: 2, severity: 'warning', priority: 'high', title: 'Order mendekati batas SLA', message: 'msg', timestamp: null, action_url: '#' },
            { type: 'return_submitted', id: 3, severity: 'info', priority: 'normal', title: 'Retur baru diajukan', message: 'msg', timestamp: null, action_url: '#' },
        ]);

        const items = wrapper.findAll('[data-severity-treatment]');
        expect(items).toHaveLength(3);

        // critical (breached) → banner-style emphasis
        expect(items[0].attributes('data-severity-treatment')).toBe('banner');
        expect(items[0].classes()).toContain('ring-rose-200');

        // warning (mendekati) → card highlighted (ring/border), normal size
        expect(items[1].attributes('data-severity-treatment')).toBe('highlight');
        expect(items[1].classes()).toContain('ring-amber-100');

        // info (informatif) → muted/normal, BUKAN collapsed (tetap tampil penuh)
        expect(items[2].attributes('data-severity-treatment')).toBe('muted');
        expect(items[2].text()).toContain('Retur baru diajukan');
    });

    it('does not collapse info-severity items', () => {
        const wrapper = mountFeed([
            { type: 'order_paid', id: 1, severity: 'info', priority: 'normal', title: 'Item Informatif', message: 'msg', timestamp: null, action_url: '#' },
        ]);

        expect(wrapper.text()).toContain('Item Informatif');
        expect(wrapper.text()).toContain('Tindak Lanjuti');
        expect(wrapper.find('[data-severity-treatment="muted"]').exists()).toBe(true);
    });

    it('defaults to muted treatment when severity is missing', () => {
        const wrapper = mountFeed([
            { type: 'order_paid', id: 1, priority: 'normal', title: 'Tanpa severity', message: 'msg', timestamp: null, action_url: '#' },
        ]);

        expect(wrapper.find('[data-severity-treatment="muted"]').exists()).toBe(true);
    });
});

describe('PriorityFeed.vue density (FASE 3.3 / C1)', () => {
    it('renders critical and warning items at comfortable density (full padding/icon)', () => {
        const wrapper = mountFeed([
            { type: 'return_received', id: 1, severity: 'critical', priority: 'critical', title: 'Item kritis', message: 'msg', timestamp: null, action_url: '#' },
            { type: 'order_paid', id: 2, severity: 'warning', priority: 'high', title: 'Item warning', message: 'msg', timestamp: null, action_url: '#' },
        ]);

        const items = wrapper.findAll('[data-density]');
        expect(items[0].attributes('data-density')).toBe('comfortable');
        expect(items[0].classes()).toContain('p-4');
        expect(items[1].attributes('data-density')).toBe('comfortable');
        expect(items[1].classes()).toContain('p-4');
    });

    it('renders info items at compact density without collapsing content', () => {
        const wrapper = mountFeed([
            { type: 'return_submitted', id: 1, severity: 'info', priority: 'normal', title: 'Item info', message: 'pesan info', timestamp: null, action_url: '#' },
        ]);

        const item = wrapper.find('[data-density]');
        expect(item.attributes('data-density')).toBe('compact');
        expect(item.classes()).toContain('p-2.5');

        // tetap dirender penuh, bukan collapsed
        expect(wrapper.text()).toContain('Item info');
        expect(wrapper.text()).toContain('pesan info');
        expect(wrapper.text()).toContain('Tindak Lanjuti');
    });
});

describe('PriorityFeed.vue drill-down filtering (§2.7-C)', () => {
    const actions = [
        { type: 'order_paid', id: 1, severity: 'critical', priority: 'critical', title: 'Order #1 terlambat diproses', message: 'msg', timestamp: null, action_url: '#' },
        { type: 'return_submitted', id: 2, severity: 'info', priority: 'normal', title: 'Retur #2 baru diajukan', message: 'msg', timestamp: null, action_url: '#' },
    ];

    it('renders all actions and hides the filter chip when no activeFilter is set', () => {
        const wrapper = mountFeed(actions, null);

        expect(wrapper.text()).toContain('Order #1 terlambat diproses');
        expect(wrapper.text()).toContain('Retur #2 baru diajukan');
        expect(wrapper.text()).not.toContain('Filter aktif');
    });

    it('shows only matching actions and a "Tampilkan Semua" chip when activeFilter matches', async () => {
        const wrapper = mountFeed(actions, { types: ['order_paid'], severity: 'critical' });

        expect(wrapper.text()).toContain('Order #1 terlambat diproses');
        expect(wrapper.text()).not.toContain('Retur #2 baru diajukan');
        expect(wrapper.text()).toContain('Filter aktif');
        expect(wrapper.text()).toContain('Tampilkan Semua');

        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('clear-filter')).toBeTruthy();
    });

    it('renders a "no matches" empty state with its own "Tampilkan Semua" button when activeFilter matches nothing', async () => {
        const wrapper = mountFeed(actions, { types: ['return_received'], severity: 'critical' });

        expect(wrapper.text()).toContain('Tidak Ada Item yang Cocok');
        expect(wrapper.text()).not.toContain('Order #1 terlambat diproses');
        expect(wrapper.text()).not.toContain('Retur #2 baru diajukan');

        const buttons = wrapper.findAll('button').filter((b) => b.text().includes('Tampilkan Semua'));
        expect(buttons.length).toBeGreaterThan(0);
        await buttons[0].trigger('click');
        expect(wrapper.emitted('clear-filter')).toBeTruthy();
    });
});
