<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    promotions: Object,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters?.q || '');
const statusFilter = ref(props.filters?.status || '');
const typeFilter = ref(props.filters?.type || '');

watch([search, statusFilter, typeFilter], () => {
    router.get(route('admin.promotions.index'), {
        q: search.value,
        status: statusFilter.value,
        type: typeFilter.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
});

const confirmState = ref({ show: false, target: null });
const askDelete = (promo) => { confirmState.value = { show: true, target: promo }; };
const cancelDelete = () => { confirmState.value = { show: false, target: null }; };
const confirmDelete = () => {
    const promo = confirmState.value.target;
    confirmState.value = { show: false, target: null };
    router.delete(route('admin.promotions.destroy', promo.id));
};

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};

const formatValue = (promotion) => {
    if (promotion.type === 'percentage') return `${promotion.value}%`;
    if (promotion.type === 'free_shipping') return 'Gratis Ongkir';
    return formatRupiah(promotion.value);
};

const formatDate = (dateString) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const typeBadge = (type) => {
    switch (type) {
        case 'percentage':
            return { label: 'Persentase', class: 'bg-indigo-50 text-indigo-600 border-indigo-200' };
        case 'fixed_amount':
            return { label: 'Nominal', class: 'bg-purple-50 text-purple-600 border-purple-200' };
        case 'free_shipping':
            return { label: 'Gratis Ongkir', class: 'bg-cyan-50 text-cyan-600 border-cyan-200' };
        default:
            return { label: type, class: 'bg-slate-50 text-slate-600 border-slate-200' };
    }
};

// Display-only status derived from is_active + validity window + quota usage.
// Does NOT change `is_active` or any stored value — purely a richer label for
// admins than the raw boolean (priority: Nonaktif > Kadaluwarsa > Terjadwal > Kuota Habis > Aktif).
const promotionStatus = (promo) => {
    if (!promo.is_active) {
        return { label: 'Nonaktif', class: 'bg-slate-100 text-slate-600 border-slate-200' };
    }

    const now = new Date();

    if (promo.valid_until && new Date(promo.valid_until) < now) {
        return { label: 'Kadaluwarsa', class: 'bg-red-50 text-red-600 border-red-200' };
    }

    if (promo.valid_from && new Date(promo.valid_from) > now) {
        return { label: 'Terjadwal', class: 'bg-blue-50 text-blue-600 border-blue-200' };
    }

    if (promo.max_usage && promo.usages_count >= promo.max_usage) {
        return { label: 'Kuota Habis', class: 'bg-amber-50 text-amber-600 border-amber-200' };
    }

    return { label: 'Aktif', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
};

const usagePercent = (promo) => {
    if (!promo.max_usage) return 0;
    return Math.min(100, Math.round((promo.usages_count / promo.max_usage) * 100));
};

const copiedCode = ref(null);
const copyCode = (code) => {
    navigator.clipboard?.writeText(code);
    copiedCode.value = code;
    setTimeout(() => {
        if (copiedCode.value === code) copiedCode.value = null;
    }, 1500);
};
</script>

<template>
    <Head title="Manajemen Voucher" />

    <AdminLayout>
        <template #header>
            <h2 class="font-bold text-xl text-slate-900 leading-tight">Manajemen Voucher</h2>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" v-if="stats">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Total Voucher</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-emerald-200 shadow-sm flex flex-col justify-center bg-emerald-50/30">
                        <p class="text-sm font-semibold text-emerald-600 uppercase tracking-wide">Aktif</p>
                        <p class="text-3xl font-black text-emerald-700 mt-1">{{ stats.active }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-amber-200 shadow-sm flex flex-col justify-center bg-amber-50/30">
                        <p class="text-sm font-semibold text-amber-600 uppercase tracking-wide">Akan Berakhir &le;7 Hari</p>
                        <p class="text-3xl font-black text-amber-700 mt-1">{{ stats.expiring_soon }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-red-200 shadow-sm flex flex-col justify-center bg-red-50/30">
                        <p class="text-sm font-semibold text-red-600 uppercase tracking-wide">Kuota Habis</p>
                        <p class="text-3xl font-black text-red-700 mt-1">{{ stats.exhausted }}</p>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <!-- Filters & Create -->
                    <div class="p-4 border-b border-slate-200 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-3 w-full xl:w-auto">
                            <input
                                type="text"
                                v-model="search"
                                placeholder="Cari kode atau nama voucher..."
                                class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm w-full sm:w-64 text-sm"
                            >
                            <select v-model="statusFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                            <select v-model="typeFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
                                <option value="">Semua Tipe</option>
                                <option value="percentage">Persentase</option>
                                <option value="fixed_amount">Nominal</option>
                                <option value="free_shipping">Gratis Ongkir</option>
                            </select>
                        </div>
                        <Link
                            :href="route('admin.promotions.create')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors shadow-sm shrink-0"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Buat Voucher Baru
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Voucher</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Tipe & Nilai</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Min. Beli</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Penggunaan</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Periode</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="promo in promotions.data" :key="promo.id" class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-slate-900 tracking-wide">{{ promo.code }}</span>
                                            <button
                                                type="button"
                                                @click="copyCode(promo.code)"
                                                class="text-slate-400 hover:text-blue-600 transition-colors"
                                                title="Salin kode"
                                            >
                                                <svg v-if="copiedCode !== promo.code" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25" /></svg>
                                                <svg v-else class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                            </button>
                                        </div>
                                        <p class="text-sm text-slate-500 mt-0.5">{{ promo.name }}</p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border" :class="typeBadge(promo.type).class">
                                            {{ typeBadge(promo.type).label }}
                                        </span>
                                        <p class="text-sm font-bold text-slate-900 mt-1">{{ formatValue(promo) }}</p>
                                        <p v-if="promo.type === 'free_shipping' && promo.max_shipping_discount" class="text-xs text-slate-400">
                                            maks. {{ formatRupiah(promo.max_shipping_discount) }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm text-slate-600">
                                        {{ formatRupiah(promo.min_purchase) }}
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <p class="text-sm font-semibold text-slate-700">
                                            {{ promo.usages_count }} / {{ promo.max_usage ?? '∞' }}
                                        </p>
                                        <div v-if="promo.max_usage" class="w-28 h-1.5 bg-slate-100 rounded-full overflow-hidden mt-1.5">
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                :class="usagePercent(promo) >= 100 ? 'bg-red-400' : 'bg-blue-400'"
                                                :style="{ width: usagePercent(promo) + '%' }"
                                            ></div>
                                        </div>
                                        <p v-if="promo.max_usage_per_user" class="text-xs text-slate-400 mt-1">
                                            maks. {{ promo.max_usage_per_user }}/user
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top text-xs text-slate-500">
                                        <p v-if="promo.valid_from">Mulai {{ formatDate(promo.valid_from) }}</p>
                                        <p v-if="promo.valid_until">Sampai {{ formatDate(promo.valid_until) }}</p>
                                        <p v-if="!promo.valid_from && !promo.valid_until" class="text-slate-400 italic">Tanpa batas waktu</p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border" :class="promotionStatus(promo).class">
                                            {{ promotionStatus(promo).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-top text-right text-sm font-medium space-x-3 whitespace-nowrap">
                                        <Link :href="route('admin.promotions.edit', promo.id)" class="text-blue-600 hover:text-blue-800 font-semibold">Edit</Link>
                                        <Link :href="route('admin.promotions.history', promo.id)" class="text-slate-500 hover:text-slate-800 font-semibold">Histori</Link>
                                        <button @click="askDelete(promo)" class="text-red-600 hover:text-red-800 font-semibold">Hapus</button>
                                    </td>
                                </tr>
                                <tr v-if="promotions.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-400">
                                        Tidak ada voucher yang cocok dengan filter saat ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-slate-200">
                        <Pagination :links="promotions.links" />
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal :show="confirmState.show" title="Hapus Voucher"
            :message='`Yakin ingin menghapus voucher "${confirmState.target?.code}"? Tindakan ini tidak dapat dibatalkan.`'
            @confirm="confirmDelete" @cancel="cancelDelete" />
    </AdminLayout>
</template>
