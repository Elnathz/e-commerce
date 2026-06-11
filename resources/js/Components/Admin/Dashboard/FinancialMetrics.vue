<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
    hasNoActivityInPeriod: {
        type: Boolean,
        default: false,
    },
    section: {
        type: String,
        default: 'risk', // 'risk' (Pending Shipment + Revenue At Risk) | 'kpi' (KPI cards + secondary metrics)
    },
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

// §1a / #45: trend sekarang berupa objek terstruktur { type, value, display }
// dari AnalyticsDashboardService::calculateTrend(). `invert` dipakai untuk metrik
// di mana turun = bagus (refund, return rate) — hanya memengaruhi warna, bukan ikon.
const getTrendColor = (trend, invert = false) => {
    if (!trend || trend.type === 'none') return 'text-slate-500 bg-slate-50/80 border-slate-100';

    let value = trend.type === 'new' ? 1 : (trend.value ?? 0);
    if (invert) value = -value;

    if (value > 0) return 'text-emerald-600 bg-emerald-50/80 border-emerald-100';
    if (value < 0) return 'text-rose-600 bg-rose-50/80 border-rose-100';
    return 'text-slate-500 bg-slate-50/80 border-slate-100';
};

const getTrendIcon = (trend) => {
    if (!trend || trend.type === 'none') return '→';
    if (trend.type === 'new') return '↗';
    if (trend.value > 0) return '↗';
    if (trend.value < 0) return '↘';
    return '→';
};

const getTrendDisplay = (trend) => trend?.display ?? '—';
</script>

<template>
    <template v-if="section === 'risk'">
        <!-- Pending Shipment -->
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-2xl p-6 shadow-xl shadow-indigo-600/20 text-white relative overflow-hidden hover:-translate-y-1 transition-transform duration-300">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
            <div class="relative z-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center shadow-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-indigo-200 uppercase tracking-widest mb-1">Total Pending Shipment</div>
                        <div class="text-2xl font-black tracking-tight">{{ formatRupiah(metrics.pending_shipment?.total_value || 0) }}</div>
                        <div class="text-xs text-indigo-100 font-medium mt-1">{{ metrics.pending_shipment?.order_count || 0 }} pesanan menunggu dikirim</div>
                    </div>
                </div>
                <Link :href="route('admin.orders.index', { status: 'processing' })" class="w-full sm:w-auto text-xs font-bold text-indigo-900 bg-white px-4 py-2.5 rounded-xl hover:bg-indigo-50 transition-colors shadow-sm text-center">Tinjau Logistik</Link>
            </div>
        </div>

        <!-- Revenue At Risk -->
        <div class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200">
            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-widest mb-5 flex items-center gap-2">
                <span class="w-2 h-6 bg-amber-500 rounded-full"></span>
                Risiko Pendapatan Tertahan
            </h3>
            <div class="space-y-4">
                <!-- Pending Payment -->
                <Link :href="route('admin.orders.index', { status: 'pending' })" class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-0.5">Pending Payment</div>
                            <div class="text-xs text-slate-400">{{ metrics.revenue_at_risk?.pending_payment_count || 0 }} checkout aktif</div>
                        </div>
                    </div>
                    <div class="text-lg font-black text-slate-800">{{ formatRupiah(metrics.revenue_at_risk?.pending_payment_amount || 0) }}</div>
                </Link>

                <!-- Pending Refund -->
                <Link :href="route('admin.returns.index', { status: 'approved' })" class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-0.5">Wajib Di-Refund</div>
                            <div class="text-xs text-slate-400">Retur telah disetujui</div>
                        </div>
                    </div>
                    <div class="text-lg font-black text-slate-800">{{ formatRupiah(metrics.revenue_at_risk?.pending_refund_amount || 0) }}</div>
                </Link>

                <!-- Reserved Voucher -->
                <Link :href="route('admin.promotions.index')" class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" /></svg>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-0.5">Alokasi Diskon</div>
                            <div class="text-xs text-slate-400">Terkunci di keranjang aktif</div>
                        </div>
                    </div>
                    <div class="text-lg font-black text-slate-800">{{ formatRupiah(metrics.revenue_at_risk?.reserved_voucher_amount || 0) }}</div>
                </Link>
            </div>
        </div>
    </template>

    <template v-else-if="section === 'kpi'">
        <!-- ========================================== -->
        <!-- KPI CARDS (FINANCIAL) -->
        <!-- ========================================== -->
        <div v-if="!hasNoActivityInPeriod" class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            <!-- Gross Sales -->
            <div class="bg-white/80 backdrop-blur-xl border border-slate-200 rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-emerald-50 rounded-full blur-3xl group-hover:bg-emerald-100 transition-colors"></div>
                <div class="relative z-10">
                    <div class="text-xs font-extrabold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-emerald-400"></div> Gross Sales</div>
                    <div class="text-4xl font-black text-slate-900 tracking-tighter mb-4">{{ formatRupiah(metrics.gross_sales || 0) }}</div>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border" :class="getTrendColor(metrics.gross_sales_trend)">
                            <span>{{ getTrendIcon(metrics.gross_sales_trend) }}</span>
                            <span>{{ getTrendDisplay(metrics.gross_sales_trend) }}</span>
                        </div>
                        <span class="text-xs font-semibold text-slate-400">{{ metrics.comparison_label || 'vs periode lalu' }}</span>
                        <Link :href="route('admin.orders.index', { status: 'completed', date_from: metrics.period_start, date_to: metrics.period_end })" class="ml-auto text-xs font-bold text-emerald-600 hover:text-emerald-700 hover:underline whitespace-nowrap">Lihat Pesanan →</Link>
                    </div>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-white/80 backdrop-blur-xl border border-slate-200 rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-blue-50 rounded-full blur-3xl group-hover:bg-blue-100 transition-colors"></div>
                <div class="relative z-10">
                    <div class="text-xs font-extrabold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-blue-400"></div> Order Masuk (Paid)</div>
                    <div class="text-4xl font-black text-slate-900 tracking-tighter mb-4">{{ metrics.total_orders || 0 }} <span class="text-lg text-slate-400 font-bold">pesanan</span></div>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border" :class="getTrendColor(metrics.total_orders_trend)">
                            <span>{{ getTrendIcon(metrics.total_orders_trend) }}</span>
                            <span>{{ getTrendDisplay(metrics.total_orders_trend) }}</span>
                        </div>
                        <span class="text-xs font-semibold text-slate-400">{{ metrics.comparison_label || 'vs periode lalu' }}</span>
                        <Link :href="route('admin.orders.index', { date_from: metrics.period_start, date_to: metrics.period_end })" class="ml-auto text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline whitespace-nowrap">Lihat Pesanan →</Link>
                    </div>
                </div>
            </div>

            <!-- Total Refund -->
            <div class="bg-white/80 backdrop-blur-xl border border-slate-200 rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-rose-50 rounded-full blur-3xl group-hover:bg-rose-100 transition-colors"></div>
                <div class="relative z-10">
                    <div class="text-xs font-extrabold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-rose-400"></div> Refund Keluar</div>
                    <div class="text-4xl font-black text-slate-900 tracking-tighter mb-4">{{ formatRupiah(metrics.total_refund || 0) }}</div>
                    <div class="flex items-center gap-3">
                        <!-- Note: Trend terbalik untuk refund. Turun = Bagus (Hijau) -->
                        <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border" :class="getTrendColor(metrics.total_refund_trend, true)">
                            <span>{{ getTrendIcon(metrics.total_refund_trend) }}</span>
                            <span>{{ getTrendDisplay(metrics.total_refund_trend) }}</span>
                        </div>
                        <span class="text-xs font-semibold text-slate-400">{{ metrics.comparison_label || 'vs periode lalu' }}</span>
                        <Link :href="route('admin.returns.index', { status: 'completed', date_from: metrics.period_start, date_to: metrics.period_end })" class="ml-auto text-xs font-bold text-rose-600 hover:text-rose-700 hover:underline whitespace-nowrap">Lihat Retur →</Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECONDARY METRICS (Conversion & Returns) -->
        <div v-if="!hasNoActivityInPeriod" class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            <div class="bg-slate-100/50 border border-slate-200/60 rounded-2xl p-5 flex justify-between items-center hover:bg-white transition-colors hover:shadow-sm">
                <div class="text-xs font-extrabold text-slate-500 uppercase tracking-widest">Checkout Attempts</div>
                <div class="flex items-center gap-3">
                    <div class="text-xl font-black text-slate-800">{{ metrics.checkout_created || 0 }}</div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded border" :class="getTrendColor(metrics.checkout_created_trend)">{{ getTrendIcon(metrics.checkout_created_trend) }} {{ getTrendDisplay(metrics.checkout_created_trend) }}</span>
                    <Link :href="route('admin.orders.index', { date_from: metrics.period_start, date_to: metrics.period_end })" class="text-xs font-bold text-slate-400 hover:text-slate-700 hover:underline" title="Lihat semua order pada periode ini">→</Link>
                </div>
            </div>
            <div class="bg-slate-100/50 border border-slate-200/60 rounded-2xl p-5 flex justify-between items-center hover:bg-white transition-colors hover:shadow-sm">
                <div class="text-xs font-extrabold text-slate-500 uppercase tracking-widest">Checkout-to-Paid</div>
                <div class="flex items-center gap-3">
                    <div class="text-xl font-black text-slate-800">{{ metrics.checkout_to_paid_rate || 0 }}%</div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded border" :class="getTrendColor(metrics.checkout_to_paid_rate_trend)">{{ getTrendIcon(metrics.checkout_to_paid_rate_trend) }} {{ getTrendDisplay(metrics.checkout_to_paid_rate_trend) }}</span>
                </div>
            </div>
            <div class="bg-slate-100/50 border border-slate-200/60 rounded-2xl p-5 flex justify-between items-center hover:bg-white transition-colors hover:shadow-sm">
                <div class="text-xs font-extrabold text-slate-500 uppercase tracking-widest">Return Rate</div>
                <div class="flex items-center gap-3">
                    <div class="text-xl font-black text-slate-800">{{ metrics.order_return_rate || 0 }}%</div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded border" :class="getTrendColor(metrics.order_return_rate_trend, true)">{{ getTrendIcon(metrics.order_return_rate_trend) }} {{ getTrendDisplay(metrics.order_return_rate_trend) }}</span>
                    <Link :href="route('admin.returns.index', { date_from: metrics.period_start, date_to: metrics.period_end })" class="text-xs font-bold text-slate-400 hover:text-slate-700 hover:underline" title="Lihat semua retur pada periode ini">→</Link>
                </div>
            </div>
        </div>
    </template>
</template>
