import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

// AdminLayout/ExportDialog pull in a large dependency tree (sidebar with static asset
// imports, modals, axios) that isn't relevant to this smoke test and trips up Vite's
// module graph under vitest. vue-chartjs components require a real <canvas> context
// that jsdom doesn't provide. Mock these modules so their real files are never loaded.
vi.mock('@/Layouts/AdminLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/Components/Admin/ExportDialog.vue', () => ({
    default: { template: '<div></div>' },
}));
vi.mock('vue-chartjs', () => ({
    Line: { template: '<div class="chart-stub-line"></div>' },
    Doughnut: { template: '<div class="chart-stub-doughnut"></div>' },
}));
// <Head> needs an Inertia head manager normally provided by the app plugin; outside
// that context it throws. Link/router don't need a real Inertia app for this smoke test.
vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div style="display: none;"><slot /></div>' },
    Link: { template: '<a><slot /></a>' },
    router: { get: vi.fn(), post: vi.fn() },
}));

const { default: Dashboard } = await import('../Dashboard.vue');

const globalMountOptions = {
    config: {
        globalProperties: {
            route: (name) => `#${name ?? ''}`,
        },
    },
};

const baseMetrics = {
    period: 'month',
    compare_period: 'previous_period',
    period_start: '2026-06-01',
    period_end: '2026-06-11',
    last_updated: '2026-06-11T10:00:00Z',
    sales_chart: { labels: [], datasets: [{ data: [] }] },
    payment_summary: [],
    oldest_order: null,
    vouchers_alert: [],
    need_fulfillment: 0,
    awaiting_approval: 0,
    awaiting_inspection: 0,
    low_stock_count: 0,
    sla_breaches: {
        total_breaches: 0,
        order_paid_overdue: 0,
        order_processing_overdue: 0,
        return_submitted_overdue: 0,
        return_received_overdue: 0,
        return_refund_overdue: 0,
    },
    priority_actions: [],
    today_focus: { items: [], is_all_clear: true },
    pending_shipment: { total_value: 0, order_count: 0 },
    revenue_at_risk: {
        pending_payment_count: 0,
        pending_payment_amount: 0,
        pending_refund_amount: 0,
        reserved_voucher_amount: 0,
    },
    comparison_label: 'vs bulan lalu',
    gross_sales: 0,
    gross_sales_trend: { type: 'none', value: null, display: '—' },
    total_orders: 0,
    total_orders_trend: { type: 'none', value: null, display: '—' },
    total_refund: 0,
    total_refund_trend: { type: 'none', value: null, display: '—' },
    checkout_created: 0,
    checkout_created_trend: { type: 'none', value: null, display: '—' },
    checkout_to_paid_rate: 0,
    checkout_to_paid_rate_trend: { type: 'delta_points', value: 0, display: '+0 pp' },
    order_return_rate: 0,
    order_return_rate_trend: { type: 'delta_points', value: 0, display: '+0 pp' },
    top_products: [],
    logistics_performance: {
        avg_shipping_cost: 0,
        avg_shipping_cost_trend: { type: 'none', value: null, display: '—' },
        avg_delivery_days: 0,
        avg_delivery_days_trend: { type: 'none', value: null, display: '—' },
    },
};

const mountDashboard = (metricsOverrides = {}, propsOverrides = {}) => {
    return mount(Dashboard, {
        props: {
            metrics: { ...baseMetrics, ...metricsOverrides },
            lowStockProducts: [],
            filters: {},
            isOwner: true,
            ...propsOverrides,
        },
        global: globalMountOptions,
    });
};

describe('Admin Dashboard.vue smoke test', () => {
    it('renders the Today Focus banner when there are items needing attention', () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
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
                ],
                is_all_clear: false,
            },
        });

        expect(wrapper.find('[data-today-focus="active"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Prioritas Hari Ini');
        expect(wrapper.text()).toContain('2 Order terlambat diproses');
    });

    it('renders the Today Focus all-clear state when nothing needs attention', () => {
        const wrapper = mountDashboard();

        expect(wrapper.find('[data-today-focus="all-clear"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Semua Terkendali');
    });

    it('renders the priority queue KPI cards with counts', () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
            need_fulfillment: 5,
            awaiting_approval: 2,
            awaiting_inspection: 1,
            low_stock_count: 3,
        });

        expect(wrapper.text()).toContain('Siap Kirim');
        expect(wrapper.text()).toContain('Retur Baru');
        expect(wrapper.text()).toContain('Inspeksi');
        expect(wrapper.text()).toContain('Stok Kritis');
    });

    it('renders priority feed items when priority_actions is non-empty', () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
            priority_actions: [
                {
                    type: 'return_submitted',
                    id: 42,
                    priority: 'critical',
                    title: 'Retur #42 menunggu review',
                    message: 'Pelanggan mengajukan retur',
                    timestamp: '2026-06-11T08:00:00Z',
                    action_url: '/admin/returns/42',
                },
            ],
        });

        expect(wrapper.text()).toContain('Feed Aktivitas Prioritas');
        expect(wrapper.text()).toContain('Retur #42 menunggu review');
        expect(wrapper.text()).toContain('Tindak Lanjuti');
        expect(wrapper.text()).not.toContain('Tidak Ada Aktivitas');
    });

    it('renders the empty-state when priority_actions is empty', () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
        });

        expect(wrapper.text()).toContain('Feed Aktivitas Prioritas');
        expect(wrapper.text()).toContain('Tidak Ada Aktivitas');
        expect(wrapper.text()).toContain('Tidak ada item di antrean prioritas saat ini.');
    });

    it('drills down from Today Focus into a filtered Priority Feed subset (§2.7-C)', async () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
            priority_actions: [
                {
                    type: 'order_paid',
                    id: 1,
                    severity: 'critical',
                    priority: 'critical',
                    title: 'Order #1 terlambat diproses',
                    message: 'msg',
                    timestamp: null,
                    action_url: '#',
                },
                {
                    type: 'return_submitted',
                    id: 2,
                    severity: 'info',
                    priority: 'normal',
                    title: 'Retur #2 baru diajukan',
                    message: 'msg',
                    timestamp: null,
                    action_url: '#',
                },
            ],
            today_focus: {
                items: [
                    {
                        key: 'order_paid_overdue',
                        severity: 'critical',
                        count: 1,
                        label: '1 Order terlambat diproses',
                        filter_types: ['order_paid'],
                        filter_severity: 'critical',
                    },
                ],
                is_all_clear: false,
            },
        });

        expect(wrapper.text()).toContain('Order #1 terlambat diproses');
        expect(wrapper.text()).toContain('Retur #2 baru diajukan');

        await wrapper.find('[data-today-focus="active"] button').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Order #1 terlambat diproses');
        expect(wrapper.text()).not.toContain('Retur #2 baru diajukan');
        expect(wrapper.text()).toContain('Tampilkan Semua');
    });

    it('renders the no-activity empty-state and hides financial sections when owner has zero activity', () => {
        const wrapper = mountDashboard({
            checkout_created: 0,
            total_orders: 0,
        }, { isOwner: true });

        expect(wrapper.text()).toContain('Belum Ada Transaksi di Periode Ini');
        expect(wrapper.text()).not.toContain('Gross Sales');
        expect(wrapper.text()).not.toContain('Trend Penjualan');
    });

    it('separates the operational section (real-time, no filter) from the analytics filter bar (§3/B10)', () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
        });

        const operationalLabel = wrapper.find('[data-operational-label]');
        expect(operationalLabel.exists()).toBe(true);
        expect(operationalLabel.text()).toContain('Real-time, tidak terpengaruh filter');

        // The operational label must appear before the period filter pills, and the
        // header (Live status/Refresh) must not contain any period/compare controls.
        const html = wrapper.html();
        const labelIndex = html.indexOf('Real-time, tidak terpengaruh filter');
        const filterPillIndex = html.indexOf('Bulan Ini');
        expect(labelIndex).toBeGreaterThan(-1);
        expect(filterPillIndex).toBeGreaterThan(-1);
        expect(labelIndex).toBeLessThan(filterPillIndex);

        expect(wrapper.text()).toContain('Dasbor Utama');
        expect(wrapper.text()).toContain('Muat Ulang');
        expect(wrapper.text()).toContain('Bandingkan:');
    });
});

// FASE 4.4 / B11-lite (§4.4): tab Operasional/Finansial — focus toggle, client-side
// only. The full payload is still rendered (toggled via v-show, not v-if/unmount).
describe('Admin Dashboard.vue Operasional/Finansial tabs (§4.4 / B11-lite)', () => {
    it('defaults to the Operasional tab with the Finansial panel hidden but still rendered', () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
        });

        const operasionalTab = wrapper.find('[data-dashboard-tab="operasional"]');
        const finansialTab = wrapper.find('[data-dashboard-tab="finansial"]');
        expect(operasionalTab.exists()).toBe(true);
        expect(finansialTab.exists()).toBe(true);

        const operasionalPanel = wrapper.find('[data-tab-panel="operasional"]');
        const finansialPanel = wrapper.find('[data-tab-panel="finansial"]');

        expect(operasionalPanel.attributes('style') || '').not.toContain('display: none');
        expect(finansialPanel.attributes('style') || '').toContain('display: none');

        // The financial panel's content is still in the DOM (payload not unmounted).
        expect(finansialPanel.text()).toContain('Bandingkan:');
    });

    it('switches to the Finansial panel and hides the Operasional panel on tab click', async () => {
        const wrapper = mountDashboard({
            checkout_created: 1,
            total_orders: 1,
        });

        await wrapper.find('[data-dashboard-tab="finansial"]').trigger('click');

        const operasionalPanel = wrapper.find('[data-tab-panel="operasional"]');
        const finansialPanel = wrapper.find('[data-tab-panel="finansial"]');

        expect(finansialPanel.attributes('style') || '').not.toContain('display: none');
        expect(operasionalPanel.attributes('style') || '').toContain('display: none');

        // The operational panel's content is still in the DOM (payload not unmounted).
        expect(operasionalPanel.text()).toContain('Real-time, tidak terpengaruh filter');
    });
});
