import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}));

const { default: FinancialMetrics } = await import('../FinancialMetrics.vue');

const globalMountOptions = {
    config: {
        globalProperties: {
            route: (name) => `#${name ?? ''}`,
        },
    },
};

const mountKpi = (metricsOverrides = {}) => mount(FinancialMetrics, {
    props: {
        section: 'kpi',
        hasNoActivityInPeriod: false,
        metrics: {
            gross_sales: 0,
            total_orders: 0,
            total_refund: 0,
            checkout_created: 0,
            checkout_to_paid_rate: 0,
            order_return_rate: 0,
            comparison_label: 'vs 7 hari sebelumnya',
            period_start: '2026-06-01',
            period_end: '2026-06-11',
            ...metricsOverrides,
        },
    },
    global: globalMountOptions,
});

const mountRisk = (metricsOverrides = {}) => mount(FinancialMetrics, {
    props: {
        section: 'risk',
        metrics: {
            pending_shipment: { total_value: 0, order_count: 0 },
            revenue_at_risk: {
                pending_payment_count: 0,
                pending_payment_amount: 0,
                pending_refund_amount: 0,
                reserved_voucher_amount: 0,
            },
            ...metricsOverrides,
        },
    },
    global: globalMountOptions,
});

// §1a / tracker #45 (DESIGN-LOCKED): structured trend display.
describe('FinancialMetrics.vue C2 trend display (§1a / #45)', () => {
    it('renders "—" for type "none" (prev==0 && cur==0)', () => {
        const wrapper = mountKpi({
            gross_sales_trend: { type: 'none', value: null, display: '—' },
        });

        expect(wrapper.text()).toContain('—');
    });

    it('renders "Baru" for type "new" (prev==0 && cur>0)', () => {
        const wrapper = mountKpi({
            gross_sales_trend: { type: 'new', value: null, display: 'Baru' },
        });

        expect(wrapper.text()).toContain('Baru');
    });

    it('renders "pct%" for type "percent" within the 200% threshold', () => {
        const wrapper = mountKpi({
            total_orders_trend: { type: 'percent', value: 33, display: '+33%' },
        });

        expect(wrapper.text()).toContain('+33%');
    });

    it('renders a signed raw delta (formatted as currency) for type "delta" beyond 200%', () => {
        const wrapper = mountKpi({
            gross_sales_trend: { type: 'delta', value: 4450000, display: '+Rp 4.450.000' },
        });

        expect(wrapper.text()).toContain('+Rp 4.450.000');
    });

    it('renders "+N pp" for Bucket B rate trends (checkout_to_paid_rate, order_return_rate)', () => {
        const wrapper = mountKpi({
            checkout_to_paid_rate_trend: { type: 'delta_points', value: 8, display: '+8 pp' },
            order_return_rate_trend: { type: 'delta_points', value: -3, display: '-3 pp' },
        });

        expect(wrapper.text()).toContain('+8 pp');
        expect(wrapper.text()).toContain('-3 pp');
    });

    it('renders the KPI context comparison-period label next to the trend', () => {
        const wrapper = mountKpi({
            gross_sales_trend: { type: 'percent', value: 12, display: '+12%' },
            comparison_label: 'vs 7 hari sebelumnya',
        });

        expect(wrapper.text()).toContain('vs 7 hari sebelumnya');
    });

    it('colors a refund increase as bad (rose) since down = good for refund', () => {
        const wrapper = mountKpi({
            total_refund_trend: { type: 'percent', value: 12, display: '+12%' },
        });

        const badge = wrapper.findAll('.inline-flex').find((el) => el.text().includes('+12%'));
        expect(badge.classes()).toContain('text-rose-600');
    });

    it('colors a return-rate decrease as good (emerald) since down = good for return rate', () => {
        const wrapper = mountKpi({
            order_return_rate_trend: { type: 'delta_points', value: -3, display: '-3 pp' },
        });

        const badge = wrapper.findAll('span').find((el) => el.text().includes('-3 pp'));
        expect(badge.classes()).toContain('text-emerald-600');
    });
});

// FASE 4.3 / P1.1: KPI deep-links — "Actionable KPI Links (filter tanggal ke halaman terkait)".
describe('FinancialMetrics.vue KPI deep-links (§ FASE 4.3 / P1.1)', () => {
    it('links Gross Sales to completed orders scoped to the dashboard period', () => {
        const wrapper = mountKpi();

        const link = wrapper.findAll('a').find((a) => a.text().includes('Lihat Pesanan'));
        expect(link.attributes('href')).toBe('#admin.orders.index');
    });

    it('links Total Refund to completed returns', () => {
        const wrapper = mountKpi();

        const links = wrapper.findAll('a').filter((a) => a.text().includes('Lihat Retur'));
        expect(links.some((a) => a.attributes('href') === '#admin.returns.index')).toBe(true);
    });

    it('links the Return Rate secondary metric to the returns list', () => {
        const wrapper = mountKpi();

        const link = wrapper.find('a[title="Lihat semua retur pada periode ini"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toBe('#admin.returns.index');
    });

    it('links the Checkout Attempts secondary metric to the orders list', () => {
        const wrapper = mountKpi();

        const link = wrapper.find('a[title="Lihat semua order pada periode ini"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toBe('#admin.orders.index');
    });
});

describe('FinancialMetrics.vue Revenue at Risk deep-links (§ FASE 4.3 / P1.1)', () => {
    it('links Pending Payment to pending orders', () => {
        const wrapper = mountRisk();

        const link = wrapper.findAll('a').find((a) => a.text().includes('Pending Payment'));
        expect(link.attributes('href')).toBe('#admin.orders.index');
    });

    it('links Wajib Di-Refund (pending refund) to the returns list', () => {
        const wrapper = mountRisk();

        const link = wrapper.findAll('a').find((a) => a.text().includes('Wajib Di-Refund'));
        expect(link.attributes('href')).toBe('#admin.returns.index');
    });

    it('links Alokasi Diskon (reserved voucher) to the promotions list', () => {
        const wrapper = mountRisk();

        const link = wrapper.findAll('a').find((a) => a.text().includes('Alokasi Diskon'));
        expect(link.attributes('href')).toBe('#admin.promotions.index');
    });
});
