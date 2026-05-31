<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed } from 'vue';

const props = defineProps({
    products: Object,
});

const groupedProducts = computed(() => {
    const groups = {};
    if (!props.products || !props.products.data) return groups;
    
    props.products.data.forEach(product => {
        const catName = product.category ? product.category.name : 'Tanpa Kategori';
        if (!groups[catName]) groups[catName] = [];
        groups[catName].push(product);
    });
    return groups;
});
</script>

<template>
    <Head title="Manajemen Produk" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                Daftar Produk
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-2xl">
                    <div class="p-6">
                        
                        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <p class="text-sm font-medium text-slate-500">Kelola semua produk, stok, dan gambar Anda di sini.</p>
                            <Link :href="route('admin.products.create')">
                                <PrimaryButton class="w-full sm:w-auto justify-center !bg-blue-600 hover:!bg-blue-700 !text-white !rounded-xl !shadow-lg !shadow-blue-500/30">
                                    + Tambah Produk Baru
                                </PrimaryButton>
                            </Link>
                        </div>

                        <!-- Product List -->
                        <div class="flex flex-col gap-8">
                            <div v-if="products.data.length === 0" class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center font-medium text-slate-500">
                                Belum ada produk yang ditambahkan.
                            </div>

                            <template v-else>
                                <div v-for="(groupProducts, categoryName) in groupedProducts" :key="categoryName" class="space-y-4">
                                    <!-- Category Header -->
                                    <h3 class="text-lg font-bold text-slate-900 border-b border-slate-200 pb-2 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        Kategori: {{ categoryName }}
                                    </h3>

                                    <div 
                                        v-for="product in groupProducts" 
                                        :key="product.id"
                                        class="flex flex-col sm:flex-row sm:items-center justify-between rounded-xl border border-slate-200 bg-white p-5 gap-4 shadow-sm hover:border-blue-300 transition-colors ml-0 sm:ml-4"
                                    >
                                        <div>
                                            <h4 class="font-bold text-slate-900 text-lg">{{ product.name }}</h4>
                                            <p class="text-sm font-medium text-slate-500 mt-1 space-y-0.5">
                                                <span class="block">Harga Dasar: <span class="font-bold text-slate-700">Rp {{ Number(product.base_price).toLocaleString('id-ID') }}</span></span>
                                                <span class="block mt-1">
                                                    Status: 
                                                    <span :class="product.is_active ? 'text-green-600 bg-green-50' : 'text-slate-500 bg-slate-100'" class="px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider ml-1">
                                                        {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                                                    </span>
                                                </span>
                                            </p>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <Link :href="route('admin.products.show', product.id)" class="w-full sm:w-auto text-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800 transition-colors shadow-sm">
                                                Atur Stok & Gambar
                                            </Link>
                                            <Link :href="route('admin.products.edit', product.id)" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                                Edit Info
                                            </Link>
                                            <Link :href="route('admin.products.destroy', product.id)" method="delete" as="button" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm">
                                                Hapus
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Pagination -->
                        <div v-if="products.links.length > 3" class="mt-8 flex flex-wrap gap-1">
                            <template v-for="(link, p) in products.links" :key="p">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-2 text-sm font-semibold text-slate-400 bg-slate-50 border border-slate-200 rounded-lg" v-html="link.label" />
                                <Link v-else :class="{ 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20': link.active, 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200 hover:text-blue-600': !link.active }" class="mr-1 mb-1 px-4 py-2 text-sm font-semibold border rounded-lg transition-all" :href="link.url" v-html="link.label" />
                            </template>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
