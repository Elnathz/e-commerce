<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExportDialog from '@/Components/Admin/ExportDialog.vue';
import TodayFocus from '@/Components/Admin/Dashboard/TodayFocus.vue';
import PrioritySummary from '@/Components/Admin/Dashboard/PrioritySummary.vue';
import PriorityFeed from '@/Components/Admin/Dashboard/PriorityFeed.vue';
import FinancialMetrics from '@/Components/Admin/Dashboard/FinancialMetrics.vue';
import PaymentBreakdown from '@/Components/Admin/Dashboard/PaymentBreakdown.vue';
import VoucherAlerts from '@/Components/Admin/Dashboard/VoucherAlerts.vue';
import TopProducts from '@/Components/Admin/Dashboard/TopProducts.vue';
import DashboardHeader from '@/Components/Admin/Dashboard/DashboardHeader.vue';
import AnalyticsFilterBar from '@/Components/Admin/Dashboard/AnalyticsFilterBar.vue';
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

const lastUpdated = computed(() => props.metrics.last_updated ? new Date(props.metrics.last_updated).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second:'2-digit' }) : '');

// True when there is genuinely no transaction activity in the selected period (owner-only, since
// non-owner financial fields are always zeroed out for access control, not data absence).
const hasNoActivityInPeriod = computed(() => {
    return props.isOwner && (props.metrics.checkout_created || 0) === 0 && (props.metrics.total_orders || 0) === 0;
});

// §2.7-C drill-down: Today Focus item -> Priority Feed filtered subset
const feedFilter = ref(null);
const priorityFeedSection = ref(null);

const handleDrillDown = (item) => {
    feedFilter.value = { types: item.filter_types, severity: item.filter_severity };
    nextTick(() => {
        priorityFeedSection.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    });
};

const clearFeedFilter = () => {
    feedFilter.value = null;
};

// FASE 4.4 / B11-lite (§4.4): tab Operasional/Finansial — focus toggle, client-side
// only. Payload tetap dikirim utuh via Inertia (lihat tracker #56).
const activeTab = ref('operasional');

const tabButtonClass = (tab) => [
    'px-4 py-2.5 text-sm font-bold border-b-2 -mb-px transition-colors',
    activeTab.value === tab
        ? 'border-emerald-500 text-emerald-600'
        : 'border-transparent text-slate-400 hover:text-slate-600',
];
</script>

<template>
    <Head title="Dashboard Operasional" />

    <AdminLayout>
        <div class="py-8 bg-slate-50 min-h-screen font-sans">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- HEADER (page-wide, real-time) -->
                <DashboardHeader
                    :last-updated="lastUpdated"
                    :is-refreshing="isRefreshing"
                    v-model:is-export-dialog-open="isExportDialogOpen"
                    @refresh="refreshDashboard"
                />

                <!-- Toast Notification -->
                <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform -translate-y-4 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform -translate-y-4 opacity-0">
                    <div v-if="showToast" class="fixed top-6 right-6 z-[100] bg-slate-900 text-white px-5 py-4 rounded-2xl shadow-2xl flex items-center gap-4">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-sm">Berhasil Diperbarui</h4>
                            <p class="text-xs text-slate-400">Data telah disinkronisasi.</p>
                        </div>
                    </div>
                </transition>

                <!-- ========================================== -->
                <!-- TAB SWITCHER (§4.4 / B11-lite: focus toggle, client-side) -->
                <!-- ========================================== -->
                <div class="flex items-center gap-1 border-b border-slate-200" data-dashboard-tabs>
                    <button type="button" data-dashboard-tab="operasional" :class="tabButtonClass('operasional')" @click="activeTab = 'operasional'">
                        Operasional
                    </button>
                    <button type="button" data-dashboard-tab="finansial" :class="tabButtonClass('finansial')" @click="activeTab = 'finansial'">
                        Finansial
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- TAB PANEL: OPERASIONAL (§3 / B10: real-time, TANPA filter) -->
                <!-- ========================================== -->
                <div v-show="activeTab === 'operasional'" data-tab-panel="operasional" class="space-y-8">

                    <div class="flex items-center gap-2 -mb-2" data-operational-label>
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Real-time, tidak terpengaruh filter</span>
                    </div>

                    <!-- TODAY FOCUS SUMMARY (§2.7 — menelan SLA banner) -->
                    <TodayFocus :metrics="metrics" @drill-down="handleDrillDown" />

                    <PrioritySummary :metrics="metrics" />

                    <!-- ========================================== -->
                    <!-- MIDDLE: FEEDS & RISKS -->
                    <!-- ========================================== -->
                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 lg:gap-8">

                        <!-- LEFT COLUMN (Needs Action) -->
                        <div class="xl:col-span-7 space-y-6 lg:space-y-8">
                            <div ref="priorityFeedSection">
                                <PriorityFeed :metrics="metrics" :active-filter="feedFilter" @clear-filter="clearFeedFilter" />
                            </div>
                        </div>

                        <!-- RIGHT COLUMN (Risks) -->
                        <div class="xl:col-span-5 space-y-6 lg:space-y-8">

                            <FinancialMetrics :metrics="metrics" section="risk" />

                            <VoucherAlerts :metrics="metrics" />

                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB PANEL: FINANSIAL (§3 / B10) -->
                <!-- ========================================== -->
                <div v-show="activeTab === 'finansial'" data-tab-panel="finansial" class="space-y-8">

                    <!-- SECTION ANALITIK FILTER BAR (§3 / B10: hanya memengaruhi widget di bawah) -->
                    <AnalyticsFilterBar
                        :display-date-range="displayDateRange"
                        :selected-period="selectedPeriod"
                        v-model:show-custom-date="showCustomDate"
                        v-model:custom-start-date="customStartDate"
                        v-model:custom-end-date="customEndDate"
                        v-model:compare-period="comparePeriod"
                        @set-period="setPeriod"
                        @apply-custom-date="applyCustomDate"
                    />

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

                    <FinancialMetrics :metrics="metrics" :has-no-activity-in-period="hasNoActivityInPeriod" section="kpi" />

                    <!-- ========================================== -->
                    <!-- CHARTS & TOP PRODUCTS -->
                    <!-- ========================================== -->
                    <div v-if="!hasNoActivityInPeriod" class="grid grid-cols-1 xl:grid-cols-12 gap-6 lg:gap-8">
                        <TopProducts :metrics="metrics" v-model:chart-grouping="chartGrouping" v-model:top-products-limit="topProductsLimit" />
                    </div>

                    <!-- ========================================== -->
                    <!-- BOTTOM LOGISTICS & PAYMENTS -->
                    <!-- ========================================== -->
                    <div v-if="!hasNoActivityInPeriod" class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
                        <PaymentBreakdown :metrics="metrics" />
                    </div>

                </div>

            </div>
        </div>
        
        <ExportDialog :show="isExportDialogOpen" @close="isExportDialogOpen = false" />
    </AdminLayout>
</template>
