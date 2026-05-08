<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    categories: Object,
});
</script>

<template>
    <Head title="Kategori" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900 dark:text-white">
                Kategori Produk
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Mobile-first card wrapper -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <!-- Header & Add Button -->
                        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Kelola daftar kategori untuk produk Anda.</p>
                            <Link :href="route('admin.categories.create')">
                                <PrimaryButton class="w-full sm:w-auto justify-center bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                                    + Tambah Kategori
                                </PrimaryButton>
                            </Link>
                        </div>

                        <!-- Categories List (Mobile friendly) -->
                        <div class="flex flex-col gap-4">
                            <!-- Show empty state if no categories -->
                            <div v-if="categories.data.length === 0" class="rounded border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400">
                                Belum ada kategori yang ditambahkan.
                            </div>

                            <!-- List Item -->
                            <div 
                                v-for="category in categories.data" 
                                :key="category.id"
                                class="flex flex-col sm:flex-row sm:items-center justify-between rounded-md border border-gray-200 dark:border-gray-700 p-4 gap-4"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Placeholder or Image -->
                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center">
                                        <img v-if="category.image_path" :src="'/storage/' + category.image_path" class="h-full w-full object-cover" />
                                        <svg v-else class="h-6 w-6 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    
                                    <div>
                                        <h3 class="font-bold text-gray-900 dark:text-white">{{ category.name }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <span v-if="category.parent">Sub dari: {{ category.parent.name }}</span>
                                            <span v-else>Kategori Utama</span>
                                            &bull; {{ category.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <Link :href="route('admin.categories.edit', category.id)" class="w-full sm:w-auto text-center rounded border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        Edit
                                    </Link>
                                    <Link :href="route('admin.categories.destroy', category.id)" method="delete" as="button" class="w-full sm:w-auto text-center rounded border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 hover:text-black dark:hover:bg-gray-700 dark:hover:text-white transition-colors">
                                        Hapus
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination (Simple) -->
                        <div v-if="categories.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                            <template v-for="(link, p) in categories.links" :key="p">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border border-gray-200 dark:border-gray-700 rounded" v-html="link.label" />
                                <Link v-else :class="{ 'bg-gray-900 text-white dark:bg-white dark:text-gray-900': link.active, 'bg-white text-gray-900 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700': !link.active }" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border border-gray-200 dark:border-gray-700 rounded transition-colors" :href="link.url" v-html="link.label" />
                            </template>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
