<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExportDialog from '@/Components/Admin/ExportDialog.vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler
} from 'chart.js';
import { Line, Doughnut } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler
);

const props = defineProps({
    metrics: Object,
    lowStockProducts: Array,
    filters: {
        type: Object,
        default: () => ({})
    },
    isOwner: {
        type: Boolean,
        default: false
    }
});

const selectedPeriod = ref(props.metrics.period || 'month');
const comparePeriod = ref(props.metrics.compare_period || 'previous_period');
const chartGrouping = ref(props.filters?.chart_grouping || 'auto');
const topProductsLimit = ref(props.filters?.top_products_limit || 5);

const setPeriod = (period) => {
    selectedPeriod.value = period;
};

watch([selectedPeriod, comparePeriod, chartGrouping, topProductsLimit], ([newPeriod, newCompare, newChartGrouping, newLimit]) => {
    router.get(route('admin.dashboard'), { 
        period: newPeriod, 
        compare_period: newCompare,
        chart_grouping: newChartGrouping,
        top_products_limit: newLimit
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const isRefreshing = ref(false);
const showToast = ref(false);

const refreshDashboard = () => {
    if (isRefreshing.value) return;
    
    isRefreshing.value = true;
    router.post(route('admin.dashboard.refresh'), { period: selectedPeriod.value, compare_period: comparePeriod.value }, { 
        preserveScroll: true,
        onSuccess: () => {
            showToast.value = true;
            setTimeout(() => {
                showToast.value = false;
            }, 3000);
        },
        onFinish: () => {
            isRefreshing.value = false;
        }
    });
};

const showCustomDate = ref(false);
const customStartDate = ref('');
const customEndDate = ref('');

const applyCustomDate = () => {
    if (customStartDate.value && customEndDate.value) {
        setPeriod(`${customStartDate.value}|${customEndDate.value}`);
        showCustomDate.value = false;
    }
};

const displayDateRange = computed(() => {
    if (!props.metrics.period_start || !props.metrics.period_end) return 'Memilih tanggal...';
    
    const start = new Date(props.metrics.period_start);
    const end = new Date(props.metrics.period_end);
    
    const options = { day: 'numeric', month: 'short', year: 'numeric' };
    
    if (props.metrics.period_start === props.metrics.period_end) {
        return start.toLocaleDateString('id-ID', options);
    }
    
    if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
        return `${start.getDate()} - ${end.getDate()} ${start.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' })}`;
    }
    
    return `${start.toLocaleDateString('id-ID', options)} - ${end.toLocaleDateString('id-ID', options)}`;
});

const isExportDialogOpen = ref(false);

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const getTrendColor = (trend) => {
    if (trend > 0) return 'text-emerald-600 bg-emerald-50/80 border-emerald-100';
    if (trend < 0) return 'text-rose-600 bg-rose-50/80 border-rose-100';
    return 'text-slate-500 bg-slate-50/80 border-slate-100';
};

const getTrendIcon = (trend) => {
    if (trend > 0) return '↗';
    if (trend < 0) return '↘';
    return '→';
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

// Data for Doughnut Chart
const paymentChartData = computed(() => {
    if (!props.metrics.payment_summary || props.metrics.payment_summary.length === 0) {
        return {
            labels: ['Tidak ada data'],
            datasets: [{ data: [1], backgroundColor: ['#f1f5f9'], borderWidth: 0 }]
        };
    }
    return {
        labels: props.metrics.payment_summary.map(p => p.name),
        datasets: [{
            data: props.metrics.payment_summary.map(p => p.percentage),
            backgroundColor: props.metrics.payment_summary.map(p => p.color),
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 4
        }]
    };
});

const paymentChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '75%',
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            padding: 12,
            cornerRadius: 8,
            callbacks: {
                label: function(context) {
                    return ` ${context.raw}%`;
                }
            }
        }
    }
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

const lastUpdated = computed(() => props.metrics.last_updated ? new Date(props.metrics.last_updated).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second:'2-digit' }) : '');

const criticalVouchers = computed(() => (props.metrics.vouchers_alert || []).filter(v => v.severity === 'critical'));
const warningVouchers = computed(() => (props.metrics.vouchers_alert || []).filter(v => v.severity === 'warning'));

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

// True when there is genuinely no transaction activity in the selected period (owner-only, since
// non-owner financial fields are always zeroed out for access control, not data absence).
const hasNoActivityInPeriod = computed(() => {
    return props.isOwner && (props.metrics.checkout_created || 0) === 0 && (props.metrics.total_orders || 0) === 0;
});

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
    <Head title="Dashboard Operasional" />

    <AdminLayout>
        <div class="py-8 bg-slate-50 min-h-screen font-sans">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- HEADER & DATE PICKER (Glassmorphism & Unified) -->
                <div class="bg-white/80 backdrop-blur-2xl rounded-[1.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 p-6 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6 relative z-10">
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

                    <div class="flex flex-col lg:flex-row items-center gap-4 w-full xl:w-auto bg-slate-50/50 p-2 rounded-2xl border border-slate-100/50">
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
                                    <button type="button" @click.prevent="applyCustomDate" class="w-full bg-indigo-600 text-white rounded-xl py-2.5 text-sm font-bold hover:bg-indigo-700 hover:shadow-md hover:shadow-indigo-500/20 transition-all">
                                        Terapkan Filter
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Period Pills -->
                        <div class="inline-flex bg-slate-200/50 rounded-xl p-1.5 w-full lg:w-auto overflow-x-auto">
                            <button @click="setPeriod('today')" :class="selectedPeriod === 'today' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">Hari Ini</button>
                            <button @click="setPeriod('yesterday')" :class="selectedPeriod === 'yesterday' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">Kemarin</button>
                            <button @click="setPeriod('7days')" :class="selectedPeriod === '7days' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">7 Hari</button>
                            <button @click="setPeriod('30days')" :class="selectedPeriod === '30days' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">30 Hari</button>
                            <button @click="setPeriod('month')" :class="selectedPeriod === 'month' ? 'bg-white shadow-sm text-indigo-600 font-bold' : 'text-slate-600 font-medium hover:text-slate-900 hover:bg-slate-200/50'" class="px-4 py-2 text-xs rounded-lg whitespace-nowrap transition-all">Bulan Ini</button>
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

                        <div class="h-8 w-px bg-slate-200 hidden lg:block"></div>

                        <button @click="refreshDashboard" :disabled="isRefreshing" class="bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2.5 hover:bg-slate-50 hover:border-slate-300 rounded-xl transition-all text-sm font-semibold flex items-center justify-center gap-2 disabled:opacity-50 w-full lg:w-auto shrink-0">
                            <span :class="{'animate-spin': isRefreshing}">↻</span>
                            <span v-if="!isRefreshing">Muat Ulang</span>
                            <span v-else>Memuat...</span>
                        </button>

                        <button @click="isExportDialogOpen = true" class="bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2.5 hover:bg-slate-50 hover:border-slate-300 rounded-xl transition-all text-sm font-semibold flex items-center justify-center gap-2 w-full lg:w-auto shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span>Export</span>
                        </button>
                    </div>
                </div>

                <!-- Toast Notification -->
                <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform -translate-y-4 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform -translate-y-4 opacity-0">
                    <div v-if="showToast" class="fixed top-6 right-6 z-[100] bg-slate-900 text-white px-5 py-4 rounded-2xl shadow-2xl flex items-center gap-4">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400">✓</div>
                        <div>
                            <h4 class="font-bold text-sm">Berhasil Diperbarui</h4>
                            <p class="text-xs text-slate-400">Data telah disinkronisasi.</p>
                        </div>
                    </div>
                </transition>

                <!-- SLA ALERT BANNER (Premium Styling) -->
                <div v-if="metrics.sla_breaches?.total_breaches > 0" class="bg-gradient-to-r from-rose-500 to-red-600 rounded-2xl shadow-lg shadow-rose-500/20 overflow-hidden text-white relative z-10 hover:-translate-y-0.5 transition-transform duration-300">
                    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPgo8cmVjdCB3aWR0aD0iOCIgaGVpZ2h0PSI4IiBmaWxsPSIjZmZmIiBmaWxsLW9wYWNpdHk9IjAuMDUiLz4KPC9zdmc+')] opacity-20"></div>
                    <div class="p-5 flex flex-col md:flex-row md:justify-between md:items-center relative z-10 border-b border-white/10">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm shadow-inner">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-white">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg tracking-tight">Perhatian Operasional Diperlukan!</h3>
                                <p class="text-sm text-rose-100">{{ metrics.sla_breaches.total_breaches }} peringatan batas waktu terlampaui (SLA).</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md divide-y divide-white/10 p-2 relative z-10">
                        <div v-if="metrics.sla_breaches.order_paid_overdue > 0" class="flex justify-between items-center py-3 px-4 hover:bg-white/5 rounded-xl transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-rose-300 animate-ping"></span>
                                <span class="font-medium text-sm">{{ metrics.sla_breaches.order_paid_overdue }} Order Telat Diproses (>24 jam)</span>
                            </div>
                            <Link :href="route('admin.orders.index', { status: 'paid', sort: 'paid_at', direction: 'asc' })" class="text-xs bg-white text-rose-600 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md font-bold transition-all hover:scale-105 active:scale-95">Tangani Sekarang</Link>
                        </div>
                        <div v-if="metrics.sla_breaches.order_processing_overdue > 0" class="flex justify-between items-center py-3 px-4 hover:bg-white/5 rounded-xl transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-rose-300 animate-ping"></span>
                                <span class="font-medium text-sm">{{ metrics.sla_breaches.order_processing_overdue }} Order Telat Dikirim (>48 jam)</span>
                            </div>
                            <Link :href="route('admin.orders.index', { status: 'processing', sort: 'processing_at', direction: 'asc' })" class="text-xs bg-white text-rose-600 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md font-bold transition-all hover:scale-105 active:scale-95">Tangani Sekarang</Link>
                        </div>
                        <div v-if="metrics.sla_breaches.return_submitted_overdue > 0" class="flex justify-between items-center py-3 px-4 hover:bg-white/5 rounded-xl transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-amber-300 animate-ping"></span>
                                <span class="font-medium text-sm">{{ metrics.sla_breaches.return_submitted_overdue }} Retur Belum Direspon (>24 jam)</span>
                            </div>
                            <Link :href="route('admin.returns.index', { status: 'submitted' })" class="text-xs bg-white text-rose-600 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md font-bold transition-all hover:scale-105 active:scale-95">Tinjau Sekarang</Link>
                        </div>
                        <div v-if="metrics.sla_breaches.return_received_overdue > 0" class="flex justify-between items-center py-3 px-4 hover:bg-white/5 rounded-xl transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-amber-300 animate-ping"></span>
                                <span class="font-medium text-sm">{{ metrics.sla_breaches.return_received_overdue }} Retur Belum Diinspeksi (>48 jam)</span>
                            </div>
                            <Link :href="route('admin.returns.index', { status: 'received' })" class="text-xs bg-white text-rose-600 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md font-bold transition-all hover:scale-105 active:scale-95">Inspeksi Sekarang</Link>
                        </div>
                        <div v-if="metrics.sla_breaches.return_refund_overdue > 0" class="flex justify-between items-center py-3 px-4 hover:bg-white/5 rounded-xl transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-violet-300 animate-ping"></span>
                                <span class="font-medium text-sm">{{ metrics.sla_breaches.return_refund_overdue }} Refund Belum Diproses (>72 jam)</span>
                            </div>
                            <Link :href="route('admin.returns.index', { status: 'inspected' })" class="text-xs bg-white text-violet-700 px-4 py-1.5 rounded-lg shadow-sm hover:shadow-md font-bold transition-all hover:scale-105 active:scale-95">Proses Refund</Link>
                        </div>
                    </div>
                </div>

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

                <!-- ========================================== -->
                <!-- MIDDLE: FEEDS & RISKS -->
                <!-- ========================================== -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 lg:gap-8">
                    
                    <!-- LEFT COLUMN (Needs Action) -->
                    <div class="xl:col-span-7 space-y-6 lg:space-y-8">
                        
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
                        <section class="bg-white/80 backdrop-blur-xl p-6 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-widest flex items-center gap-2">
                                    <span class="w-2 h-6 bg-indigo-500 rounded-full"></span>
                                    Feed Aktivitas Prioritas
                                </h3>
                            </div>
                            
                            <div v-if="!metrics.priority_actions?.length" class="flex flex-col items-center justify-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                <div class="mb-3 text-slate-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <h4 class="font-bold text-slate-700">Semua Terkendali</h4>
                                <p class="text-sm text-slate-500 mt-1">Tidak ada item mendesak di antrean Anda.</p>
                            </div>
                            
                            <div v-else class="space-y-4">
                                <div v-for="action in metrics.priority_actions" :key="action.type + action.id" class="group flex flex-col sm:flex-row gap-4 items-start sm:items-center p-4 rounded-xl border border-transparent hover:border-slate-100 hover:bg-slate-50/80 transition-all duration-200">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 shadow-inner"
                                        :class="{
                                            'bg-rose-100/80 text-rose-600 border border-rose-200': action.priority === 'critical',
                                            'bg-amber-100/80 text-amber-600 border border-amber-200': action.priority === 'high',
                                            'bg-sky-100/80 text-sky-600 border border-sky-200': action.priority === 'normal'
                                        }">
                                        <svg v-if="action.type.includes('return')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
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
                    </div>

                    <!-- RIGHT COLUMN (Risks) -->
                    <div class="xl:col-span-5 space-y-6 lg:space-y-8">
                        
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
                                <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
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
                                </div>
                                
                                <!-- Pending Refund -->
                                <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
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
                                </div>
                                
                                <!-- Reserved Voucher -->
                                <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
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
                                </div>
                            </div>
                        </div>

                        <!-- Voucher Alerts (Warning tier - critical tier shown as chip in Zona A) -->
                        <div v-if="warningVouchers.length > 0" class="bg-white/80 backdrop-blur-xl p-5 rounded-2xl shadow-sm border border-amber-100 bg-gradient-to-b from-amber-50/30 to-transparent">
                            <h3 class="text-xs font-bold text-amber-800 uppercase tracking-widest mb-3 flex items-center gap-2">Peringatan Kuota Promo</h3>
                            <div class="space-y-3">
                                <div v-for="alert in warningVouchers" :key="alert.id" class="p-3 rounded-xl border border-amber-200 flex justify-between items-center bg-white shadow-sm hover:shadow-md transition-shadow">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">{{ alert.code }}</div>
                                        <div class="text-xs font-medium mt-0.5 text-amber-600">Sisa {{ alert.remaining }} kuota</div>
                                    </div>
                                    <Link :href="route('admin.promotions.edit', alert.id)" class="text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg transition-colors">Kelola</Link>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ========================================== -->
                <!-- SECTION DIVIDER -->
                <!-- ========================================== -->
                <div class="py-8">
                    <div class="flex items-center gap-4">
                        <div class="h-px bg-slate-300 flex-1"></div>
                        <h2 class="text-xl font-extrabold tracking-widest uppercase text-slate-400">Analisis Performa & Finansial</h2>
                        <div class="h-px bg-slate-300 flex-1"></div>
                    </div>
                </div>

                <!-- Empty State: no transactions at all in selected period -->
                <div v-if="hasNoActivityInPeriod" class="bg-white/80 backdrop-blur-xl border border-dashed border-slate-300 rounded-3xl p-10 flex flex-col items-center text-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-700">Belum Ada Transaksi di Periode Ini</h3>
                    <p class="text-sm text-slate-400 max-w-md">Tidak ditemukan checkout maupun order pada rentang tanggal yang dipilih. Coba ubah periode atau rentang tanggal untuk melihat data analisis.</p>
                </div>

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
                                <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border" :class="getTrendColor(metrics.gross_sales_trend || 0)">
                                    <span>{{ getTrendIcon(metrics.gross_sales_trend || 0) }}</span>
                                    <span>{{ Math.abs(metrics.gross_sales_trend || 0) }}%</span>
                                </div>
                                <span class="text-xs font-semibold text-slate-400">vs periode lalu</span>
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
                                <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border" :class="getTrendColor(metrics.total_orders_trend || 0)">
                                    <span>{{ getTrendIcon(metrics.total_orders_trend || 0) }}</span>
                                    <span>{{ Math.abs(metrics.total_orders_trend || 0) }}%</span>
                                </div>
                                <span class="text-xs font-semibold text-slate-400">vs periode lalu</span>
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
                                <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border" :class="getTrendColor(-(metrics.total_refund_trend || 0))">
                                    <span>{{ getTrendIcon(metrics.total_refund_trend || 0) }}</span>
                                    <span>{{ Math.abs(metrics.total_refund_trend || 0) }}%</span>
                                </div>
                                <span class="text-xs font-semibold text-slate-400">vs periode lalu</span>
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
                            <span class="text-xs font-bold px-2 py-0.5 rounded border" :class="getTrendColor(metrics.checkout_created_trend || 0)">{{ getTrendIcon(metrics.checkout_created_trend || 0) }} {{ Math.abs(metrics.checkout_created_trend || 0) }}%</span>
                        </div>
                    </div>
                    <div class="bg-slate-100/50 border border-slate-200/60 rounded-2xl p-5 flex justify-between items-center hover:bg-white transition-colors hover:shadow-sm">
                        <div class="text-xs font-extrabold text-slate-500 uppercase tracking-widest">Checkout-to-Paid</div>
                        <div class="flex items-center gap-3">
                            <div class="text-xl font-black text-slate-800">{{ metrics.checkout_to_paid_rate || 0 }}%</div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded border" :class="getTrendColor(metrics.checkout_to_paid_rate_trend || 0)">{{ getTrendIcon(metrics.checkout_to_paid_rate_trend || 0) }} {{ Math.abs(metrics.checkout_to_paid_rate_trend || 0) }}%</span>
                        </div>
                    </div>
                    <div class="bg-slate-100/50 border border-slate-200/60 rounded-2xl p-5 flex justify-between items-center hover:bg-white transition-colors hover:shadow-sm">
                        <div class="text-xs font-extrabold text-slate-500 uppercase tracking-widest">Return Rate</div>
                        <div class="flex items-center gap-3">
                            <div class="text-xl font-black text-slate-800">{{ metrics.order_return_rate || 0 }}%</div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded border" :class="getTrendColor(-(metrics.order_return_rate_trend || 0))">{{ getTrendIcon(metrics.order_return_rate_trend || 0) }} {{ Math.abs(metrics.order_return_rate_trend || 0) }}%</span>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- CHARTS & TOP PRODUCTS -->
                <!-- ========================================== -->
                <div v-if="!hasNoActivityInPeriod" class="grid grid-cols-1 xl:grid-cols-12 gap-6 lg:gap-8">
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
                </div>

                <!-- ========================================== -->
                <!-- BOTTOM LOGISTICS & PAYMENTS -->
                <!-- ========================================== -->
                <div v-if="!hasNoActivityInPeriod" class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
                    <!-- Ringkasan Pembayaran -->
                    <section class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200">
                        <h3 class="text-base font-extrabold text-slate-800 tracking-tight flex items-center gap-2 mb-8">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-emerald-500"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                            Distribusi Pembayaran
                        </h3>
                        <div class="flex flex-col sm:flex-row items-center gap-8 h-full min-h-[220px]">
                            <div class="w-48 h-48 relative shrink-0">
                                <Doughnut :data="paymentChartData" :options="paymentChartOptions" />
                                <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none">
                                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total</span>
                                    <span class="text-xl font-black text-slate-900">{{ metrics.payment_summary?.length || 0 }} Metode</span>
                                </div>
                            </div>
                            <div class="flex-1 w-full space-y-3">
                                <div v-for="payment in metrics.payment_summary" :key="payment.name" class="flex justify-between items-center p-3 rounded-xl hover:bg-slate-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-4 h-4 rounded-full shadow-inner" :style="{ backgroundColor: payment.color }"></div>
                                        <span class="text-sm font-bold text-slate-700">{{ payment.name }}</span>
                                    </div>
                                    <span class="font-black text-slate-900 bg-slate-100 px-2 py-0.5 rounded-lg">{{ payment.percentage }}%</span>
                                </div>
                                <div v-if="!metrics.payment_summary || metrics.payment_summary.length === 0" class="text-sm text-slate-400 text-center py-4">Belum ada data</div>
                            </div>
                        </div>
                    </section>

                    <!-- Performa Logistik -->
                    <section class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200 flex flex-col justify-between">
                        <h3 class="text-base font-extrabold text-slate-800 tracking-tight flex items-center gap-2 mb-8">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-sky-500"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                            Performa Logistik
                        </h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 flex justify-between items-center group hover:bg-white hover:shadow-md transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <span class="text-slate-600 font-bold text-sm">Rata-rata Ongkir</span>
                                </div>
                                <span class="font-black text-slate-900 text-xl">{{ formatRupiah(metrics.logistics_performance?.avg_shipping_cost || 0) }}</span>
                            </div>
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 flex justify-between items-center group hover:bg-white hover:shadow-md transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <span class="text-slate-600 font-bold text-sm">Rata-rata Lama Pengiriman</span>
                                </div>
                                <div class="flex items-baseline gap-1 text-xl font-black text-slate-900">
                                    {{ metrics.logistics_performance?.avg_delivery_days || 0 }} <span class="text-sm text-slate-400 font-bold">hari</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

            </div>
        </div>
        
        <ExportDialog :show="isExportDialogOpen" @close="isExportDialogOpen = false" />
    </AdminLayout>
</template>
