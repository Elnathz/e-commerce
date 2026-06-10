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

// Severity color logic
const getSeverityColor = (count) => {
    if (count > 10) return 'bg-red-50 text-red-700 border-red-200';
    if (count > 5) return 'bg-yellow-50 text-yellow-700 border-yellow-200';
    if (count > 0) return 'bg-blue-50 text-blue-700 border-blue-200';
    return 'bg-green-50 text-green-700 border-green-200'; 
};

const getTrendColor = (trend) => {
    if (trend > 0) return 'text-green-600 bg-green-50';
    if (trend < 0) return 'text-red-600 bg-red-50';
    return 'text-gray-500 bg-gray-50';
};

const getTrendIcon = (trend) => {
    if (trend > 0) return '▲';
    if (trend < 0) return '▼';
    return '—';
};

// Data for Line Chart (Trend Penjualan)
const chartData = computed(() => {
    if (!props.metrics.sales_chart) return { labels: [], datasets: [] };
    return props.metrics.sales_chart;
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            labels: { usePointStyle: true, boxWidth: 8 }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value) {
                    return value / 1000000 + ' jt';
                }
            }
        }
    }
};

// Data for Doughnut Chart (Ringkasan Pembayaran)
const paymentChartData = computed(() => {
    if (!props.metrics.payment_summary || props.metrics.payment_summary.length === 0) {
        return {
            labels: ['Tidak ada data'],
            datasets: [{ data: [1], backgroundColor: ['#E5E7EB'], borderWidth: 0 }]
        };
    }
    return {
        labels: props.metrics.payment_summary.map(p => p.name),
        datasets: [{
            data: props.metrics.payment_summary.map(p => p.percentage),
            backgroundColor: props.metrics.payment_summary.map(p => p.color),
            borderWidth: 0
        }]
    };
});

const paymentChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
        legend: {
            display: false
        }
    }
};

</script>

<template>
    <Head title="Dashboard Operasional" />

    <AdminLayout>
        <div class="py-6 bg-gray-50 min-h-screen">
            <div class="mx-auto max-w-[1600px] sm:px-6 lg:px-8">
                
                <!-- TOP HEADER BAR -->
                <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center gap-4">
                        <h2 class="text-2xl font-bold leading-tight text-gray-800">
                            Dashboard Operasional
                        </h2>
                        <button 
                            @click="isExportDialogOpen = true"
                            class="p-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors shadow-sm border border-indigo-100"
                            title="Export Data"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-col lg:flex-row items-center gap-4 text-sm w-full xl:w-auto">
                        <!-- Date Picker -->
                        <div class="relative w-full lg:w-auto min-w-[260px] z-10">
                            <button @click="showCustomDate = !showCustomDate" class="w-full pl-10 pr-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm text-left flex items-center justify-between gap-2 hover:bg-gray-50 transition">
                                <span class="whitespace-nowrap">{{ displayDateRange }}</span>
                                <span class="text-xs text-gray-400 shrink-0">▼</span>
                            </button>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                📅
                            </div>
                            
                            <!-- Custom Date Dropdown -->
                            <div v-if="showCustomDate" class="absolute top-full left-0 mt-2 w-72 bg-white border border-gray-200 shadow-xl rounded-lg p-4 z-50">
                                <h4 class="text-sm font-bold text-gray-700 mb-3">Pilih Tanggal Kustom</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Mulai</label>
                                        <input type="date" v-model="customStartDate" class="w-full border-gray-300 rounded-md text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Sampai</label>
                                        <input type="date" v-model="customEndDate" class="w-full border-gray-300 rounded-md text-sm">
                                    </div>
                                    <button type="button" @click.prevent="applyCustomDate" class="w-full bg-indigo-600 text-white rounded-md py-2 text-sm font-bold hover:bg-indigo-700 transition">
                                        Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Period Pills -->
                        <div class="inline-flex bg-gray-100 rounded-lg p-1 w-full lg:w-auto justify-between overflow-x-auto items-center">
                            <button @click="setPeriod('today')" :class="selectedPeriod === 'today' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Hari Ini</button>
                            <button @click="setPeriod('yesterday')" :class="selectedPeriod === 'yesterday' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Kemarin</button>
                            <button @click="setPeriod('7days')" :class="selectedPeriod === '7days' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">7 Hari</button>
                            <button @click="setPeriod('30days')" :class="selectedPeriod === '30days' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">30 Hari</button>
                            <button @click="setPeriod('month')" :class="selectedPeriod === 'month' ? 'bg-white shadow text-indigo-600 font-bold border border-indigo-100' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Bulan Ini</button>
                            <button @click="setPeriod('year')" :class="selectedPeriod === 'year' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Tahun Ini</button>
                            <button @click="refreshDashboard" :disabled="isRefreshing" class="ml-2 text-indigo-600 font-bold px-3 py-1.5 hover:bg-indigo-50 rounded-md transition text-xs flex items-center gap-1 disabled:opacity-50">
                                <span :class="{'animate-spin': isRefreshing}">↻</span> 
                                <span v-if="!isRefreshing">Segarkan</span>
                                <span v-else>Menyegarkan...</span>
                            </button>
                        </div>

                        <!-- Toast Notification -->
                        <div v-if="showToast" class="fixed top-4 right-4 z-50 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 animate-fade-in-down">
                            <span class="text-xl">✅</span>
                            <div>
                                <h4 class="font-bold text-sm">Berhasil</h4>
                                <p class="text-xs">Data dasbor telah diperbarui.</p>
                            </div>
                        </div>

                        <!-- Compare Dropdown -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-medium whitespace-nowrap">Bandingkan dengan</span>
                            <select v-model="comparePeriod" class="text-sm border-gray-300 rounded-md shadow-sm py-2">
                                <option value="previous_period">Periode Sebelumnya</option>
                                <option value="previous_year">Tahun Sebelumnya</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- MAIN GRID: LEFT (8) + RIGHT (4) -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    
                    <!-- LEFT COLUMN (Priority, Financial, Charts) -->
                    <div class="xl:col-span-8 space-y-6">
                        
                        <!-- PRIORITAS TINDAKAN -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-md font-bold text-gray-800 border-l-4 border-indigo-500 pl-3">Prioritas Tindakan Saat Ini</h3>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                                    <span class="text-xs text-green-600 font-bold tracking-wider">LIVE</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mb-4 border-l-2 border-gray-300 pl-2">Data operasional di bawah ini adalah kondisi riil saat ini (Real-time), mengabaikan filter tanggal di atas.</p>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <Link :href="route('admin.orders.index', { status: 'paid' })" :class="['block border rounded-lg p-4 pb-8 hover:shadow-md transition relative group', metrics.need_fulfillment > 0 ? 'bg-blue-50 border-blue-200' : 'bg-green-50/50 border-green-100']">
                                    <div :class="['text-xs font-bold uppercase tracking-wider mb-2', metrics.need_fulfillment > 0 ? 'text-blue-700' : 'text-green-700']">Need Fulfillment</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.need_fulfillment || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Order Paid menanti proses</div>
                                    <div class="absolute bottom-3 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>
                                
                                <Link :href="route('admin.returns.index', { status: 'submitted' })" :class="['block border rounded-lg p-4 pb-8 hover:shadow-md transition relative group', metrics.awaiting_approval > 0 ? 'bg-amber-50 border-amber-200' : 'bg-green-50/50 border-green-100']">
                                    <div :class="['text-xs font-bold uppercase tracking-wider mb-2', metrics.awaiting_approval > 0 ? 'text-amber-700' : 'text-green-700']">Antrean Retur Baru</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.awaiting_approval || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Menunggu approval Admin</div>
                                    <div class="absolute bottom-3 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>
                                
                                <Link :href="route('admin.products.index', { filter: 'low_stock' })" :class="['block border rounded-lg p-4 pb-8 hover:shadow-md transition relative group', metrics.low_stock_count > 0 ? 'bg-red-50 border-red-200' : 'bg-green-50/50 border-green-100']">
                                    <div :class="['text-xs font-bold uppercase tracking-wider mb-2', metrics.low_stock_count > 0 ? 'text-red-700 animate-pulse' : 'text-green-700']">Stok Kritis</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.low_stock_count || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Varian produk batas bawah</div>
                                    <div class="absolute bottom-3 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>

                                <Link :href="route('admin.returns.index', { status: 'received' })" :class="['block border rounded-lg p-4 pb-8 hover:shadow-md transition relative group', metrics.awaiting_inspection > 0 ? 'bg-orange-50 border-orange-200' : 'bg-green-50/50 border-green-100']">
                                    <div :class="['text-xs font-bold uppercase tracking-wider mb-2', metrics.awaiting_inspection > 0 ? 'text-orange-700' : 'text-green-700']">Inspeksi Retur</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.awaiting_inspection || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Barang tiba, perlu diperiksa</div>
                                    <div class="absolute bottom-3 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>
                            </div>

                            <div v-if="metrics.sla_breaches?.total_breaches > 0" class="mt-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
                                <div class="flex items-center">
                                    <span class="text-red-500 text-xl mr-3">⚠️</span>
                                    <div>
                                        <h4 class="text-red-800 font-bold">Pelanggaran SLA Operasional!</h4>
                                        <p class="text-red-700 text-sm">Terdapat {{ metrics.sla_breaches.uninspected_returns }} retur belum diinspeksi > 24 jam dan {{ metrics.sla_breaches.unrefunded_returns }} retur belum direfund > 24 jam.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 bg-yellow-50 rounded-lg p-3 flex flex-col md:flex-row md:justify-between md:items-center border border-yellow-100">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">🚚</span>
                                    <div>
                                        <div class="text-sm font-bold text-yellow-800">{{ metrics.pending_shipment?.count || 0 }} Order Sedang Diproses (Pending Shipment)</div>
                                        <div class="text-xs text-yellow-700 mt-1">Total Nilai: {{ formatRupiah(metrics.pending_shipment?.value || 0) }} &bull; Order Paling Lama: {{ metrics.pending_shipment?.oldest_at ? new Date(metrics.pending_shipment.oldest_at).toLocaleString('id-ID') : '-' }}</div>
                                    </div>
                                </div>
                                <Link :href="route('admin.orders.index', { status: 'processing' })" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 mt-2 md:mt-0">Lihat Semua &rarr;</Link>
                            </div>

                            <div v-if="metrics.financial_exposure > 0" class="mt-3 bg-orange-50 rounded-lg p-3 border border-orange-100 flex items-center gap-3">
                                <span class="text-xl">💰</span>
                                <div>
                                    <div class="text-sm font-bold text-orange-800">Revenue at Risk (Exposure Promosi)</div>
                                    <div class="text-xs text-orange-700 mt-1">Total Diskon Direservasi: {{ formatRupiah(metrics.financial_exposure) }} (Menunggu Pembayaran)</div>
                                </div>
                            </div>
                        </section>

                        <!-- TINJAUAN FINANSIAL (KPIs) -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-md font-bold text-gray-800 border-l-4 border-indigo-500 pl-3">Tinjauan Finansial & Performa</h3>
                                    <p class="text-xs text-gray-500 mt-1 ml-4 border-l-2 border-gray-300 pl-2">Data di bawah ini dihitung berdasarkan filter rentang waktu: <strong>{{ displayDateRange }}</strong></p>
                                </div>
                            </div>  

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="border border-gray-100 rounded-xl p-5 hover:shadow-md transition">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Gross Sales</div>
                                    <div class="flex items-end justify-between">
                                        <div class="text-2xl font-black text-gray-900">{{ formatRupiah(metrics.gross_sales || 0) }}</div>
                                        <div class="text-xs font-bold" :class="getTrendColor(metrics.gross_sales_trend || 0)">{{ getTrendIcon(metrics.gross_sales_trend || 0) }} {{ Math.abs(metrics.gross_sales_trend || 0) }}%</div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-2">vs Periode Sebelumnya<br>Total pembayaran berhasil</div>
                                </div>

                                <div class="border border-gray-100 rounded-xl p-5 hover:shadow-md transition">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Order Masuk (Paid)</div>
                                    <div class="flex items-end justify-between">
                                        <div class="text-2xl font-black text-gray-900">{{ metrics.total_orders || 0 }}</div>
                                        <div class="text-xs font-bold" :class="getTrendColor(metrics.total_orders_trend || 0)">{{ getTrendIcon(metrics.total_orders_trend || 0) }} {{ Math.abs(metrics.total_orders_trend || 0) }}%</div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-2">vs Periode Sebelumnya<br>Total volume penjualan (kecuali pending)</div>
                                </div>

                                <div class="border border-gray-100 rounded-xl p-5 hover:shadow-md transition">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Refund Keluar</div>
                                    <div class="flex items-end justify-between">
                                        <div class="text-2xl font-black text-gray-900">{{ formatRupiah(metrics.total_refund || 0) }}</div>
                                        <div class="text-xs font-bold" :class="getTrendColor(-(metrics.total_refund_trend || 0))">{{ getTrendIcon(metrics.total_refund_trend || 0) }} {{ Math.abs(metrics.total_refund_trend || 0) }}%</div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-2">vs Periode Sebelumnya<br>Total uang kembali ke customer</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="border border-gray-100 rounded-xl p-4 flex justify-between items-center bg-gray-50/50">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Checkout Attempts</div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-xl font-black text-gray-900">{{ metrics.checkout_created || 0 }}</div>
                                        <span class="text-xs font-bold" :class="getTrendColor(metrics.checkout_created_trend || 0)">{{ getTrendIcon(metrics.checkout_created_trend || 0) }} {{ Math.abs(metrics.checkout_created_trend || 0) }}%</span>
                                    </div>
                                </div>
                                <div class="border border-gray-100 rounded-xl p-4 flex justify-between items-center bg-gray-50/50">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Checkout-to-Paid</div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-xl font-black text-gray-900">{{ metrics.checkout_to_paid_rate || 0 }}%</div>
                                        <span class="text-xs font-bold" :class="getTrendColor(metrics.checkout_to_paid_rate_trend || 0)">{{ getTrendIcon(metrics.checkout_to_paid_rate_trend || 0) }} {{ Math.abs(metrics.checkout_to_paid_rate_trend || 0) }}%</span>
                                    </div>
                                </div>
                                <div class="border border-gray-100 rounded-xl p-4 flex justify-between items-center bg-gray-50/50">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Order Return Rate</div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-xl font-black text-gray-900">{{ metrics.order_return_rate || 0 }}%</div>
                                        <span class="text-xs font-bold" :class="getTrendColor(-(metrics.order_return_rate_trend || 0))">{{ getTrendIcon(metrics.order_return_rate_trend || 0) }} {{ Math.abs(metrics.order_return_rate_trend || 0) }}%</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- CHARTS & TOP PRODUCTS -->
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                            <!-- Line Chart -->
                            <section class="md:col-span-3 bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Trend Penjualan</h3>
                                    <select v-model="chartGrouping" class="text-xs border-gray-200 rounded-md py-1 pl-2 pr-8 text-gray-600 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="auto">Auto</option>
                                        <option value="daily">Harian</option>
                                        <option value="weekly">Mingguan</option>
                                        <option value="monthly">Bulanan</option>
                                    </select>
                                </div>
                                <div class="h-64 w-full">
                                    <Line :data="chartData" :options="chartOptions" />
                                </div>
                            </section>

                            <!-- Top Products (Horizontal Bars) -->
                            <section class="md:col-span-2 bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Produk Terlaris</h3>
                                    <select v-model="topProductsLimit" class="text-xs border-gray-200 rounded-md py-1 pl-2 pr-8 text-gray-600 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option :value="5">Top 5</option>
                                        <option :value="10">Top 10</option>
                                    </select>
                                </div>
                                <div class="space-y-4">
                                    <div v-for="(prod, i) in metrics.top_products || []" :key="i">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="font-medium text-gray-800">{{ i+1 }}. {{ prod.name }}</span>
                                            <span class="font-bold text-gray-600">{{ prod.total_sold }}</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                                            <div class="bg-indigo-500 h-1.5 rounded-full" :style="{ width: ((prod.total_sold / (metrics.top_products[0]?.total_sold || 1)) * 100) + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN (Sidebar metrics) -->
                    <div class="xl:col-span-4 space-y-6">
                        <!-- Ringkasan Pembayaran -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 border-l-4 border-blue-500 pl-2">Ringkasan Pembayaran</h3>
                            <div class="flex items-center h-40">
                                <div class="w-1/2 h-full">
                                    <Doughnut :data="paymentChartData" :options="paymentChartOptions" />
                                </div>
                                <div class="w-1/2 pl-4 text-xs space-y-2">
                                    <div v-for="payment in metrics.payment_summary" :key="payment.name" class="flex justify-between items-center text-sm border-b border-gray-50 pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: payment.color }"></span>
                                            <span class="text-gray-600">{{ payment.name }}</span>
                                        </div>
                                        <span class="font-bold text-gray-800">{{ payment.percentage }}%</span>
                                    </div>
                                    <div v-if="!metrics.payment_summary || metrics.payment_summary.length === 0" class="text-sm text-gray-400 text-center py-4">Belum ada data pembayaran</div>
                                </div>
                            </div>
                        </section>

                        <!-- Performa Logistik -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 border-l-4 border-green-500 pl-2">Performa Logistik</h3>
                            <div class="space-y-4 text-sm">
                                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                    <span class="text-gray-600 text-sm">Rata-rata Ongkir</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">{{ formatRupiah(metrics.logistics_performance?.avg_shipping_cost || 0) }}</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                    <span class="text-gray-600 text-sm">Rata-rata Lama Pengiriman</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">{{ metrics.logistics_performance?.avg_delivery_days || 0 }} hari</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center py-3">
                                    <span class="text-gray-600 text-sm">Order Tepat Waktu</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">{{ metrics.logistics_performance?.on_time_rate || 0 }}%</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Priority Actions Feed -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex-1">
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 border-l-4 border-purple-500 pl-2">Task Priority Feed</h3>
                            <div v-if="!metrics.priority_actions?.length" class="text-sm text-gray-500 text-center py-4">
                                Tidak ada aksi prioritas saat ini.
                            </div>
                            <div v-else class="space-y-4">
                                <div v-for="action in metrics.priority_actions" :key="action.type + action.id" class="flex gap-3 items-start border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm flex-shrink-0"
                                        :class="{
                                            'bg-red-100 text-red-600': action.priority === 'critical',
                                            'bg-orange-100 text-orange-600': action.priority === 'high',
                                            'bg-blue-100 text-blue-600': action.priority === 'normal'
                                        }">
                                        {{ action.type.includes('return') ? '🔄' : '📦' }}
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-800 font-bold">
                                            <Link :href="action.action_url" class="hover:text-indigo-600">{{ action.title }}</Link>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">{{ action.message }}</p>
                                    </div>
                                    <div class="text-xs font-medium text-right whitespace-nowrap"
                                        :class="{
                                            'text-red-500': action.priority === 'critical',
                                            'text-gray-400': action.priority !== 'critical'
                                        }">
                                        {{ new Date(action.timestamp).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                                    </div>
                                </div>
                            </div>
                        </section>

                    </div>
                </div>

            </div>
        </div>
        
        <ExportDialog :show="isExportDialogOpen" @close="isExportDialogOpen = false" />
    </AdminLayout>
</template>
