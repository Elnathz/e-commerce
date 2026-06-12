<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
    // §2.7-C drill-down: { types: string[]|null, severity: string|null } atau null (tanpa filter)
    activeFilter: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['clear-filter']);

const filteredActions = computed(() => {
    const actions = props.metrics.priority_actions ?? [];
    if (!props.activeFilter) return actions;

    return actions.filter((action) => {
        if (props.activeFilter.types && !props.activeFilter.types.includes(action.type)) return false;
        if (props.activeFilter.severity && action.severity !== props.activeFilter.severity) return false;
        return true;
    });
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const oldestOrder = computed(() => props.metrics.oldest_order);
const oldestOrderClass = computed(() => {
    if (!oldestOrder.value) return '';
    const hours = oldestOrder.value.waiting_hours;
    if (hours > 24) return 'from-rose-50 to-white border-rose-200';
    if (hours > 12) return 'from-amber-50 to-white border-amber-200';
    return 'from-sky-50 to-white border-sky-200';
});

const timeAgo = (date) => {
    if (!date) return '';
    const diff = Math.floor((new Date() - new Date(date)) / 1000);
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    return Math.floor(diff / 86400) + ' hari lalu';
};

// §2.6 KONTRAK HIERARKI VISUAL: backend `severity` (critical/warning/info) drives
// rendering directly — no time thresholds here. critical = banner-style emphasis,
// warning = highlighted card (ring/border), info = muted/normal (NOT collapsed).
//
// FASE 3.3 (C1 densitas): ranking sudah benar via §2.5/#49 (priority_score), feed
// sudah dibatasi 10 item (#49/#51). Sisa keluhan "feed endless" diatasi lewat
// densitas per-severity — item `info` (prioritas terendah) dirender lebih ringkas
// (padding/icon lebih kecil) agar lebih banyak item muat tanpa scroll berlebihan,
// SEMENTARA `critical`/`warning` tetap mendapat ruang penuh sesuai §2.6. `info`
// tetap dirender penuh (judul, pesan, tombol, waktu) — TIDAK collapsed.
const severityTreatment = (severity) => {
    switch (severity) {
        case 'critical':
            return {
                treatment: 'banner',
                density: 'comfortable',
                card: 'border-rose-200 bg-rose-50/60 ring-1 ring-rose-200 p-4 gap-4',
                icon: 'bg-rose-500 text-white border border-rose-300 w-12 h-12',
                iconSvg: 'w-6 h-6',
            };
        case 'warning':
            return {
                treatment: 'highlight',
                density: 'comfortable',
                card: 'border-amber-200 bg-amber-50/40 ring-1 ring-amber-100 p-4 gap-4',
                icon: 'bg-amber-100/80 text-amber-600 border border-amber-200 w-12 h-12',
                iconSvg: 'w-6 h-6',
            };
        default:
            return {
                treatment: 'muted',
                density: 'compact',
                card: 'border-transparent p-2.5 gap-3',
                icon: 'bg-slate-100 text-slate-500 border border-slate-200 w-9 h-9',
                iconSvg: 'w-4 h-4',
            };
    }
};
</script>

<template>
    <!-- Order Tertua Belum Diproses -->
    <div v-if="oldestOrder" class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-r shadow-lg border hover:-translate-y-1 transition-all duration-300" :class="oldestOrderClass">
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h4 class="font-extrabold text-slate-900 flex items-center gap-2 text-lg">
                    <div class="bg-white p-1.5 rounded-md shadow-sm" v-if="oldestOrder.waiting_hours > 24">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-rose-500"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    Order Tertua Belum Diproses
                </h4>
                <div class="mt-2 text-slate-700 font-medium flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="bg-white/60 px-2 py-0.5 rounded text-sm">{{ oldestOrder.order_number }}</span>
                    <span class="text-slate-300">|</span>
                    <span class="text-sm">{{ formatRupiah(oldestOrder.total_amount) }}</span>
                    <span class="text-slate-300">|</span>
                    <span class="font-bold text-slate-900 bg-white/40 px-2 py-0.5 rounded text-sm">{{ oldestOrder.waiting_hours }} jam lalu</span>
                </div>
                <p class="text-xs text-slate-500 mt-2 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                    {{ oldestOrder.customer_name }}
                </p>
                <p v-if="oldestOrder.total_waiting > 1" class="text-xs text-rose-600 mt-1.5 font-semibold bg-rose-100/50 inline-block px-2 py-0.5 rounded">
                    + {{ oldestOrder.total_waiting - 1 }} order lain menunggu antrean
                </p>
            </div>
            <Link :href="route('admin.orders.show', oldestOrder.id)" class="w-full md:w-auto bg-slate-900 text-white px-6 py-3 rounded-xl text-sm font-bold hover:bg-slate-800 transition-all hover:shadow-lg hover:shadow-slate-900/20 text-center whitespace-nowrap">
                Proses Order Ini →
            </Link>
        </div>
    </div>

    <!-- Needs Action Feed -->
    <section class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200 mt-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-widest flex items-center gap-2">
                <span class="w-2 h-6 bg-indigo-500 rounded-full"></span>
                Feed Aktivitas Prioritas
            </h3>
            <!-- §2.7-C: chip filter aktif dari drill-down Today Focus -->
            <button v-if="activeFilter" type="button" @click="emit('clear-filter')" class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5">
                Filter aktif
                <span class="text-slate-400">&times;</span>
                Tampilkan Semua
            </button>
        </div>

        <!-- Tidak ada item sama sekali — netral, BUKAN klaim "aman" (SSOT ada di TodayFocus §2.7-B) -->
        <div v-if="!metrics.priority_actions?.length" class="flex flex-col items-center justify-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
            <div class="mb-3 text-slate-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h4 class="font-bold text-slate-700">Tidak Ada Aktivitas</h4>
            <p class="text-sm text-slate-500 mt-1">Tidak ada item di antrean prioritas saat ini.</p>
        </div>

        <!-- Filter aktif tapi tidak ada item yang cocok -->
        <div v-else-if="!filteredActions.length" class="flex flex-col items-center justify-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
            <h4 class="font-bold text-slate-700">Tidak Ada Item yang Cocok</h4>
            <p class="text-sm text-slate-500 mt-1">Tidak ada item yang cocok dengan filter ini.</p>
            <button type="button" @click="emit('clear-filter')" class="mt-3 text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-lg transition-all">
                Tampilkan Semua
            </button>
        </div>

        <div v-else class="space-y-4">
            <div v-for="action in filteredActions" :key="action.type + action.id"
                :data-severity-treatment="severityTreatment(action.severity).treatment"
                :data-density="severityTreatment(action.severity).density"
                class="group flex flex-col sm:flex-row items-start sm:items-center rounded-xl border hover:border-slate-100 hover:bg-slate-50/80 transition-all duration-200"
                :class="severityTreatment(action.severity).card">
                <div class="rounded-xl flex items-center justify-center flex-shrink-0 shadow-inner"
                    :class="severityTreatment(action.severity).icon">
                    <svg v-if="action.type.includes('return')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" :class="severityTreatment(action.severity).iconSvg"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" :class="severityTreatment(action.severity).iconSvg"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm text-slate-900 font-bold tracking-tight">
                        {{ action.title }}
                    </p>
                    <p class="text-xs text-slate-500 mt-1 font-medium">{{ action.message }}</p>
                </div>
                <div class="flex flex-row sm:flex-col items-center sm:items-end justify-between w-full sm:w-auto gap-3 sm:gap-1">
                    <div class="flex items-center gap-1.5 text-xs font-bold whitespace-nowrap bg-slate-100 px-2 py-1 rounded-md text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ timeAgo(action.timestamp) }}
                    </div>
                    <Link :href="action.action_url" class="text-xs font-bold text-indigo-600 hover:text-white bg-indigo-50 hover:bg-indigo-600 px-4 py-2 rounded-lg transition-all hover:shadow-md">
                        Tindak Lanjuti
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
