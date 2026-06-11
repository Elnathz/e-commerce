<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
});

const criticalVouchers = computed(() => (props.metrics.vouchers_alert || []).filter(v => v.severity === 'critical'));

// Count-based color tiers for priority queue cards: >10 = critical (red), 5-10 = warning (amber), 1-4 = active (indigo), 0 = clear (green)
const priorityTier = (count) => {
    if (count > 10) {
        return {
            ring: 'ring-2 ring-rose-400 ring-offset-2 ring-offset-slate-50 border-transparent',
            blob: 'bg-rose-50 group-hover:bg-rose-100',
            icon: 'bg-rose-500 text-white animate-pulse',
            number: 'text-rose-600',
            label: 'text-rose-600 bg-rose-50',
        };
    }
    if (count >= 5) {
        return {
            ring: '',
            blob: 'bg-amber-50 group-hover:bg-amber-100',
            icon: 'bg-amber-100 text-amber-600',
            number: 'text-amber-600',
            label: 'text-amber-600 bg-amber-50',
        };
    }
    if (count >= 1) {
        return {
            ring: '',
            blob: 'bg-indigo-50 group-hover:bg-indigo-100',
            icon: 'bg-indigo-100 text-indigo-600',
            number: 'text-slate-900',
            label: 'text-indigo-600 bg-indigo-50',
        };
    }
    return {
        ring: '',
        blob: 'bg-emerald-50 group-hover:bg-emerald-100',
        icon: 'bg-emerald-100 text-emerald-600',
        number: 'text-slate-900',
        label: 'text-emerald-600 bg-emerald-50',
    };
};

// Stock alert severity: critical stock is P0 regardless of volume, so any non-zero count is at least a warning (no neutral/indigo tier).
const stockAlertTier = (count) => {
    if (count > 4) {
        return {
            ring: 'ring-2 ring-rose-400 ring-offset-2 ring-offset-slate-50 border-transparent',
            blob: 'bg-rose-50 group-hover:bg-rose-100',
            icon: 'bg-rose-500 text-white animate-pulse',
            number: 'text-rose-600',
            label: 'text-rose-600 bg-rose-50',
        };
    }
    if (count >= 1) {
        return {
            ring: '',
            blob: 'bg-amber-50 group-hover:bg-amber-100',
            icon: 'bg-amber-100 text-amber-600',
            number: 'text-amber-600',
            label: 'text-amber-600 bg-amber-50',
        };
    }
    return {
        ring: '',
        blob: 'bg-emerald-50 group-hover:bg-emerald-100',
        icon: 'bg-emerald-100 text-emerald-600',
        number: 'text-slate-900',
        label: 'text-emerald-600 bg-emerald-50',
    };
};
</script>

<template>
    <!-- ========================================== -->
    <!-- OPERASIONAL (PRIORITAS ANTRIAN) -->
    <!-- ========================================== -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        <!-- Need Fulfillment -->
        <Link :href="route('admin.orders.index', { status: 'paid' })" class="group bg-white/80 backdrop-blur-lg border border-slate-200 rounded-2xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 relative overflow-hidden" :class="priorityTier(metrics.need_fulfillment || 0).ring">
            <div class="absolute -right-4 -top-4 w-24 h-24 rounded-full blur-2xl transition-colors" :class="priorityTier(metrics.need_fulfillment || 0).blob"></div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shadow-sm" :class="priorityTier(metrics.need_fulfillment || 0).icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                    </div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Siap Kirim</h3>
                </div>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-black tracking-tight" :class="priorityTier(metrics.need_fulfillment || 0).number">{{ metrics.need_fulfillment || 0 }}</span>
                    <span class="text-xs font-semibold px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 transition-opacity" :class="priorityTier(metrics.need_fulfillment || 0).label">Proses →</span>
                </div>
            </div>
        </Link>

        <!-- Retur Baru -->
        <Link :href="route('admin.returns.index', { status: 'submitted' })" class="group bg-white/80 backdrop-blur-lg border border-slate-200 rounded-2xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 relative overflow-hidden" :class="priorityTier(metrics.awaiting_approval || 0).ring">
            <div class="absolute -right-4 -top-4 w-24 h-24 rounded-full blur-2xl transition-colors" :class="priorityTier(metrics.awaiting_approval || 0).blob"></div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shadow-sm" :class="priorityTier(metrics.awaiting_approval || 0).icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    </div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Retur Baru</h3>
                </div>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-black tracking-tight" :class="priorityTier(metrics.awaiting_approval || 0).number">{{ metrics.awaiting_approval || 0 }}</span>
                    <span class="text-xs font-semibold px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 transition-opacity" :class="priorityTier(metrics.awaiting_approval || 0).label">Review →</span>
                </div>
            </div>
        </Link>

        <!-- Inspeksi -->
        <Link :href="route('admin.returns.index', { status: 'received' })" class="group bg-white/80 backdrop-blur-lg border border-slate-200 rounded-2xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 relative overflow-hidden" :class="priorityTier(metrics.awaiting_inspection || 0).ring">
            <div class="absolute -right-4 -top-4 w-24 h-24 rounded-full blur-2xl transition-colors" :class="priorityTier(metrics.awaiting_inspection || 0).blob"></div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shadow-sm" :class="priorityTier(metrics.awaiting_inspection || 0).icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    </div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Inspeksi</h3>
                </div>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-black tracking-tight" :class="priorityTier(metrics.awaiting_inspection || 0).number">{{ metrics.awaiting_inspection || 0 }}</span>
                    <span class="text-xs font-semibold px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 transition-opacity" :class="priorityTier(metrics.awaiting_inspection || 0).label">Periksa →</span>
                </div>
            </div>
        </Link>

        <!-- Stok Kritis -->
        <Link :href="route('admin.products.index', { filter: 'low_stock' })" class="group bg-white/80 backdrop-blur-lg border border-slate-200 rounded-2xl p-5 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 relative overflow-hidden" :class="stockAlertTier(metrics.low_stock_count || 0).ring">
            <div class="absolute -right-4 -top-4 w-24 h-24 rounded-full blur-2xl transition-colors" :class="stockAlertTier(metrics.low_stock_count || 0).blob"></div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shadow-sm" :class="stockAlertTier(metrics.low_stock_count || 0).icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" /></svg>
                    </div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Stok Kritis</h3>
                </div>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-black tracking-tight" :class="stockAlertTier(metrics.low_stock_count || 0).number">{{ metrics.low_stock_count || 0 }}</span>
                    <span class="text-xs font-semibold px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 transition-opacity" :class="stockAlertTier(metrics.low_stock_count || 0).label">Lihat Produk →</span>
                </div>
            </div>
        </Link>
    </div>

    <!-- Critical Voucher Summary Chip (Zona A) -->
    <div v-if="criticalVouchers.length > 0" class="bg-rose-50 border border-rose-200 rounded-2xl px-5 py-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="flex items-center gap-3">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse shrink-0"></span>
            <span class="text-sm font-bold text-rose-800">{{ criticalVouchers.length }} promo kuotanya hampir habis (≤5 tersisa)</span>
        </div>
        <Link :href="route('admin.promotions.index')" class="text-xs font-bold bg-white text-rose-700 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md transition-all">Kelola Promo →</Link>
    </div>
</template>
