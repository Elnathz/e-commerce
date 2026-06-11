import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
}));

const { default: TodayFocus } = await import('../TodayFocus.vue');

const globalMountOptions = {
    config: {
        globalProperties: {
            route: (name) => `#${name ?? ''}`,
        },
    },
};

describe('TodayFocus.vue (§2.7 / §2.7-B / §2.7-C)', () => {
    it('renders the active state with severity emoji, labels and drill-down controls', async () => {
        const wrapper = mount(TodayFocus, {
            props: {
                metrics: {
                    today_focus: {
                        items: [
                            {
                                key: 'order_paid_overdue',
                                severity: 'critical',
                                count: 2,
                                label: '2 Order terlambat diproses',
                                filter_types: ['order_paid'],
                                filter_severity: 'critical',
                            },
                            {
                                key: 'low_stock',
                                severity: 'warning',
                                count: 3,
                                label: '3 Produk stok kritis',
                                filter_types: null,
                                filter_severity: null,
                            },
                        ],
                        is_all_clear: false,
                    },
                },
            },
            global: globalMountOptions,
        });

        expect(wrapper.find('[data-today-focus="active"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Prioritas Hari Ini');
        expect(wrapper.text()).toContain('2 Order terlambat diproses');
        expect(wrapper.text()).toContain('3 Produk stok kritis');

        // critical -> 🔴, warning -> 🟡
        const severityIcons = wrapper.findAll('[aria-label]');
        expect(severityIcons[0].attributes('aria-label')).toBe('critical');
        expect(severityIcons[0].text()).toBe('🔴');
        expect(severityIcons[1].attributes('aria-label')).toBe('warning');
        expect(severityIcons[1].text()).toBe('🟡');

        // Item with filter_types -> "Tangani Sekarang" button emitting drill-down
        const button = wrapper.find('button');
        expect(button.text()).toContain('Tangani Sekarang');
        await button.trigger('click');
        expect(wrapper.emitted('drill-down')).toBeTruthy();
        expect(wrapper.emitted('drill-down')[0][0]).toMatchObject({ key: 'order_paid_overdue' });

        // Stock item (no filter_types) -> direct Link to existing products page
        const link = wrapper.find('a');
        expect(link.text()).toContain('Lihat Produk');
    });

    it('renders the §2.7-B all-clear success state with the exact required text', () => {
        const wrapper = mount(TodayFocus, {
            props: {
                metrics: {
                    today_focus: { items: [], is_all_clear: true },
                },
            },
            global: globalMountOptions,
        });

        expect(wrapper.find('[data-today-focus="all-clear"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Semua Terkendali');
        expect(wrapper.text()).toContain('Tidak ada order terlambat · Tidak ada retur lewat SLA · Semua stok aman');
    });
});
