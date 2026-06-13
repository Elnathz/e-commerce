<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    categories: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.q || '');

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.categories.index'), {
            q: search.value,
        }, { preserveState: true, replace: true, preserveScroll: true });
    }, 350);
});

const deleteCategory = (cat) => {
    if (confirm(`Yakin hapus kategori "${cat.name}"? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(route('admin.categories.destroy', cat.id));
    }
};
</script>

<template>
    <Head title="Kategori Produk" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                Kategori Produk
            </h2>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Row -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" v-if="stats">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center" data-stat="total">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Total Kategori</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-emerald-200 shadow-sm flex flex-col justify-center bg-emerald-50/30" data-stat="active">
                        <p class="text-sm font-semibold text-emerald-600 uppercase tracking-wide">Aktif</p>
                        <p class="text-3xl font-black text-emerald-700 mt-1">{{ stats.active }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center" data-stat="subcategories">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Sub-kategori</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.subcategories }}</p>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <!-- Filters & Create -->
                    <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <input
                            type="text"
                            v-model="search"
                            placeholder="Cari nama kategori..."
                            class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm w-full sm:w-64 text-sm"
                        >
                        <Link
                            :href="route('admin.categories.create')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors shadow-sm shrink-0"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Tambah Kategori
                        </Link>
                    </div>

                    <div class="p-4 flex flex-col gap-3 relative">
                        <div v-if="categories.data.length === 0" class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center text-slate-500 font-medium">
                            Tidak ada kategori yang cocok dengan filter saat ini.
                        </div>

                        <!-- Flat search results -->
                        <template v-if="filters.q">
                            <div v-for="category in categories.data" :key="category.id"
                                class="flex flex-col sm:flex-row sm:items-center justify-between rounded-xl border border-slate-200 bg-white p-4 gap-4 shadow-sm hover:border-blue-300 transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center p-1">
                                        <img v-if="category.image_path" :src="'/storage/' + category.image_path" class="h-full w-full object-contain" />
                                        <svg v-else class="h-5 w-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900">{{ category.name }}</h3>
                                        <p class="text-xs font-medium text-slate-500 mt-0.5 flex items-center gap-2">
                                            <span :class="category.is_active ? 'text-emerald-600' : 'text-slate-400'">{{ category.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                            <span>&bull;</span>
                                            <span>{{ category.products_count || 0 }} produk</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Link :href="route('admin.categories.edit', category.id)" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                        Edit
                                    </Link>
                                    <button
                                        v-if="(category.products_count || 0) > 0 || (category.children_count || 0) > 0"
                                        type="button" disabled
                                        :title="`Tidak dapat dihapus karena masih memiliki ${category.products_count || 0} produk / ${category.children_count || 0} subkategori.`"
                                        class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-400 cursor-not-allowed opacity-50"
                                        data-delete-disabled
                                    >Hapus</button>
                                    <button
                                        v-else type="button" @click="deleteCategory(category)" data-delete-enabled
                                        class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm"
                                    >Hapus</button>
                                </div>
                            </div>
                        </template>

                        <!-- Hierarchy view -->
                        <template v-else>
                            <template v-for="category in categories.data" :key="category.id">
                                <!-- Parent Category -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between rounded-xl border border-slate-200 bg-white p-4 gap-4 shadow-sm hover:border-blue-300 transition-colors z-10 relative">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center p-1">
                                            <img v-if="category.image_path" :src="'/storage/' + category.image_path" class="h-full w-full object-contain" />
                                            <svg v-else class="h-5 w-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-slate-900">{{ category.name }}</h3>
                                            <p class="text-xs font-medium text-slate-500 mt-0.5 flex items-center gap-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-600">Kategori Utama</span>
                                                <span :class="category.is_active ? 'text-emerald-600' : 'text-slate-400'">&bull; {{ category.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                <span>&bull; {{ category.products_count || 0 }} produk</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <Link :href="route('admin.categories.edit', category.id)" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                            Edit
                                        </Link>
                                        <button
                                            v-if="(category.products_count || 0) > 0 || (category.children_count || 0) > 0"
                                            type="button" disabled
                                            :title="`Tidak dapat dihapus karena masih memiliki ${category.products_count || 0} produk / ${category.children_count || 0} subkategori.`"
                                            class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-400 cursor-not-allowed opacity-50"
                                            data-delete-disabled
                                        >Hapus</button>
                                        <button
                                            v-else type="button" @click="deleteCategory(category)" data-delete-enabled
                                            class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm"
                                        >Hapus</button>
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
                                                <h4 class="font-bold text-slate-800 text-sm">↳ {{ child.name }}</h4>
                                                <p class="text-xs font-medium text-slate-500 flex items-center gap-2">
                                                    <span :class="child.is_active ? 'text-emerald-600' : 'text-slate-400'">{{ child.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                    <span>&bull; {{ child.products_count || 0 }} produk</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <Link :href="route('admin.categories.edit', child.id)" class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-blue-600 hover:border-blue-200 transition-colors shadow-sm">
                                                Edit
                                            </Link>
                                            <button
                                                v-if="(child.products_count || 0) > 0"
                                                type="button" disabled
                                                :title="`Tidak dapat dihapus karena masih memiliki ${child.products_count || 0} produk.`"
                                                class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-400 cursor-not-allowed opacity-50"
                                                data-delete-disabled
                                            >Hapus</button>
                                            <button
                                                v-else type="button" @click="deleteCategory(child)" data-delete-enabled
                                                class="w-full sm:w-auto text-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm"
                                            >Hapus</button>
                                        </div>
                                    </div>
                                </template>
                            </template>
                        </template>
                    </div>

                    <div class="px-4 py-3 border-t border-slate-200">
                        <Pagination :links="categories.links" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
