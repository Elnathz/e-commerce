<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['drill-down']);

// §2.7: Today Focus = SSOT untuk "apa yang harus dikerjakan hari ini" — roll-up
// priority_score (§2.5/#49), dihitung backend (getTodayFocusSummary). Vue hanya render.
const items = computed(() => props.metrics.today_focus?.items ?? []);
const isAllClear = computed(() => props.metrics.today_focus?.is_all_clear ?? true);
</script>

<template>
    <!-- §2.6: severity=critical → full-width banner merah. Ini MENELAN SLA banner
         lama (§2.7) — satu blok ringkasan, bukan dua. -->
    <div v-if="!isAllClear" data-today-focus="active" class="bg-gradient-to-r from-rose-500 to-red-600 rounded-2xl shadow-lg shadow-rose-500/20 overflow-hidden text-white relative z-10 hover:-translate-y-0.5 transition-transform duration-300">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPgo8cmVjdCB3aWR0aD0iOCIgaGVpZ2h0PSI4IiBmaWxsPSIjZmZmIiBmaWxsLW9wYWNpdHk9IjAuMDUiLz4KPC9zdmc+')] opacity-20"></div>
        <div class="p-5 flex flex-col md:flex-row md:justify-between md:items-center relative z-10 border-b border-white/10">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-lg tracking-tight">Prioritas Hari Ini</h3>
                    <p class="text-sm text-rose-100">{{ items.length }} hal memerlukan tindakan Anda sekarang.</p>
                </div>
            </div>
        </div>
        <div class="bg-white/10 backdrop-blur-md divide-y divide-white/10 p-2 relative z-10">
            <div v-for="item in items" :key="item.key" class="flex justify-between items-center py-3 px-4 hover:bg-white/5 rounded-xl transition-colors">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" :aria-label="item.severity" :class="item.severity === 'critical' ? 'text-rose-300' : 'text-amber-300'" class="w-3 h-3 shrink-0"><circle cx="12" cy="12" r="10" /></svg>
                    <span class="font-medium text-sm">{{ item.label }}</span>
                </div>

                <!-- §2.7-C: drill-down ke Priority Feed (subset terfilter) untuk item yang ada di feed -->
                <button v-if="item.filter_types" type="button" @click="emit('drill-down', item)" class="text-xs bg-white text-rose-600 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md font-bold transition-all hover:scale-105 active:scale-95">
                    Tangani Sekarang
                </button>
                <!-- Stok bukan bagian priority_actions (#49) — langsung ke halaman detail existing -->
                <Link v-else :href="route('admin.products.index', { filter: 'low_stock' })" class="text-xs bg-white text-rose-600 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md font-bold transition-all hover:scale-105 active:scale-95">
                    Lihat Produk
                </Link>
            </div>
        </div>
    </div>

    <!-- §2.7-B: success state EKSPLISIT, bukan menghilang begitu saja -->
    <div v-else data-today-focus="all-clear" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <h3 class="font-bold text-emerald-800">Semua Terkendali</h3>
            <p class="text-sm text-emerald-700">Tidak ada order terlambat · Tidak ada retur lewat SLA · Semua stok aman</p>
        </div>
    </div>
</template>
