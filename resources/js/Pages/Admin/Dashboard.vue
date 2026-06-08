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
const comparePeriod = ref('previous_period');

const setPeriod = (period) => {
    selectedPeriod.value = period;
};

watch(selectedPeriod, (value) => {
    router.get(route('admin.dashboard'), { period: value }, {
        preserveState: true,
        replace: true,
    });
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

// Mock data for Line Chart (Trend Penjualan)
const chartData = computed(() => ({
    labels: ['1 Mei', '6 Mei', '11 Mei', '16 Mei', '21 Mei', '26 Mei', '31 Mei'],
    datasets: [
        {
            label: 'Gross Sales',
            data: [10000000, 25000000, 18000000, 28000000, 15000000, 22000000, 19000000],
            borderColor: '#4F46E5', // Indigo
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            tension: 0.4,
            fill: true
        },
        {
            label: 'Refund',
            data: [1000000, 2000000, 1500000, 4000000, 1000000, 5000000, 2000000],
            borderColor: '#10B981', // Emerald
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true
        }
    ]
}));

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

// Mock data for Doughnut Chart (Ringkasan Pembayaran)
const paymentChartData = computed(() => ({
    labels: ['Transfer Bank', 'E-Wallet', 'Virtual Account', 'QRIS'],
    datasets: [
        {
            data: [45.8, 32.1, 15.6, 7.9],
            backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'],
            borderWidth: 0
        }
    ]
}));

const paymentChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
        legend: {
            position: 'right',
            labels: { usePointStyle: true, boxWidth: 8 }
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
                            class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium transition"
                        >
                            Export Data ⌄
                        </button>
                    </div>

                    <div class="flex flex-col lg:flex-row items-center gap-4 text-sm w-full xl:w-auto">
                        <!-- Date Picker Mock -->
                        <div class="relative w-full lg:w-48">
                            <input type="text" class="w-full pl-10 pr-4 py-2 border-gray-300 rounded-md shadow-sm text-sm" value="1 - 31 Mei 2025" readonly>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                📅
                            </div>
                        </div>

                        <!-- Period Pills -->
                        <div class="inline-flex bg-gray-100 rounded-lg p-1 w-full lg:w-auto justify-between overflow-x-auto">
                            <button @click="setPeriod('today')" :class="selectedPeriod === 'today' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Hari Ini</button>
                            <button @click="setPeriod('yesterday')" :class="selectedPeriod === 'yesterday' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Kemarin</button>
                            <button @click="setPeriod('7days')" :class="selectedPeriod === '7days' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">7 Hari</button>
                            <button @click="setPeriod('30days')" :class="selectedPeriod === '30days' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">30 Hari</button>
                            <button @click="setPeriod('month')" :class="selectedPeriod === 'month' ? 'bg-white shadow text-indigo-600 font-bold border border-indigo-100' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Bulan Ini</button>
                            <button @click="setPeriod('year')" :class="selectedPeriod === 'year' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1.5 text-xs rounded-md whitespace-nowrap">Tahun Ini</button>
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
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <Link :href="route('admin.orders.index', { status: 'paid' })" class="block bg-green-50/50 border border-green-100 rounded-lg p-4 hover:shadow-md transition relative group">
                                    <div class="text-xs font-bold text-green-700 uppercase tracking-wider mb-2">Need Fulfillment</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.need_fulfillment || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Order Paid menanti proses</div>
                                    <div class="absolute bottom-4 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>
                                
                                <Link :href="route('admin.returns.index', { status: 'submitted' })" class="block bg-green-50/50 border border-green-100 rounded-lg p-4 hover:shadow-md transition relative group">
                                    <div class="text-xs font-bold text-green-700 uppercase tracking-wider mb-2">Antrean Retur Baru</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.awaiting_approval || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Menunggu approval Admin</div>
                                    <div class="absolute bottom-4 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>
                                
                                <Link :href="route('admin.products.index', { filter: 'low_stock' })" class="block bg-green-50/50 border border-green-100 rounded-lg p-4 hover:shadow-md transition relative group">
                                    <div class="text-xs font-bold text-green-700 uppercase tracking-wider mb-2">Stok Kritis</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.low_stock_count || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Varian produk batas bawah</div>
                                    <div class="absolute bottom-4 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>

                                <Link :href="route('admin.returns.index', { status: 'received' })" class="block bg-green-50/50 border border-green-100 rounded-lg p-4 hover:shadow-md transition relative group">
                                    <div class="text-xs font-bold text-green-700 uppercase tracking-wider mb-2">Inspeksi Retur</div>
                                    <div class="text-3xl font-black text-gray-900">{{ metrics.awaiting_inspection || 0 }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Barang tiba, perlu diperiksa</div>
                                    <div class="absolute bottom-4 right-4 text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition">Lihat Detail &rarr;</div>
                                </Link>
                            </div>

                            <div class="mt-4 bg-yellow-50 rounded-lg p-3 flex justify-between items-center border border-yellow-100">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">🚚</span>
                                    <span class="text-sm font-bold text-yellow-800">{{ metrics.in_processing || 0 }} Order Sedang Diproses (In Processing)</span>
                                </div>
                                <Link :href="route('admin.orders.index', { status: 'processing' })" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">Lihat Semua &rarr;</Link>
                            </div>
                        </section>

                        <!-- TINJAUAN FINANSIAL (KPIs) -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-md font-bold text-gray-800 border-l-4 border-indigo-500 pl-3">Tinjauan Finansial & Performa</h3>
                                <div class="inline-flex bg-gray-100 rounded-lg p-1">
                                    <button class="px-3 py-1 text-xs text-gray-500 hover:text-gray-900">Hari</button>
                                    <button class="px-3 py-1 text-xs text-gray-500 hover:text-gray-900">Minggu</button>
                                    <button class="px-3 py-1 text-xs bg-white shadow text-gray-900 font-bold rounded">Bulan</button>
                                    <button class="px-3 py-1 text-xs text-gray-500 hover:text-gray-900">Tahun</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="border border-gray-100 rounded-xl p-5 hover:shadow-md transition">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Gross Sales</div>
                                    <div class="flex items-end justify-between">
                                        <div class="text-2xl font-black text-gray-900">{{ formatRupiah(metrics.gross_sales || 0) }}</div>
                                        <div class="text-xs font-bold" :class="getTrendColor(metrics.gross_sales_trend || 0)">{{ getTrendIcon(metrics.gross_sales_trend || 0) }} {{ Math.abs(metrics.gross_sales_trend || 0) }}%</div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-2">vs Apr 2025<br>Total pembayaran berhasil</div>
                                </div>

                                <div class="border border-gray-100 rounded-xl p-5 hover:shadow-md transition">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Order Masuk (Paid)</div>
                                    <div class="flex items-end justify-between">
                                        <div class="text-2xl font-black text-gray-900">{{ metrics.total_orders || 0 }}</div>
                                        <div class="text-xs font-bold" :class="getTrendColor(metrics.total_orders_trend || 0)">{{ getTrendIcon(metrics.total_orders_trend || 0) }} {{ Math.abs(metrics.total_orders_trend || 0) }}%</div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-2">vs Apr 2025<br>Total volume penjualan (kecuali pending)</div>
                                </div>

                                <div class="border border-gray-100 rounded-xl p-5 hover:shadow-md transition">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Refund Keluar</div>
                                    <div class="flex items-end justify-between">
                                        <div class="text-2xl font-black text-gray-900">{{ formatRupiah(metrics.total_refund || 0) }}</div>
                                        <div class="text-xs font-bold" :class="getTrendColor(-(metrics.total_refund_trend || 0))">{{ getTrendIcon(metrics.total_refund_trend || 0) }} {{ Math.abs(metrics.total_refund_trend || 0) }}%</div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-2">vs Apr 2025<br>Total uang kembali ke customer</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="border border-gray-100 rounded-xl p-4 flex justify-between items-center bg-gray-50/50">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Checkout Attempts</div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-xl font-black text-gray-900">{{ metrics.checkout_created || 0 }}</div>
                                        <span class="text-xs text-green-600 font-bold">▲ 8.4%</span>
                                    </div>
                                </div>
                                <div class="border border-gray-100 rounded-xl p-4 flex justify-between items-center bg-gray-50/50">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Checkout-to-Paid</div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-xl font-black text-gray-900">{{ metrics.checkout_to_paid_rate || 0 }}%</div>
                                        <span class="text-xs text-green-600 font-bold">▲ 3.1%</span>
                                    </div>
                                </div>
                                <div class="border border-gray-100 rounded-xl p-4 flex justify-between items-center bg-gray-50/50">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Order Return Rate</div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-xl font-black text-gray-900">{{ metrics.order_return_rate || 0 }}%</div>
                                        <span class="text-xs text-red-600 font-bold">▲ 0.8%</span>
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
                                    <select class="text-xs border-gray-200 rounded-md py-1 px-2 text-gray-600">
                                        <option>Harian</option>
                                        <option>Mingguan</option>
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
                                    <select class="text-xs border-gray-200 rounded-md py-1 px-2 text-gray-600">
                                        <option>Top 5</option>
                                        <option>Top 10</option>
                                    </select>
                                </div>
                                <div class="space-y-4">
                                    <div v-for="(prod, i) in metrics.top_products?.slice(0, 5) || [{name:'Produk A', total_sold:230}, {name:'Produk B', total_sold:198}, {name:'Produk C', total_sold:156}, {name:'Produk D', total_sold:120}, {name:'Produk E', total_sold:98}]" :key="i">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="font-medium text-gray-800">{{ i+1 }}. {{ prod.name }}</span>
                                            <span class="font-bold text-gray-600">{{ prod.total_sold }}</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                                            <div class="bg-indigo-500 h-1.5 rounded-full" :style="{ width: ((prod.total_sold / 230) * 100) + '%' }"></div>
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
                                    <div class="flex justify-between"><span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Transfer Bank</span> <b>45.8%</b></div>
                                    <div class="flex justify-between"><span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> E-Wallet</span> <b>32.1%</b></div>
                                    <div class="flex justify-between"><span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> Virtual Account</span> <b>15.6%</b></div>
                                    <div class="flex justify-between"><span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> QRIS</span> <b>7.9%</b></div>
                                </div>
                            </div>
                        </section>

                        <!-- Performa Logistik -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 border-l-4 border-green-500 pl-2">Performa Logistik</h3>
                            <div class="space-y-4 text-sm">
                                <div class="flex justify-between items-center border-b border-gray-50 pb-2">
                                    <span class="text-gray-600">Rata-rata Ongkir</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">Rp 24.500</span>
                                        <span class="text-xs text-green-600 font-bold">▲ 6.2%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center border-b border-gray-50 pb-2">
                                    <span class="text-gray-600">Rata-rata Lama Pengiriman</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">2.8 hari</span>
                                        <span class="text-xs text-green-600 font-bold">▼ 0.3 hari</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Order Tepat Waktu</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">92.1%</span>
                                        <span class="text-xs text-green-600 font-bold">▲ 4.6%</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Aktivitas Terbaru -->
                        <section class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex-1">
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 border-l-4 border-purple-500 pl-2">Aktivitas Terbaru</h3>
                            <div class="space-y-4">
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-sm flex-shrink-0">📦</div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-800">Order <span class="font-bold text-indigo-600">#INV-250601-0012</span></p>
                                        <p class="text-xs text-gray-500">Pembayaran berhasil</p>
                                    </div>
                                    <div class="text-xs text-gray-400 text-right whitespace-nowrap">Baru saja</div>
                                </div>
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 text-sm flex-shrink-0">🔄</div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-800">Retur <span class="font-bold text-indigo-600">#RTR-250601-0008</span></p>
                                        <p class="text-xs text-gray-500">Menunggu inspeksi</p>
                                    </div>
                                    <div class="text-xs text-gray-400 text-right whitespace-nowrap">5 menit lalu</div>
                                </div>
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600 text-sm flex-shrink-0">⚠️</div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-800">Stok Kritis: <span class="font-bold">Produk K - Variant Merah</span></p>
                                        <p class="text-xs text-gray-500 text-red-600 font-medium">Sisa 5</p>
                                    </div>
                                    <div class="text-xs text-gray-400 text-right whitespace-nowrap">15 menit lalu</div>
                                </div>
                            </div>
                            <div class="mt-6 text-center">
                                <button class="text-sm text-indigo-600 font-bold hover:text-indigo-800">Lihat Semua Aktivitas &rarr;</button>
                            </div>
                        </section>

                    </div>
                </div>

            </div>
        </div>
        
        <ExportDialog :show="isExportDialogOpen" @close="isExportDialogOpen = false" />
    </AdminLayout>
</template>
