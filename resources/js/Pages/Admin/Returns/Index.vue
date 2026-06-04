<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    returns: Object,
    filters: Object,
});

const statusFilter = ref(props.filters.status || '');

watch(statusFilter, () => {
    router.get(route('admin.returns.index'), {
        status: statusFilter.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
});

const getStatusDisplay = (status) => {
    const displays = {
        'submitted': 'Menunggu Persetujuan',
        'approved': 'Disetujui',
        'waiting_customer_shipment': 'Menunggu Pengiriman Pembeli',
        'customer_shipped': 'Dikirim Pembeli',
        'received': 'Diterima Admin',
        'inspected': 'Diinspeksi',
        'refund_processed': 'Refund Selesai',
        'completed': 'Selesai',
        'rejected': 'Ditolak',
        'cancelled': 'Dibatalkan',
        'expires': 'Kedaluwarsa'
    };
    return displays[status] || status;
};

const getStatusClass = (status) => {
    if (status === 'submitted') return 'bg-yellow-100 text-yellow-800';
    if (status === 'approved' || status === 'waiting_customer_shipment') return 'bg-blue-100 text-blue-800';
    if (status === 'customer_shipped' || status === 'received') return 'bg-indigo-100 text-indigo-800';
    if (status === 'inspected') return 'bg-purple-100 text-purple-800';
    if (status === 'refund_processed' || status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'rejected' || status === 'cancelled' || status === 'expires') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head title="Kelola Retur & Komplain" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kelola Retur & Komplain</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 border-b border-gray-200">
                        <select v-model="statusFilter" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-64">
                            <option value="">Semua Status</option>
                            <option value="submitted">Menunggu Persetujuan</option>
                            <option value="approved">Disetujui</option>
                            <option value="waiting_customer_shipment">Menunggu Pengiriman Pembeli</option>
                            <option value="customer_shipped">Barang Dikirim Pembeli</option>
                            <option value="received">Barang Diterima Admin</option>
                            <option value="inspected">Diinspeksi</option>
                            <option value="refund_processed">Refund Selesai</option>
                            <option value="completed">Selesai</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Retur</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pesanan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inspeksi</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="ret in returns.data" :key="ret.id" :class="{'bg-yellow-50': ret.status === 'submitted', 'bg-indigo-50': ret.status === 'returned'}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900">{{ ret.return_number }}</div>
                                        <div class="text-xs text-gray-500">{{ new Date(ret.created_at).toLocaleDateString('id-ID') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ ret.order.order_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ ret.user.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span v-if="ret.inspection_result === 'passed'" class="text-green-600 font-bold">Lolos</span>
                                        <span v-else-if="ret.inspection_result === 'failed'" class="text-red-600 font-bold">Gagal</span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', getStatusClass(ret.status)]">
                                            {{ getStatusDisplay(ret.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link :href="route('admin.returns.show', ret.id)" class="text-indigo-600 hover:text-indigo-900 font-bold">
                                            Detail & Proses
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="returns.data.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada data retur ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="returns.links && returns.links.length > 3" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex flex-wrap -mb-1">
                            <template v-for="(link, key) in returns.links" :key="key">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                                <Link v-else :href="link.url" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" :class="{ 'bg-indigo-50 text-indigo-600 border-indigo-200': link.active }" v-html="link.label" preserve-scroll />
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>
