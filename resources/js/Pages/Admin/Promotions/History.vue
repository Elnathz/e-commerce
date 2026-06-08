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

const getActionBadge = (action) => {
    switch (action) {
        case 'created':
            return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Dibuat</span>';
        case 'updated':
            return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Diperbarui</span>';
        case 'deactivated':
            return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Dinonaktifkan</span>';
        case 'reactivated':
            return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Diaktifkan Kembali</span>';
        default:
            return `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">${action}</span>`;
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
                <Link :href="route('admin.promotions.index')" class="text-gray-500 hover:text-gray-700">
                    ← Kembali
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Riwayat Perubahan: {{ promotion.code }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6">
                        <div v-if="histories.length === 0" class="text-center py-8 text-gray-500">
                            Belum ada riwayat perubahan untuk voucher ini.
                        </div>

                        <div v-else class="space-y-8">
                            <div v-for="history in histories" :key="history.id" class="relative pl-6 border-l-2 border-indigo-200 dark:border-indigo-900 pb-2">
                                <div class="absolute w-3 h-3 bg-indigo-500 rounded-full -left-[7px] top-2"></div>
                                
                                <div class="flex items-center gap-3 mb-2">
                                    <span v-html="getActionBadge(history.action)"></span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        Oleh: {{ history.admin ? history.admin.name : 'System' }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ formatDate(history.created_at) }}
                                    </span>
                                </div>

                                <div v-if="history.action === 'updated' && history.changes" class="bg-gray-50 dark:bg-gray-900 rounded-md p-4 mt-3">
                                    <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Detail Perubahan:</h4>
                                    <table class="min-w-full text-sm text-left">
                                        <thead>
                                            <tr class="text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">
                                                <th class="py-1 font-medium w-1/3">Kolom</th>
                                                <th class="py-1 font-medium w-1/3 text-red-500">Sebelumnya</th>
                                                <th class="py-1 font-medium w-1/3 text-green-500">Menjadi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                            <tr v-for="(change, idx) in formatChanges(history.changes)" :key="idx">
                                                <td class="py-2 text-gray-900 dark:text-gray-300 font-medium">{{ change.field }}</td>
                                                <td class="py-2 text-red-600 dark:text-red-400 break-words">{{ change.old }}</td>
                                                <td class="py-2 text-green-600 dark:text-green-400 break-words font-semibold">{{ change.new }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
