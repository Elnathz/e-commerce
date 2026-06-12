<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    promotion: Object,
    histories: Array,
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const actionBadge = (action) => {
    switch (action) {
        case 'created':
            return { label: 'Dibuat', class: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' };
        case 'updated':
            return { label: 'Diperbarui', class: 'bg-blue-50 text-blue-600 border-blue-200', dot: 'bg-blue-500' };
        case 'deactivated':
            return { label: 'Dinonaktifkan', class: 'bg-red-50 text-red-600 border-red-200', dot: 'bg-red-500' };
        case 'reactivated':
            return { label: 'Diaktifkan Kembali', class: 'bg-amber-50 text-amber-600 border-amber-200', dot: 'bg-amber-500' };
        default:
            return { label: action, class: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' };
    }
};

const formatChanges = (changes) => {
    if (!changes || !changes.before) return [];

    const formatted = [];
    for (const [field, oldVal] of Object.entries(changes.before)) {
        if (field === 'updated_at' || field === 'created_at') continue;
        const newVal = changes.after[field];

        formatted.push({
            field: formatFieldName(field),
            old: oldVal === null ? 'NULL' : String(oldVal),
            new: newVal === null ? 'NULL' : String(newVal),
        });
    }
    return formatted;
};

const formatFieldName = (field) => {
    const map = {
        value: 'Nilai Diskon',
        type: 'Tipe',
        min_purchase: 'Min. Beli',
        max_usage: 'Maks. Penggunaan',
        max_usage_per_user: 'Maks. per User',
        applicable_shipping_type: 'Tipe Kurir',
        max_shipping_discount: 'Maks. Diskon Ongkir',
        valid_from: 'Berlaku Mulai',
        valid_until: 'Berlaku Sampai',
        is_active: 'Status Aktif',
    };
    return map[field] || field;
};
</script>

<template>
    <Head :title="`Riwayat Voucher: ${promotion.code}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.promotions.index')" class="text-slate-400 hover:text-slate-700">
                    ← Kembali
                </Link>
                <h2 class="font-bold text-xl text-slate-900 leading-tight">
                    Riwayat Perubahan: <span class="font-mono">{{ promotion.code }}</span>
                </h2>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <div v-if="histories.length === 0" class="text-center py-12 text-sm text-slate-400">
                        Belum ada riwayat perubahan untuk voucher ini.
                    </div>

                    <div v-else class="space-y-8">
                        <div v-for="history in histories" :key="history.id" class="relative pl-6 border-l-2 border-slate-200 pb-2 last:pb-0">
                            <div class="absolute w-3 h-3 rounded-full -left-[7px] top-1.5" :class="actionBadge(history.action).dot"></div>

                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border" :class="actionBadge(history.action).class">
                                    {{ actionBadge(history.action).label }}
                                </span>
                                <span class="text-sm font-semibold text-slate-900">
                                    Oleh: {{ history.admin ? history.admin.name : 'System' }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    {{ formatDate(history.created_at) }}
                                </span>
                            </div>

                            <div v-if="history.action === 'updated' && history.changes" class="bg-slate-50 border border-slate-200 rounded-xl p-4 mt-3">
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Detail Perubahan</h4>
                                <table class="min-w-full text-sm text-left">
                                    <thead>
                                        <tr class="text-slate-500 border-b border-slate-200">
                                            <th class="py-1 font-medium w-1/3">Kolom</th>
                                            <th class="py-1 font-medium w-1/3 text-red-500">Sebelumnya</th>
                                            <th class="py-1 font-medium w-1/3 text-emerald-600">Menjadi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="(change, idx) in formatChanges(history.changes)" :key="idx">
                                            <td class="py-2 text-slate-900 font-medium">{{ change.field }}</td>
                                            <td class="py-2 text-red-600 break-words">{{ change.old }}</td>
                                            <td class="py-2 text-emerald-600 break-words font-semibold">{{ change.new }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
