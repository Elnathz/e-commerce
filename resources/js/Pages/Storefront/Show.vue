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
                            <div v-for="(img, idx) in displayImages" :key="idx" class="w-full flex-shrink-0 snap-center flex items-center justify-center">
                                <img :src="img.src" :alt="product.name" class="max-w-full max-h-full object-contain p-4" />
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

                    <!-- Desktop: Main Image + Thumbnail Strip -->
                    <div class="hidden md:block sticky top-6">
                        <div class="bg-gray-50 rounded-2xl flex items-center justify-center aspect-square overflow-hidden border border-gray-100">
                            <img v-if="activeImage" :src="activeImage" :alt="product.name" class="max-w-full max-h-full object-contain p-6 transition-all duration-300" />
                            <div v-else class="text-gray-300 text-sm">No Image</div>
                        </div>
                        <!-- Thumbnails -->
                        <div v-if="displayImages.length > 1" class="flex gap-2 mt-3 overflow-x-auto pb-1">
                            <button v-for="(img, idx) in displayImages" :key="idx" @click="activeImageIndex = idx"
                                :class="['w-16 h-16 rounded-lg overflow-hidden border-2 flex-shrink-0 transition-all duration-200 bg-gray-50 flex items-center justify-center',
                                    activeImageIndex === idx ? 'border-blue-500 shadow-md' : 'border-gray-200 hover:border-gray-400']"
                            >
                                <img :src="img.src" :alt="product.name" class="max-w-full max-h-full object-contain p-1" />
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

                    <!-- Product Name -->
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900 leading-tight">{{ product.name }}</h1>

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
                    <div class="hidden md:flex gap-3 mt-6">
                        <button :disabled="currentStock <= 0" :class="[
                            'flex-1 flex items-center justify-center gap-2 py-3.5 rounded-xl text-base font-bold transition-all duration-200',
                            currentStock > 0
                                ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-200 hover:shadow-blue-300'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                        ]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                            Masukkan Keranjang
                        </button>
                        <button :disabled="currentStock <= 0" :class="[
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
                </div>
            </div>

            <!-- Related Products -->
            <section v-if="relatedProducts && relatedProducts.length > 0" class="mt-12 md:mt-16">
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

                <!-- Add to Cart Button -->
                <button :disabled="currentStock <= 0" :class="[
                    'flex-1 flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold transition-all',
                    currentStock > 0
                        ? 'bg-blue-600 text-white active:bg-blue-700'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                ]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                    Keranjang · {{ formatPrice(currentPrice * quantity) }}
                </button>
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
