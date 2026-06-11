<script setup>
defineProps({
    lastUpdated: {
        type: String,
        default: '',
    },
    isRefreshing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['refresh']);

const isExportDialogOpen = defineModel('isExportDialogOpen', { default: false });
</script>

<template>
    <!-- §3 / B10: HEADER page-wide — Live status + Refresh, TIDAK berisi filter periode/compare. -->
    <div class="bg-white/80 backdrop-blur-2xl rounded-[1.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 relative z-10">
        <div class="shrink-0">
            <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 flex items-center gap-3 flex-wrap">
                Dasbor Utama
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100/80 text-emerald-700 text-xs font-bold uppercase tracking-widest border border-emerald-200/50">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Live
                </span>
            </h2>
            <p class="text-sm text-slate-500 font-medium mt-1">Sikronisasi terakhir: {{ lastUpdated }} WIB</p>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <button @click="emit('refresh')" :disabled="isRefreshing" class="bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2.5 hover:bg-slate-50 hover:border-slate-300 rounded-xl transition-all text-sm font-semibold flex items-center justify-center gap-2 disabled:opacity-50 w-full sm:w-auto shrink-0">
                <span :class="{'animate-spin': isRefreshing}">↻</span>
                <span v-if="!isRefreshing">Muat Ulang</span>
                <span v-else>Memuat...</span>
            </button>

            <button @click="isExportDialogOpen = true" class="bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2.5 hover:bg-slate-50 hover:border-slate-300 rounded-xl transition-all text-sm font-semibold flex items-center justify-center gap-2 w-full sm:w-auto shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span>Export</span>
            </button>
        </div>
    </div>
</template>
