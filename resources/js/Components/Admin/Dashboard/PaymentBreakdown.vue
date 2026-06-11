<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
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
</script>

<template>
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
</template>
