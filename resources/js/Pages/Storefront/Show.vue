<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';

const props = defineProps({
    product: Object,
    relatedProducts: Array,
});

// ─── Helpers ───
const formatPrice = (price) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);

// ─── Gallery State ───
// Foto Umum: gambar produk yang TIDAK terikat ke varian manapun
const generalImages = computed(() => {
    if (!props.product.images) return [];
    return props.product.images
        .filter(img => !img.product_variant_id)
        .map(img => ({ src: '/storage/' + img.image_path, type: 'general' }));
});

// Foto Varian: gambar yang terikat ke varian tertentu
const getVariantImages = (variant) => {
    if (!variant) return [];
    // Pertama cek dari relasi variant.images (eager loaded)
    if (variant.images && variant.images.length > 0) {
        return variant.images.map(img => ({ src: '/storage/' + img.image_path, type: 'variant', variantId: variant.id }));
    }
    // Fallback: cari di product.images yang punya product_variant_id matching
    if (props.product.images) {
        return props.product.images
            .filter(img => img.product_variant_id === variant.id)
            .map(img => ({ src: '/storage/' + img.image_path, type: 'variant', variantId: variant.id }));
    }
    return [];
};

// Display Images: Foto Umum + Foto Varian Terpilih
const displayImages = computed(() => {
    const imgs = [...generalImages.value];
    if (selectedVariant.value) {
        imgs.push(...getVariantImages(selectedVariant.value));
    }
    return imgs;
});

const activeImageIndex = ref(0);
const activeImage = computed(() => displayImages.value[activeImageIndex.value]?.src || '');

// ─── Variant Selection (Opsi A: Default = termurah, sudah diurutkan oleh backend asc) ───
const selectedVariant = ref(
    props.product.variants && props.product.variants.length > 0
        ? props.product.variants[0]
        : null
);

const selectVariant = (variant) => {
    if (variant.stock <= 0) return; // Disabled jika stok habis
    selectedVariant.value = variant;
};

// Saat varian berubah, pindahkan galeri ke foto varian pertama (setelah foto umum)
watch(selectedVariant, () => {
    // Scroll ke foto varian pertama (index setelah foto umum)
    const firstVariantIdx = generalImages.value.length;
    if (displayImages.value.length > firstVariantIdx) {
        activeImageIndex.value = firstVariantIdx;
    } else {
        activeImageIndex.value = 0;
    }
});

// ─── Computed Harga Dinamis ───
const currentPrice = computed(() =>
    selectedVariant.value ? parseFloat(selectedVariant.value.price) : parseFloat(props.product.base_price)
);

const hasDiscount = computed(() =>
    parseFloat(props.product.base_price) > currentPrice.value
);

const discountPercent = computed(() => {
    if (!hasDiscount.value) return 0;
    const base = parseFloat(props.product.base_price);
    return Math.round(((base - currentPrice.value) / base) * 100);
});

const currentStock = computed(() =>
    selectedVariant.value ? selectedVariant.value.stock - (selectedVariant.value.reserved_stock || 0) : 0
);

// ─── Quantity ───
const quantity = ref(1);
const incrementQty = () => {
    if (quantity.value < currentStock.value) quantity.value++;
};
const decrementQty = () => {
    if (quantity.value > 1) quantity.value--;
};

// Reset quantity when variant changes
watch(selectedVariant, () => {
    quantity.value = 1;
});

// ─── Variant Grouping (by variant_type) ───
const variantGroups = computed(() => {
    const groups = {};
    if (!props.product.variants) return groups;
    props.product.variants.forEach(v => {
        const type = v.variant_type || 'Pilihan';
        if (!groups[type]) groups[type] = [];
        groups[type].push(v);
    });
    return groups;
});

// ─── Breadcrumb ───
const breadcrumbs = computed(() => {
    const crumbs = [{ name: 'Beranda', href: '/' }];
    const cat = props.product.category;
    if (cat) {
        if (cat.parent) {
            crumbs.push({ name: cat.parent.name, href: null });
        }
        crumbs.push({ name: cat.name, href: `/search?categories[]=${cat.id}` });
    }
    crumbs.push({ name: props.product.name, href: null });
    return crumbs;
});

// ─── Mobile Gallery Swipe ───
const galleryRef = ref(null);
const scrollToImage = (idx) => {
    activeImageIndex.value = idx;
    if (galleryRef.value) {
        const child = galleryRef.value.children[idx];
        if (child) child.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
};

// Handle scroll events to sync active dot indicator
const onGalleryScroll = () => {
    if (!galleryRef.value) return;
    const container = galleryRef.value;
    const scrollLeft = container.scrollLeft;
    const childWidth = container.children[0]?.offsetWidth || 1;
    const newIdx = Math.round(scrollLeft / childWidth);
    if (newIdx !== activeImageIndex.value && newIdx >= 0 && newIdx < displayImages.value.length) {
        activeImageIndex.value = newIdx;
    }
};
</script>

<template>
    <Head :title="product.name" />

    <StorefrontLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 md:py-8">

            <!-- Breadcrumb -->
            <nav class="hidden md:flex items-center gap-2 text-sm text-gray-500 mb-6">
                <template v-for="(crumb, i) in breadcrumbs" :key="i">
                    <Link v-if="crumb.href" :href="crumb.href" class="hover:text-blue-600 transition-colors">{{ crumb.name }}</Link>
                    <span v-else class="text-gray-800 font-medium">{{ crumb.name }}</span>
                    <svg v-if="i < breadcrumbs.length - 1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-gray-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </template>
            </nav>

            <!-- Main Layout -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">

                <!-- ═══════════ LEFT: Image Gallery ═══════════ -->
                <div>
                    <!-- Mobile: Horizontal Swipe Gallery -->
                    <div class="md:hidden">
                        <div ref="galleryRef" @scroll="onGalleryScroll" class="flex snap-x snap-mandatory overflow-x-auto scrollbar-hide rounded-xl bg-gray-50 aspect-square">
                            <div v-for="(img, idx) in displayImages" :key="idx" class="w-full flex-shrink-0 snap-center flex items-center justify-center bg-white">
                                <img :src="img.src" :alt="product.name" class="w-full h-full object-cover" />
                            </div>
                            <div v-if="displayImages.length === 0" class="w-full flex-shrink-0 flex items-center justify-center text-gray-300 text-sm">No Image</div>
                        </div>
                        <!-- Dot Indicators -->
                        <div v-if="displayImages.length > 1" class="flex justify-center gap-1.5 mt-3">
                            <button v-for="(_, idx) in displayImages" :key="idx" @click="scrollToImage(idx)"
                                :class="['w-2 h-2 rounded-full transition-all duration-200', activeImageIndex === idx ? 'bg-blue-500 w-5' : 'bg-gray-300']"
                            />
                        </div>
                    </div>

                    <!-- Desktop: Main Image Carousel + Thumbnail Strip -->
                    <div class="hidden md:block sticky top-6">
                        <div class="bg-gray-50 rounded-2xl flex items-center justify-center aspect-square overflow-hidden border border-gray-100 relative group">
                            <img v-if="activeImage" :src="activeImage" :alt="product.name" class="w-full h-full object-cover transition-all duration-300" />
                            <div v-else class="text-gray-300 text-sm">No Image</div>
                            
                            <!-- Carousel Controls -->
                            <button v-if="displayImages.length > 1" @click="activeImageIndex = activeImageIndex > 0 ? activeImageIndex - 1 : displayImages.length - 1" class="absolute left-4 w-10 h-10 bg-white/80 hover:bg-white rounded-full flex items-center justify-center shadow-md text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                            </button>
                            <button v-if="displayImages.length > 1" @click="activeImageIndex = activeImageIndex < displayImages.length - 1 ? activeImageIndex + 1 : 0" class="absolute right-4 w-10 h-10 bg-white/80 hover:bg-white rounded-full flex items-center justify-center shadow-md text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                            </button>
                        </div>
                        <!-- Thumbnails -->
                        <div v-if="displayImages.length > 1" class="flex gap-2 mt-3 overflow-x-auto pb-1 scrollbar-hide">
                            <button v-for="(img, idx) in displayImages" :key="idx" @click="activeImageIndex = idx"
                                :class="['w-16 h-16 rounded-lg overflow-hidden border-2 flex-shrink-0 transition-all duration-200 bg-gray-50 flex items-center justify-center',
                                    activeImageIndex === idx ? 'border-blue-500 shadow-md' : 'border-gray-200 hover:border-gray-400']"
                            >
                                <img :src="img.src" :alt="product.name" class="w-full h-full object-cover" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ═══════════ RIGHT: Product Info ═══════════ -->
                <div class="flex flex-col">
                    <!-- Category Badge -->
                    <div v-if="product.category" class="mb-2">
                        <Link :href="`/search?categories[]=${product.category.id}`" class="inline-block text-xs font-medium text-blue-600 bg-blue-50 px-3 py-1 rounded-full hover:bg-blue-100 transition-colors">
                            {{ product.category.name }}
                        </Link>
                    </div>

                    <!-- Product Name & Share -->
                    <div class="flex items-start justify-between gap-4">
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900 leading-tight">{{ product.name }}</h1>
                        <button class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-full transition-colors" title="Bagikan Produk">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" /></svg>
                        </button>
                    </div>

                    <!-- Rating Summary -->
                    <div class="mt-2.5 flex items-center gap-2.5 flex-wrap">
                        <div class="flex items-center gap-1 text-yellow-400">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" /></svg>
                            <span class="text-sm font-bold text-gray-900">4.8</span>
                        </div>
                        <span class="text-gray-300 text-xs">•</span>
                        <a href="#ulasan" class="text-sm font-medium text-gray-500 hover:text-blue-600 underline-offset-4 hover:underline">124 Ulasan</a>
                        <span class="text-gray-300 text-xs">•</span>
                        <span class="text-sm font-medium text-gray-500">Terjual 500+</span>
                    </div>

                    <!-- Price Section -->
                    <div class="mt-4 space-y-1">
                        <div class="flex items-baseline gap-3">
                            <span class="text-2xl md:text-3xl font-extrabold text-gray-900">{{ formatPrice(currentPrice) }}</span>
                            <span v-if="hasDiscount" class="text-sm font-bold text-white bg-red-500 px-2 py-0.5 rounded-md">-{{ discountPercent }}%</span>
                        </div>
                        <div v-if="hasDiscount" class="text-sm text-gray-400 line-through">{{ formatPrice(product.base_price) }}</div>
                    </div>

                    <!-- Stock Info -->
                    <div class="mt-3">
                        <span v-if="currentStock > 0" class="text-sm font-medium text-green-600 flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            Stok tersedia ({{ currentStock }})
                        </span>
                        <span v-else class="text-sm font-medium text-red-500 flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            Stok habis
                        </span>
                    </div>

                    <!-- Divider -->
                    <hr class="my-5 border-gray-100" />

                    <!-- Variant Selector (Grouped by Type) -->
                    <div v-for="(variants, typeName) in variantGroups" :key="typeName" class="mb-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2.5">
                            {{ typeName }}
                            <span v-if="selectedVariant && selectedVariant.variant_type === typeName" class="font-normal text-gray-400 ml-1">— {{ selectedVariant.name }}</span>
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="variant in variants"
                                :key="variant.id"
                                @click="selectVariant(variant)"
                                :disabled="variant.stock <= 0"
                                :class="[
                                    'px-4 py-2 rounded-lg text-sm font-medium border-2 transition-all duration-200',
                                    selectedVariant?.id === variant.id
                                        ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-sm'
                                        : variant.stock > 0
                                            ? 'border-gray-200 bg-white text-gray-700 hover:border-gray-400'
                                            : 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed line-through'
                                ]"
                            >
                                {{ variant.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Quantity (Desktop Only — Mobile uses Sticky Bar) -->
                    <div class="hidden md:block">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2.5">Jumlah</h3>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                <button @click="decrementQty" :disabled="quantity <= 1" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:bg-gray-50 disabled:text-gray-300 disabled:cursor-not-allowed transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                                </button>
                                <span class="w-12 h-10 flex items-center justify-center text-sm font-semibold text-gray-800 border-x border-gray-200 bg-gray-50">{{ quantity }}</span>
                                <button @click="incrementQty" :disabled="quantity >= currentStock" class="w-10 h-10 flex items-center justify-center text-gray-600 hover:bg-gray-50 disabled:text-gray-300 disabled:cursor-not-allowed transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                </button>
                            </div>
                            <span v-if="currentStock > 0 && currentStock <= 10" class="text-xs text-orange-500 font-medium">Tersisa {{ currentStock }} buah</span>
                        </div>
                    </div>

                    <!-- Add to Cart (Desktop) -->
                    <div class="hidden md:flex flex-wrap gap-3 mt-6">
                        <button :disabled="currentStock <= 0" :class="[
                            'flex-1 flex items-center justify-center gap-2 py-3.5 rounded-xl text-sm md:text-base font-bold border-2 transition-all duration-200',
                            currentStock > 0
                                ? 'border-blue-600 text-blue-600 hover:bg-blue-50'
                                : 'border-gray-200 text-gray-300 cursor-not-allowed'
                        ]">
                            Beli Sekarang
                        </button>
                        <button :disabled="currentStock <= 0" :class="[
                            'flex-1 flex items-center justify-center gap-2 py-3.5 rounded-xl text-sm md:text-base font-bold transition-all duration-200',
                            currentStock > 0
                                ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-200 hover:shadow-blue-300'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                        ]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                            + Keranjang
                        </button>
                        <button :class="[
                            'px-5 py-3.5 rounded-xl text-base font-bold border-2 transition-all duration-200',
                            currentStock > 0
                                ? 'border-blue-600 text-blue-600 hover:bg-blue-50'
                                : 'border-gray-200 text-gray-300 cursor-not-allowed'
                        ]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                        </button>
                    </div>

                    <!-- Divider -->
                    <hr class="my-6 border-gray-100" />

                    <!-- Product Description -->
                    <div>
                        <h3 class="text-base font-bold text-gray-900 mb-3">Deskripsi Produk</h3>
                        <div class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ product.description }}</div>
                    </div>

                    <!-- Product Specs -->
                    <div class="mt-6 bg-gray-50 rounded-xl p-4">
                        <h3 class="text-sm font-bold text-gray-800 mb-3">Detail</h3>
                        <div class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-gray-500">Berat</span>
                            <span class="text-gray-800 font-medium">{{ product.weight_gram }} gram</span>
                            <span class="text-gray-500">Kategori</span>
                            <span class="text-gray-800 font-medium">{{ product.category?.name || '-' }}</span>
                            <span class="text-gray-500">SKU</span>
                            <span class="text-gray-800 font-medium">{{ selectedVariant?.sku || '-' }}</span>
                        </div>
                    </div>

                    <!-- Mini Gallery Grid -->
                    <div v-if="displayImages.length > 0" class="mt-6">
                        <h3 class="text-base font-bold text-gray-900 mb-3">Galeri Foto</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div v-for="(img, idx) in displayImages" :key="idx" class="aspect-square bg-gray-50 rounded-xl border border-gray-100 overflow-hidden hover:border-blue-300 transition-colors cursor-pointer" @click="scrollToImage(idx)">
                                <img :src="img.src" :alt="product.name" class="w-full h-full object-cover" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rating & Reviews Section -->
            <section id="ulasan" class="mt-12 md:mt-16 border-t border-gray-200 pt-10">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900">Ulasan Pembeli</h2>
                    <button class="text-sm font-semibold text-blue-600 hover:text-blue-700">Lihat Semua</button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Rating Stats -->
                    <div class="flex flex-col items-center justify-center p-6 bg-gray-50 rounded-2xl h-fit">
                        <div class="text-5xl font-black text-gray-900 mb-2">4.8<span class="text-xl text-gray-500 font-medium">/5</span></div>
                        <div class="flex gap-1 text-yellow-400 mb-2">
                            <!-- 5 stars -->
                            <svg v-for="i in 5" :key="i" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="text-sm text-gray-500">124 ulasan pembeli</div>
                        
                        <!-- Mini Progress Bars -->
                        <div class="w-full mt-6 space-y-2">
                            <div v-for="star in [5,4,3,2,1]" :key="star" class="flex items-center gap-2 text-sm text-gray-500">
                                <span>{{ star }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-yellow-400"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-yellow-400 rounded-full" :style="{ width: star === 5 ? '80%' : star === 4 ? '15%' : '2%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews List -->
                    <div class="md:col-span-2 space-y-6">
                        <!-- Review Item 1 -->
                        <div class="pb-6 border-b border-gray-100">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">FA</div>
                                <div>
                                    <div class="font-semibold text-gray-900 text-sm">Fulan Ahmad</div>
                                    <div class="text-xs text-gray-500">Varian: Black • 2 hari yang lalu</div>
                                </div>
                            </div>
                            <div class="flex gap-1 text-yellow-400 mb-3">
                                <svg v-for="i in 5" :key="i" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" /></svg>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed">Barang sangat bagus, pengiriman cepat. Packing juga sangat aman pakai bubble wrap tebal. Recommended seller! Bakal beli lagi di sini.</p>
                        </div>
                        <!-- Review Item 2 -->
                        <div class="">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center font-bold text-sm">BW</div>
                                <div>
                                    <div class="font-semibold text-gray-900 text-sm">Budi Wibowo</div>
                                    <div class="text-xs text-gray-500">Varian: White • 1 minggu yang lalu</div>
                                </div>
                            </div>
                            <div class="flex gap-1 text-yellow-400 mb-3">
                                <svg v-for="i in 4" :key="i" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" /></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-300"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed">Kualitas sesuai harga, tapi sayang kurirnya agak lambat. Overall oke lah buat harganya.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Related Products -->
            <section v-if="relatedProducts && relatedProducts.length > 0" class="mt-12 md:mt-16 border-t border-gray-200 pt-10">
                <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-5">Produk Terkait</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <ProductCard v-for="rp in relatedProducts" :key="rp.id" :product="rp" />
                </div>
            </section>

        </div>

        <!-- ═══════════ MOBILE STICKY ADD-TO-CART BAR ═══════════ -->
        <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 z-40 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]">
            <div class="flex items-center gap-3">
                <!-- Quantity Controls -->
                <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                    <button @click="decrementQty" :disabled="quantity <= 1" class="w-9 h-9 flex items-center justify-center text-gray-600 hover:bg-gray-50 disabled:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                    </button>
                    <span class="w-9 h-9 flex items-center justify-center text-sm font-semibold text-gray-800 border-x border-gray-200 bg-gray-50">{{ quantity }}</span>
                    <button @click="incrementQty" :disabled="quantity >= currentStock" class="w-9 h-9 flex items-center justify-center text-gray-600 hover:bg-gray-50 disabled:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </div>

                <!-- Add to Cart Buttons -->
                <div class="flex-1 flex gap-2">
                    <button :disabled="currentStock <= 0" :class="[
                        'flex-1 flex items-center justify-center py-3 rounded-xl text-xs sm:text-sm font-bold transition-all border-2',
                        currentStock > 0
                            ? 'border-blue-600 text-blue-600 active:bg-blue-50'
                            : 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50'
                    ]">
                        Beli Langsung
                    </button>
                    <button :disabled="currentStock <= 0" :class="[
                        'flex-1 flex items-center justify-center gap-1 sm:gap-2 py-3 rounded-xl text-xs sm:text-sm font-bold transition-all',
                        currentStock > 0
                            ? 'bg-blue-600 text-white active:bg-blue-700'
                            : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    ]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="hidden sm:block w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                        + Keranjang
                    </button>
                </div>
            </div>
        </div>

        <!-- Spacer for mobile sticky bar -->
        <div class="md:hidden h-20"></div>
    </StorefrontLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
