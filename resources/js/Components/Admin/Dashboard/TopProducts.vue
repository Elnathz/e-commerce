<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
});

const chartGrouping = defineModel('chartGrouping', { default: 'auto' });
const topProductsLimit = defineModel('topProductsLimit', { default: 5 });

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

// Data for Line Chart (Trend Penjualan)
const chartData = computed(() => {
    if (!props.metrics.sales_chart) return { labels: [], datasets: [] };

    // Enhance chart visual
    const enhanced = JSON.parse(JSON.stringify(props.metrics.sales_chart));
    if (enhanced.datasets && enhanced.datasets.length > 0) {
        enhanced.datasets[0].backgroundColor = 'rgba(99, 102, 241, 0.1)'; // subtle indigo gradient
        enhanced.datasets[0].borderColor = '#4f46e5';
        enhanced.datasets[0].borderWidth = 3;
        enhanced.datasets[0].pointBackgroundColor = '#ffffff';
        enhanced.datasets[0].pointBorderColor = '#4f46e5';
        enhanced.datasets[0].pointBorderWidth = 2;
        enhanced.datasets[0].pointRadius = 4;
        enhanced.datasets[0].pointHoverRadius = 6;
        enhanced.datasets[0].tension = 0.4; // smooth curve
        enhanced.datasets[0].fill = true;
    }
    return enhanced;
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 12,
            titleFont: { size: 13, family: "'Inter', sans-serif" },
            bodyFont: { size: 14, family: "'Inter', sans-serif", weight: 'bold' },
            cornerRadius: 8,
            displayColors: false,
            callbacks: {
                label: function(context) {
                    return formatRupiah(context.raw);
                }
            }
        }
    },
    scales: {
        x: {
            grid: { display: false, drawBorder: false },
            ticks: { font: { family: "'Inter', sans-serif" }, color: '#64748b' }
        },
        y: {
            border: { display: false },
            grid: { color: '#f1f5f9', drawBorder: false },
            ticks: {
                font: { family: "'Inter', sans-serif" },
                color: '#64748b',
                callback: function(value) {
                    return value >= 1000000 ? (value / 1000000) + ' jt' : value;
                }
            }
        }
    },
    interaction: {
        intersect: false,
        mode: 'index',
    },
};

// Stock availability badge for Top Products list
const stockBadge = (status) => {
    switch (status) {
        case 'habis':
            return { label: 'Habis', class: 'text-rose-700 bg-rose-100' };
        case 'kritis':
            return { label: 'Kritis', class: 'text-amber-700 bg-amber-100' };
        case 'perhatian':
            return { label: 'Perhatian', class: 'text-sky-700 bg-sky-100' };
        default:
            return { label: 'Aman', class: 'text-emerald-700 bg-emerald-100' };
    }
};
</script>

<template>
    <!-- Line Chart -->
    <div class="xl:col-span-8 bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200 flex flex-col">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
            <h3 class="text-base font-extrabold text-slate-800 tracking-tight flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" /></svg>
                Trend Penjualan
            </h3>
            <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-xl">
                <button @click="chartGrouping = 'auto'" :class="chartGrouping === 'auto' ? 'bg-white shadow-sm text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-3 py-1.5 text-xs rounded-lg transition-all">Auto</button>
                <button @click="chartGrouping = 'daily'" :class="chartGrouping === 'daily' ? 'bg-white shadow-sm text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-3 py-1.5 text-xs rounded-lg transition-all">Harian</button>
                <button @click="chartGrouping = 'weekly'" :class="chartGrouping === 'weekly' ? 'bg-white shadow-sm text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-3 py-1.5 text-xs rounded-lg transition-all">Mingguan</button>
            </div>
        </div>
        <div class="h-[320px] w-full flex-1">
            <Line :data="chartData" :options="chartOptions" />
        </div>
    </div>

    <!-- Top Products -->
    <div class="xl:col-span-4 bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-base font-extrabold text-slate-800 tracking-tight flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-amber-500"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                Produk Terlaris
            </h3>
            <select v-model="topProductsLimit" class="text-xs font-bold border-none bg-slate-100 rounded-xl py-1.5 pl-3 pr-8 text-slate-700 cursor-pointer focus:ring-0">
                <option :value="5">Top 5</option>
                <option :value="10">Top 10</option>
            </select>
        </div>
        <div class="space-y-6">
            <div v-for="(prod, i) in metrics.top_products || []" :key="i" class="group">
                <div class="flex justify-between items-end text-sm mb-2">
                    <span class="font-bold text-slate-700 truncate pr-4"><span class="text-slate-400 mr-1">{{ i+1 }}.</span> {{ prod.name }}</span>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded" :class="stockBadge(prod.stock_status).class">{{ stockBadge(prod.stock_status).label }}</span>
                        <span class="font-black text-slate-900 bg-slate-100 px-2 py-0.5 rounded-md">{{ prod.total_sold }}</span>
                    </div>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-400 to-indigo-600 h-2.5 rounded-full relative group-hover:from-indigo-500 group-hover:to-indigo-700 transition-all duration-500" :style="{ width: ((prod.total_sold / (metrics.top_products[0]?.total_sold || 1)) * 100) + '%' }">
                        <div class="absolute top-0 right-0 bottom-0 left-0 bg-[linear-gradient(45deg,rgba(255,255,255,.15)_25%,transparent_25%,transparent_50%,rgba(255,255,255,.15)_50%,rgba(255,255,255,.15)_75%,transparent_75%,transparent)] bg-[length:1rem_1rem] opacity-50"></div>
                    </div>
                </div>
            </div>
            <div v-if="!metrics.top_products || metrics.top_products.length === 0" class="text-sm font-medium text-slate-400 text-center py-8 bg-slate-50 rounded-2xl border border-dashed border-slate-200">Data belum tersedia di periode ini</div>
        </div>
    </div>
</template>
