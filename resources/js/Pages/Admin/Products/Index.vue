<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    products: Object,
});
</script>

<template>
    <Head title="Produk" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900 dark:text-white">
                Daftar Produk
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Kelola semua produk, stok, dan gambar Anda di sini.</p>
                            <Link :href="route('admin.products.create')">
                                <PrimaryButton class="w-full sm:w-auto justify-center bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                                    + Tambah Produk Baru
                                </PrimaryButton>
                            </Link>
                        </div>

                        <!-- Product List -->
                        <div class="flex flex-col gap-4">
                            <div v-if="products.data.length === 0" class="rounded border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                                Belum ada produk yang ditambahkan.
                            </div>

                            <div 
                                v-for="product in products.data" 
                                :key="product.id"
                                class="flex flex-col sm:flex-row sm:items-center justify-between rounded-md border border-gray-200 dark:border-gray-700 p-4 gap-4"
                            >
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-lg">{{ product.name }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Kategori: <span class="font-semibold">{{ product.category ? product.category.name : '-' }}</span>
                                        <br/>
                                        Harga Dasar: Rp {{ Number(product.base_price).toLocaleString('id-ID') }}
                                        <br/>
                                        Status: {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </p>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-2">
                                    <Link :href="route('admin.products.show', product.id)" class="w-full sm:w-auto text-center rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white transition-colors">
                                        Atur Stok & Gambar
                                    </Link>
                                    <Link :href="route('admin.products.edit', product.id)" class="w-full sm:w-auto text-center rounded border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        Edit Info
                                    </Link>
                                    <Link :href="route('admin.products.destroy', product.id)" method="delete" as="button" class="w-full sm:w-auto text-center rounded border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 hover:text-black dark:hover:bg-gray-700 dark:hover:text-white transition-colors">
                                        Hapus
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="products.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                            <template v-for="(link, p) in products.links" :key="p">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border border-gray-200 dark:border-gray-700 rounded" v-html="link.label" />
                                <Link v-else :class="{ 'bg-gray-900 text-white dark:bg-white dark:text-gray-900': link.active, 'bg-white text-gray-900 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700': !link.active }" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border border-gray-200 dark:border-gray-700 rounded transition-colors" :href="link.url" v-html="link.label" />
                            </template>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
