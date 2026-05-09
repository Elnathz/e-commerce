<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    products: Object,
    filters: Object,
    availableCategories: Array,
    availableColors: Array,
    totalResults: Number,
});

// Local filter state
const searchQuery = ref(props.filters?.q || '');
const inStock = ref(props.filters?.in_stock === '1' || props.filters?.in_stock === true);
const priceMin = ref(props.filters?.price_min || '');
const priceMax = ref(props.filters?.price_max || '');
const selectedCategories = ref(props.filters?.categories ? [...props.filters.categories] : []);
const selectedColors = ref(props.filters?.colors ? [...props.filters.colors] : []);
const sortBy = ref(props.filters?.sort || 'best_match');
const showMobileFilter = ref(false);

// Keep local state in sync with URL props
watch(() => props.filters, (newFilters) => {
    searchQuery.value = newFilters?.q || '';
    inStock.value = newFilters?.in_stock === '1' || newFilters?.in_stock === true;
    priceMin.value = newFilters?.price_min || '';
    priceMax.value = newFilters?.price_max || '';
    selectedCategories.value = newFilters?.categories ? [...newFilters.categories] : [];
    selectedColors.value = newFilters?.colors ? [...newFilters.colors] : [];
    if (newFilters?.sort) sortBy.value = newFilters.sort;
}, { deep: true });

const sortOptions = [
    { value: 'best_match', label: 'Best Match' },
    { value: 'recommended', label: 'Rekomendasi' },
    { value: 'az', label: 'Alphabet A-Z' },
    { value: 'za', label: 'Alphabet Z-A' },
    { value: 'price_low', label: 'Harga Terendah' },
    { value: 'price_high', label: 'Harga Tertinggi' },
    { value: 'newest', label: 'Terbaru' },
    { value: 'oldest', label: 'Paling Lama' },
];

// Build params and navigate
const applyFilters = () => {
    const params = {};
    if (searchQuery.value) params.q = searchQuery.value;
    if (inStock.value) params.in_stock = '1';
    if (priceMin.value) params.price_min = priceMin.value;
    if (priceMax.value) params.price_max = priceMax.value;
    if (selectedCategories.value.length) params.categories = selectedCategories.value;
    if (selectedColors.value.length) params.colors = selectedColors.value;
    if (sortBy.value !== 'best_match') params.sort = sortBy.value;

    router.get('/search', params, { preserveState: true, preserveScroll: false });
};

// Auto-apply on sort change
watch(sortBy, () => applyFilters());

// Toggle checkbox helpers
const toggleCategory = (id, apply = true) => {
    const strId = String(id);
    const idx = selectedCategories.value.indexOf(strId);
    if (idx > -1) selectedCategories.value.splice(idx, 1);
    else selectedCategories.value.push(strId);
    if (apply) applyFilters();
};

const toggleColor = (color, apply = true) => {
    const idx = selectedColors.value.indexOf(color);
    if (idx > -1) selectedColors.value.splice(idx, 1);
    else selectedColors.value.push(color);
    if (apply) applyFilters();
};

const toggleStock = (apply = true) => {
    inStock.value = !inStock.value;
    if (apply) applyFilters();
};

const isCategorySelected = (id) => selectedCategories.value.includes(String(id));
const isColorSelected = (color) => selectedColors.value.includes(color);

const clearAllFilters = () => {
    searchQuery.value = props.filters?.q || '';
    inStock.value = false;
    priceMin.value = '';
    priceMax.value = '';
    selectedCategories.value = [];
    selectedColors.value = [];
    sortBy.value = 'best_match';
    applyFilters();
};
</script>

<template>
    <Head :title="filters?.q ? `Hasil untuk '${filters.q}'` : 'Pencarian'" />

    <StorefrontLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

            <!-- Search Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-3">
                <div>
                    <h1 class="text-lg md:text-xl font-bold text-gray-800">
                        <template v-if="filters?.q">
                            Hasil pencarian untuk "<span class="text-blue-600">{{ filters.q }}</span>"
                        </template>
                        <template v-else>Semua Produk</template>
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ totalResults }} produk ditemukan</p>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Mobile filter toggle -->
                    <button
                        @click="showMobileFilter = true"
                        class="md:hidden flex items-center gap-1.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                        Filter
                    </button>

                    <!-- Sort dropdown -->
                    <select
                        v-model="sortBy"
                        class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
            </div>

            <!-- Main Content: Sidebar + Grid -->
            <div class="flex gap-6">

                <!-- Filter Sidebar (Desktop) -->
                <aside class="hidden md:block w-64 shrink-0">
                    <div class="sticky top-24 space-y-6">

                        <!-- Clear filters -->
                        <button
                            @click="clearAllFilters"
                            class="text-xs text-blue-600 hover:underline font-medium"
                        >Reset semua filter</button>

                        <!-- Stock -->
                        <div class="border-b pb-4">
                            <h4 class="text-sm font-bold text-gray-800 mb-3">Ketersediaan</h4>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 hover:text-gray-800">
                                <input type="checkbox" :checked="inStock" @change="toggleStock" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                Hanya yang tersedia
                            </label>
                        </div>

                        <!-- Price Range -->
                        <div class="border-b pb-4">
                            <h4 class="text-sm font-bold text-gray-800 mb-3">Rentang Harga</h4>
                            <div class="flex items-center gap-2">
                                <input v-model="priceMin" type="number" placeholder="Min" class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-blue-500" />
                                <span class="text-gray-400 text-xs">—</span>
                                <input v-model="priceMax" type="number" placeholder="Max" class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <button @click="applyFilters" class="mt-2 w-full text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg py-1.5 transition-colors">Terapkan</button>
                        </div>

                        <!-- Category -->
                        <div class="border-b pb-4">
                            <h4 class="text-sm font-bold text-gray-800 mb-3">Kategori</h4>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                <label v-for="cat in availableCategories" :key="cat.id" class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 hover:text-gray-800">
                                    <input type="checkbox" :checked="isCategorySelected(cat.id)" @change="toggleCategory(cat.id)" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    {{ cat.name }}
                                </label>
                            </div>
                        </div>

                        <!-- Colors -->
                        <div v-if="availableColors.length > 0">
                            <h4 class="text-sm font-bold text-gray-800 mb-3">Warna</h4>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                <label v-for="color in availableColors" :key="color" class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 hover:text-gray-800">
                                    <input type="checkbox" :checked="isColorSelected(color)" @change="toggleColor(color)" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    {{ color }}
                                </label>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Mobile Filter Drawer -->
                <Teleport to="body">
                    <Transition name="fade">
                        <div v-if="showMobileFilter" class="fixed inset-0 bg-black/40 z-50 md:hidden" @click="showMobileFilter = false"></div>
                    </Transition>
                    <Transition name="slide-up">
                        <div v-if="showMobileFilter" class="fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl z-50 md:hidden max-h-[80vh] overflow-y-auto shadow-2xl">
                            <div class="p-5 space-y-5">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-bold text-gray-800">Filter</h3>
                                    <button @click="showMobileFilter = false" class="text-gray-400 hover:text-gray-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <!-- Stock -->
                                <div class="border-b pb-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Ketersediaan</h4>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                                        <input type="checkbox" :checked="inStock" @change="toggleStock(false)" class="rounded border-gray-300 text-blue-600" />
                                        Hanya yang tersedia
                                    </label>
                                </div>

                                <!-- Price -->
                                <div class="border-b pb-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Rentang Harga</h4>
                                    <div class="flex items-center gap-2">
                                        <input v-model="priceMin" type="number" placeholder="Min" class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm" />
                                        <span class="text-gray-400 text-xs">—</span>
                                        <input v-model="priceMax" type="number" placeholder="Max" class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm" />
                                    </div>
                                </div>

                                <!-- Category -->
                                <div class="border-b pb-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Kategori</h4>
                                    <div class="space-y-2">
                                        <label v-for="cat in availableCategories" :key="cat.id" class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                                            <input type="checkbox" :checked="isCategorySelected(cat.id)" @change="toggleCategory(cat.id, false)" class="rounded border-gray-300 text-blue-600" />
                                            {{ cat.name }}
                                        </label>
                                    </div>
                                </div>

                                <!-- Colors -->
                                <div v-if="availableColors.length > 0" class="pb-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Warna</h4>
                                    <div class="space-y-2">
                                        <label v-for="color in availableColors" :key="color" class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                                            <input type="checkbox" :checked="isColorSelected(color)" @change="toggleColor(color, false)" class="rounded border-gray-300 text-blue-600" />
                                            {{ color }}
                                        </label>
                                    </div>
                                </div>

                                <button @click="applyFilters(); showMobileFilter = false" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors">
                                    Terapkan Filter
                                </button>
                            </div>
                        </div>
                    </Transition>
                </Teleport>

                <!-- Product Grid -->
                <div class="flex-1 min-w-0">
                    <div v-if="products.data && products.data.length > 0">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <ProductCard
                                v-for="product in products.data"
                                :key="product.id"
                                :product="product"
                            />
                        </div>

                        <!-- Pagination -->
                        <div v-if="products.last_page > 1" class="mt-8 flex justify-center">
                            <nav class="flex items-center gap-1">
                                <template v-for="link in products.links" :key="link.label">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        class="px-3 py-1.5 text-sm rounded-lg transition-colors"
                                        :class="link.active ? 'bg-blue-600 text-white font-bold' : 'text-gray-600 hover:bg-gray-100'"
                                        v-html="link.label"
                                        preserve-scroll
                                    />
                                    <span v-else class="px-3 py-1.5 text-sm text-gray-300" v-html="link.label" />
                                </template>
                            </nav>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-20">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-16 h-16 mx-auto text-gray-300 mb-4"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                        <h3 class="text-lg font-semibold text-gray-600 mb-1">Tidak ada produk ditemukan</h3>
                        <p class="text-sm text-gray-400">Coba ubah kata kunci atau filter pencarian Anda.</p>
                        <Link href="/" class="inline-block mt-4 text-sm font-medium text-blue-600 hover:underline">← Kembali ke Beranda</Link>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
