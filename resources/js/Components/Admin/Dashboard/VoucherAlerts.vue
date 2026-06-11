<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
});

const warningVouchers = computed(() => (props.metrics.vouchers_alert || []).filter(v => v.severity === 'warning'));
</script>

<template>
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
</template>
