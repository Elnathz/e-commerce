<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { ref } from 'vue';

const props = defineProps({
    sales: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, active: 0 }) },
});

const statusBadge = (status) => ({
    aktif: 'bg-emerald-50 text-emerald-700',
    terjadwal: 'bg-blue-50 text-blue-700',
    selesai: 'bg-slate-100 text-slate-500',
    nonaktif: 'bg-red-50 text-red-600',
}[status] ?? 'bg-slate-100 text-slate-500');

const statusLabel = (status) => ({
    aktif: 'Aktif',
    terjadwal: 'Terjadwal',
    selesai: 'Selesai',
    nonaktif: 'Nonaktif',
}[status] ?? status);

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

const confirmState = ref({ show: false, target: null });
const askDelete = (sale) => { confirmState.value = { show: true, target: sale }; };
const cancelDelete = () => { confirmState.value = { show: false, target: null }; };
const confirmDelete = () => {
    const sale = confirmState.value.target;
    confirmState.value = { show: false, target: null };
    router.delete(route('admin.flash-sales.destroy', sale.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Flash Sale" />
    <AdminLayout>
        <div class="py-6 bg-slate-50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Row -->
                <div class="grid grid-cols-2 sm:grid-cols-2 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Total Flash Sale</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-emerald-200 shadow-sm flex flex-col justify-center bg-emerald-50/30">
                        <p class="text-sm font-semibold text-emerald-600 uppercase tracking-wide">Aktif Sekarang</p>
                        <p class="text-3xl font-black text-emerald-700 mt-1">{{ stats.active }}</p>
                    </div>
                </div>

                <!-- Header + Add Button -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-slate-800">Flash Sale</h1>
                            <p class="text-sm text-slate-500 mt-0.5">{{ stats.active }} aktif dari {{ stats.total }} flash sale</p>
                        </div>
                        <Link :href="route('admin.flash-sales.create')"
                              class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors shadow-sm shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Tambah Flash Sale
                        </Link>
                    </div>

                    <div class="p-4">
                        <div v-if="sales.length" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-400 border-b border-slate-200">
                                        <th class="py-2 pr-4">Nama</th>
                                        <th class="py-2 pr-4">Periode</th>
                                        <th class="py-2 pr-4">Status</th>
                                        <th class="py-2 pr-4">Item</th>
                                        <th class="py-2 pr-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="sale in sales" :key="sale.id" class="border-b border-slate-100 hover:bg-slate-50">
                                        <td class="py-3 pr-4 font-semibold text-slate-800">{{ sale.name }}</td>
                                        <td class="py-3 pr-4 text-slate-500">
                                            <div>{{ formatDate(sale.starts_at) }}</div>
                                            <div class="text-xs text-slate-400">s/d {{ formatDate(sale.ends_at) }}</div>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold" :class="statusBadge(sale.status)">
                                                {{ statusLabel(sale.status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-500">{{ sale.items_count }} produk</td>
                                        <td class="py-3 pr-4 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <Link :href="route('admin.flash-sales.edit', sale.id)"
                                                      class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                                    Edit
                                                </Link>
                                                <button @click="askDelete(sale)"
                                                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm cursor-pointer">
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-500">
                            Belum ada Flash Sale. Klik "Tambah Flash Sale" untuk membuat yang pertama.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <ConfirmModal :show="confirmState.show" title="Hapus Flash Sale"
            :message='`Hapus Flash Sale "${confirmState.target?.name}"? Jika ada pesanan yang sudah memakai harga flash, sale akan diakhiri (bukan dihapus) untuk menjaga riwayat pesanan.`'
            @confirm="confirmDelete" @cancel="cancelDelete" />
    </AdminLayout>
</template>
