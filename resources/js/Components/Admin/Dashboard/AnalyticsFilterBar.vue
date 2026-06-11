<script setup>
defineProps({
    displayDateRange: {
        type: String,
        default: 'Memilih tanggal...',
    },
    selectedPeriod: {
        type: String,
        default: 'month',
    },
});

const emit = defineEmits(['set-period', 'apply-custom-date']);

const showCustomDate = defineModel('showCustomDate', { default: false });
const customStartDate = defineModel('customStartDate', { default: '' });
const customEndDate = defineModel('customEndDate', { default: '' });
const comparePeriod = defineModel('comparePeriod', { default: 'previous_period' });
</script>

<template>
    <!-- §3 / B10: SECTION ANALITIK — [Filter][Compare][Date], hanya memengaruhi widget di bawah blok ini. -->
    <div class="bg-white/80 backdrop-blur-2xl rounded-[1.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 p-4 flex flex-col lg:flex-row items-center gap-4">
        <!-- Date Picker Dropdown -->
        <div class="relative w-full lg:w-auto min-w-[240px]">
            <button @click="showCustomDate = !showCustomDate" class="w-full pl-4 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl shadow-sm text-sm font-semibold text-slate-700 text-left flex items-center justify-between gap-3 hover:bg-slate-50 hover:border-slate-300 transition-all">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <span>{{ displayDateRange }}</span>
                </div>
                <span class="text-xs text-slate-400">▼</span>
            </button>

            <div v-if="showCustomDate" class="absolute top-full left-0 mt-2 w-full min-w-[280px] bg-white border border-slate-200 shadow-xl rounded-2xl p-5 z-50">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Pilih Tanggal Kustom</h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wider">Mulai</label>
                        <input type="date" v-model="customStartDate" class="w-full border-slate-300 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wider">Sampai</label>
                        <input type="date" v-model="customEndDate" class="w-full border-slate-300 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="button" @click.prevent="emit('apply-custom-date')" class="w-full bg-indigo-600 text-white rounded-xl py-2.5 text-sm font-bold hover:bg-indigo-700 hover:shadow-md hover:shadow-indigo-500/20 transition-all">
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Period Pills -->
        <div class="inline-flex bg-slate-200/50 rounded-xl p-1.5 w-full lg:w-auto overflow-x-auto">
            <button @click="emit('set-period', 'today')" :class="selectedPeriod === 'today' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">Hari Ini</button>
            <button @click="emit('set-period', 'yesterday')" :class="selectedPeriod === 'yesterday' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">Kemarin</button>
            <button @click="emit('set-period', '7days')" :class="selectedPeriod === '7days' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">7 Hari</button>
            <button @click="emit('set-period', '30days')" :class="selectedPeriod === '30days' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">30 Hari</button>
            <button @click="emit('set-period', 'month')" :class="selectedPeriod === 'month' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">Bulan Ini</button>
        </div>

        <div class="h-8 w-px bg-slate-200 hidden lg:block"></div>

        <!-- Compare Period Dropdown -->
        <div class="flex items-center gap-2 w-full lg:w-auto">
            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider whitespace-nowrap hidden sm:block">Bandingkan:</label>
            <select v-model="comparePeriod" class="text-xs font-bold border-none bg-white shadow-sm rounded-xl py-2.5 pl-3 pr-8 text-slate-700 cursor-pointer focus:ring-2 focus:ring-indigo-500 w-full lg:w-auto">
                <option value="previous_period">Periode Sebelumnya</option>
                <option value="previous_year">Tahun Lalu</option>
            </select>
        </div>
    </div>
</template>
