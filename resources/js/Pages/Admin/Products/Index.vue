<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    lowStockThreshold: Number,
    stats: Object,
});

const search = ref(props.filters?.q || '');
const categoryFilter = ref(props.filters?.category || '');
const statusFilter = ref(props.filters?.status || '');
const lowStockActive = computed(() => props.filters?.filter === 'low_stock');

const apply = () => {
    router.get(route('admin.products.index'), {
        q: search.value,
        category: categoryFilter.value,
        status: statusFilter.value,
        filter: props.filters?.filter || '',
    }, { preserveState: true, replace: true, preserveScroll: true });
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(apply, 350);
});
watch([categoryFilter, statusFilter], apply);

const clearLowStock = () => {
    router.get(route('admin.products.index'), {
        q: search.value,
        category: categoryFilter.value,
        status: statusFilter.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

const availableStock = (product) => (product.stock_sum || 0) - (product.reserved_sum || 0);

const variantNames = (variants, max = 2) => {
    if (!variants || variants.length === 0) return '';
    const names = variants.map((v) => v.name);
    if (names.length <= max) return names.join(', ');
    return `${names.slice(0, max).join(', ')} +${names.length - max} lainnya`;
};

const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', minimumFractionDigits: 0,
}).format(value || 0);

const confirmState = ref({ show: false, target: null });
const askDelete = (product) => { confirmState.value = { show: true, target: product }; };
const cancelDelete = () => { confirmState.value = { show: false, target: null }; };
const confirmDelete = () => {
    const product = confirmState.value.target;
    confirmState.value = { show: false, target: null };
    router.delete(route('admin.products.destroy', product.id));
};
</script>

<template>
    <Head title="Manajemen Produk" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                Daftar Produk
            </h2>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center" data-stat="total">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Total Produk</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-emerald-200 shadow-sm flex flex-col justify-center bg-emerald-50/30" data-stat="active">
                        <p class="text-sm font-semibold text-emerald-600 uppercase tracking-wide">Aktif</p>
                        <p class="text-3xl font-black text-emerald-700 mt-1">{{ stats.active }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-amber-200 shadow-sm flex flex-col justify-center bg-amber-50/30" data-stat="low_stock">
                        <p class="text-sm font-semibold text-amber-600 uppercase tracking-wide">Stok Kritis</p>
                        <p class="text-3xl font-black text-amber-700 mt-1">{{ stats.low_stock }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-red-200 shadow-sm flex flex-col justify-center bg-red-50/30" data-stat="out_of_stock">
                        <p class="text-sm font-semibold text-red-600 uppercase tracking-wide">Habis</p>
                        <p class="text-3xl font-black text-red-700 mt-1">{{ stats.out_of_stock }}</p>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">

                    <!-- Low stock filter chip -->
                    <div v-if="lowStockActive" class="px-4 py-3 border-b border-amber-200 bg-amber-50 flex items-center justify-between gap-4" data-filter-active="low_stock">
                        <p class="text-sm font-semibold text-amber-700">
                            Menampilkan produk stok kritis ({{ stats.low_stock }})
                        </p>
                        <button type="button" @click="clearLowStock" class="text-sm font-bold text-amber-700 hover:text-amber-900 underline shrink-0">
                            Tampilkan Semua
                        </button>
                    </div>

                    <!-- Filters & Create -->
                    <div class="p-4 border-b border-slate-200 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-3 w-full xl:w-auto">
                            <input
                                type="text"
                                v-model="search"
                                placeholder="Cari nama produk atau SKU..."
                                class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm w-full sm:w-64 text-sm"
                            >
                            <select v-model="categoryFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <select v-model="statusFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                        <Link
                            :href="route('admin.products.create')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors shadow-sm shrink-0"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Tambah Produk Baru
                        </Link>
                    </div>

                    <div class="overflow-visible">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Kategori</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Harga</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Stok</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="product in products.data" :key="product.id" class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center p-1">
                                                <img v-if="product.images && product.images.length > 0" :src="'/storage/' + product.images[0].image_path" class="h-full w-full object-contain" />
                                                <svg v-else class="h-5 w-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <span class="font-bold text-slate-900">{{ product.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm text-slate-600">
                                        {{ product.category?.name ?? 'Tanpa Kategori' }}
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm font-semibold text-slate-700">
                                        {{ formatRupiah(product.base_price) }}
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <p class="text-sm font-bold text-slate-900">{{ availableStock(product) }}</p>
                                        <p
                                            v-if="product.out_of_stock_variants_count > 0"
                                            class="text-xs font-bold text-red-600 mt-0.5"
                                            data-stock-warning="out_of_stock"
                                            :title="product.out_of_stock_variants.map((v) => v.name).join(', ')"
                                        >
                                            Habis: {{ variantNames(product.out_of_stock_variants) }}
                                        </p>
                                        <p
                                            v-else-if="product.low_stock_variants_count > 0"
                                            class="text-xs font-bold text-amber-600 mt-0.5"
                                            data-stock-warning="low_stock"
                                            :title="product.critical_variants.map((v) => v.name).join(', ')"
                                        >
                                            Kritis: {{ variantNames(product.critical_variants) }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span
                                            :class="product.is_active ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-slate-500 bg-slate-100 border-slate-200'"
                                            class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border"
                                        >
                                            {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-top text-right text-sm font-medium">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button type="button" class="p-1.5 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" /></svg>
                                                </button>
                                            </template>
                                            <template #content>
                                                <DropdownLink :href="route('admin.products.show', product.id)">Atur Stok &amp; Gambar</DropdownLink>
                                                <DropdownLink :href="route('admin.products.edit', product.id)">Edit</DropdownLink>
                                                <button type="button" @click="askDelete(product)" class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-600 hover:bg-red-50 transition duration-150 ease-in-out">
                                                    Hapus
                                                </button>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                                <tr v-if="products.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">
                                        Tidak ada produk yang cocok dengan filter saat ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-slate-200">
                        <Pagination :links="products.links" />
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal :show="confirmState.show" title="Hapus Produk"
            :message='`Yakin hapus produk "${confirmState.target?.name}"? Tindakan ini tidak dapat dibatalkan.`'
            @confirm="confirmDelete" @cancel="cancelDelete" />
    </AdminLayout>
</template>
