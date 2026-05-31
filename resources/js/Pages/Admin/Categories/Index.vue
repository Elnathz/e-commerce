<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    categories: Object,
});
</script>

<template>
    <Head title="Kategori Produk" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                Kategori Produk
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-2xl">
                    <div class="p-6">
                        
                        <!-- Header & Add Button -->
                        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <p class="text-sm font-medium text-slate-500">Kelola hierarki daftar kategori untuk produk Anda.</p>
                            <Link :href="route('admin.categories.create')">
                                <PrimaryButton class="w-full sm:w-auto justify-center !bg-blue-600 hover:!bg-blue-700 !text-white !rounded-xl !shadow-lg !shadow-blue-500/30">
                                    + Tambah Kategori
                                </PrimaryButton>
                            </Link>
                        </div>

                        <!-- Categories List -->
                        <div class="flex flex-col gap-4 relative">
                            <!-- Show empty state if no categories -->
                            <div v-if="categories.data.length === 0" class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center text-slate-500 font-medium">
                                Belum ada kategori yang ditambahkan.
                            </div>

                            <!-- Hierarchy List -->
                            <template v-for="(category, index) in categories.data" :key="category.id">
                                <!-- Parent Category -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between rounded-xl border border-slate-200 bg-white p-4 gap-4 shadow-sm hover:border-blue-300 transition-colors z-10 relative">
                                    <div class="flex items-center gap-4">
                                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center p-1">
                                            <img v-if="category.image_path" :src="'/storage/' + category.image_path" class="h-full w-full object-contain drop-shadow-sm" />
                                            <svg v-else class="h-6 w-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        
                                        <div>
                                            <h3 class="font-bold text-slate-900 text-lg">{{ category.name }}</h3>
                                            <p class="text-sm font-medium text-slate-500 mt-0.5">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-600 mr-2">Kategori Utama</span>
                                                <span :class="category.is_active ? 'text-green-600' : 'text-slate-400'">&bull; {{ category.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <Link :href="route('admin.categories.edit', category.id)" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                            Edit
                                        </Link>
                                        <Link :href="route('admin.categories.destroy', category.id)" method="delete" as="button" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm">
                                            Hapus
                                        </Link>
                                    </div>
                                </div>

                                <!-- Sub Categories -->
                                <template v-if="category.children && category.children.length > 0">
                                    <div 
                                        v-for="(child, childIndex) in category.children" 
                                        :key="child.id"
                                        class="flex flex-col sm:flex-row sm:items-center justify-between rounded-xl border border-slate-200 bg-slate-50/80 p-3 gap-4 ml-10 sm:ml-16 relative hover:bg-slate-100 transition-colors"
                                    >
                                        <!-- L-Connector -->
                                        <div class="absolute -left-6 sm:-left-8 top-1/2 -mt-[1px] w-4 sm:w-6 h-px bg-slate-300"></div>
                                        <div class="absolute -left-6 sm:-left-8 -top-6 bottom-1/2 w-px bg-slate-300" :class="{'-top-12': childIndex > 0}"></div>

                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-md bg-white border border-slate-200 flex items-center justify-center p-1">
                                                <img v-if="child.image_path" :src="'/storage/' + child.image_path" class="h-full w-full object-contain" />
                                                <svg v-else class="h-5 w-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-800 text-base">↳ {{ child.name }}</h4>
                                                <p class="text-xs font-medium text-slate-500">
                                                    <span :class="child.is_active ? 'text-green-600' : 'text-slate-400'">{{ child.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <Link :href="route('admin.categories.edit', child.id)" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-blue-600 hover:border-blue-200 transition-colors shadow-sm">
                                                Edit
                                            </Link>
                                            <Link :href="route('admin.categories.destroy', child.id)" method="delete" as="button" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm">
                                                Hapus
                                            </Link>
                                        </div>
                                    </div>
                                </template>
                            </template>
                        </div>

                        <!-- Pagination -->
                        <div v-if="categories.links.length > 3" class="mt-8 flex flex-wrap gap-1">
                            <template v-for="(link, p) in categories.links" :key="p">
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
