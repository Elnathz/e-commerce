<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    returns: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.q || '');
const statusFilter = ref(props.filters?.status || '');

const apply = () => {
    router.get(route('admin.returns.index'), {
        q: search.value,
        status: statusFilter.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(apply, 350);
});
watch(statusFilter, apply);

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
    if (status === 'submitted') return 'bg-amber-50 text-amber-700 border-amber-200';
    if (status === 'approved' || status === 'waiting_customer_shipment') return 'bg-blue-50 text-blue-700 border-blue-200';
    if (status === 'customer_shipped' || status === 'received') return 'bg-indigo-50 text-indigo-700 border-indigo-200';
    if (status === 'inspected') return 'bg-purple-50 text-purple-700 border-purple-200';
    if (status === 'refund_processed' || status === 'completed') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (status === 'rejected' || status === 'cancelled' || status === 'expires') return 'bg-red-50 text-red-700 border-red-200';
    return 'bg-slate-100 text-slate-600 border-slate-200';
};
</script>

<template>
    <Head title="Kelola Retur & Komplain" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">Kelola Retur &amp; Komplain</h2>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Row: "Perlu Tindakan Sekarang", urut severity -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" v-if="stats">
                    <div class="bg-white p-5 rounded-2xl border border-red-200 shadow-sm flex flex-col justify-center bg-red-50/30" data-stat="awaiting_refund">
                        <p class="text-sm font-semibold text-red-600 uppercase tracking-wide">Perlu Refund</p>
                        <p class="text-3xl font-black text-red-700 mt-1">{{ stats.awaiting_refund }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-amber-200 shadow-sm flex flex-col justify-center bg-amber-50/30" data-stat="awaiting_inspection">
                        <p class="text-sm font-semibold text-amber-600 uppercase tracking-wide">Perlu Inspeksi</p>
                        <p class="text-3xl font-black text-amber-700 mt-1">{{ stats.awaiting_inspection }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center" data-stat="awaiting_approval">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Menunggu Persetujuan</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.awaiting_approval }}</p>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <!-- Filters -->
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center gap-3">
                        <input
                            type="text"
                            v-model="search"
                            placeholder="Cari no. retur, no. pesanan, atau nama pelanggan..."
                            class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm w-full sm:w-80 text-sm"
                        >
                        <select v-model="statusFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
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
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">No. Retur</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Pesanan</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Pelanggan</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Inspeksi</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="ret in returns.data" :key="ret.id" class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-4 align-top">
                                        <div class="font-bold text-slate-900">{{ ret.return_number }}</div>
                                        <div class="text-xs text-slate-400">{{ new Date(ret.created_at).toLocaleDateString('id-ID') }}</div>
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm text-slate-600">
                                        {{ ret.order.order_number }}
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm text-slate-600">
                                        {{ ret.user.name }}
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm">
                                        <span v-if="ret.inspection_result === 'passed'" class="font-bold text-emerald-600">Lolos</span>
                                        <span v-else-if="ret.inspection_result === 'failed'" class="font-bold text-red-600">Gagal</span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span :class="['inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border', getStatusClass(ret.status)]">
                                            {{ getStatusDisplay(ret.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-top text-right text-sm font-medium">
                                        <Link :href="route('admin.returns.show', ret.id)" class="text-blue-600 hover:text-blue-800 font-semibold">
                                            Detail &amp; Proses
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="returns.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">
                                        Tidak ada data retur yang cocok dengan filter saat ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-slate-200">
                        <Pagination :links="returns.links" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
