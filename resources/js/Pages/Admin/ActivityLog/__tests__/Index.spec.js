import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

// AdminLayout pulls in a large dependency tree (sidebar with static asset imports,
// logout button, etc.) that isn't relevant to this smoke test. Render both the
// named #header slot and the default slot so heading text is visible to assertions.
vi.mock('@/Layouts/AdminLayout.vue', () => ({
    default: { template: '<div><slot name="header" /><slot /></div>' },
}));
vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div style="display: none;"><slot /></div>' },
    Link: { template: '<a><slot /></a>' },
    router: { get: vi.fn(), post: vi.fn() },
}));

// `route()` is normally a Ziggy global (window.route). Index.vue calls it directly
// from <script setup> (subjectLink), which resolves against the global scope, not
// component globalProperties — stub both.
const routeFn = (name, params) => `#${name ?? ''}${params !== undefined ? '/' + params : ''}`;
vi.stubGlobal('route', routeFn);

const { default: Index } = await import('../Index.vue');

const globalMountOptions = {
    config: {
        globalProperties: {
            route: routeFn,
        },
    },
};

const baseLogs = {
    data: [
        {
            id: 1,
            type: 'order.paid',
            actor_type: 'admin',
            actor_id: 5,
            actor_name: 'Budi Admin',
            subject_type: 'order',
            subject_id: 101,
            subject_label: 'ORD-101',
            description: 'Order ORD-101 berubah dari pending ke paid',
            metadata: { amount: 150000 },
            created_at: '2026-06-12T10:00:00Z',
        },
        {
            id: 2,
            type: 'return.submitted',
            actor_type: 'system',
            actor_id: null,
            actor_name: 'System',
            subject_type: 'return_request',
            subject_id: 7,
            subject_label: 'RET-007',
            description: 'Retur RET-007 berubah dari - ke submitted',
            metadata: [],
            created_at: '2026-06-12T11:30:00Z',
        },
    ],
    links: [],
};

const mountIndex = (propsOverrides = {}) => {
    return mount(Index, {
        props: {
            logs: baseLogs,
            filters: {},
            ...propsOverrides,
        },
        global: globalMountOptions,
    });
};

describe('Admin ActivityLog Index.vue smoke test', () => {
    it('renders the page heading', () => {
        const wrapper = mountIndex();
        expect(wrapper.text()).toContain('Log Aktivitas');
    });

    it('renders the filter bar', () => {
        const wrapper = mountIndex();
        expect(wrapper.find('input[type="text"]').exists()).toBe(true);
        expect(wrapper.findAll('select').length).toBeGreaterThanOrEqual(1);
    });

    it('renders a log item description', () => {
        const wrapper = mountIndex();
        expect(wrapper.text()).toContain('Order ORD-101 berubah dari pending ke paid');
    });

    it('renders deep-links to the order and return subjects', () => {
        const wrapper = mountIndex();
        const links = wrapper.findAll('[data-testid="activity-subject-link"]');

        expect(links.length).toBe(2);
        expect(links[0].attributes('href')).toContain('orders');
        expect(links[1].attributes('href')).toContain('returns');
    });

    it('renders an empty state when there are no logs', () => {
        const wrapper = mountIndex({ logs: { data: [], links: [] } });
        expect(wrapper.text()).toContain('Belum ada aktivitas tercatat');
    });
});
