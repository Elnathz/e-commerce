<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import HeroCarousel from '@/Components/Storefront/HeroCarousel.vue';
import BannerImage from '@/Components/Storefront/BannerImage.vue';
import ImageTileCard from '@/Components/Storefront/ImageTileCard.vue';
import FlashSaleSection from '@/Components/Storefront/FlashSaleSection.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    heroMainBanners: { type: Array, default: () => [] },
    heroSideBanners: { type: Array, default: () => [] },
    popularCategories: { type: Array, default: () => [] },
    banyakDicari: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    flashSale: { type: Object, default: () => ({ ends_at: null, products: [] }) },
});
</script>

<template>
    <Head title="Home - MegaMart" />
    <StorefrontLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-12">

            <!-- HERO: kiri 2 banner | tengah carousel | kanan 2 banner (murni gambar) -->
            <section v-if="heroMainBanners.length || heroSideBanners.length">
                <!-- desktop -->
                <div class="hidden lg:grid grid-cols-4 gap-3">
                    <div class="flex flex-col gap-3">
                        <BannerImage v-for="b in heroSideBanners.slice(0,2)" :key="b.id" :banner="b" />
                    </div>
                    <div class="col-span-2">
                        <HeroCarousel v-if="heroMainBanners.length" :slides="heroMainBanners" />
                    </div>
                    <div class="flex flex-col gap-3">
                        <BannerImage v-for="b in heroSideBanners.slice(2,4)" :key="b.id" :banner="b" />
                    </div>
                </div>
                <!-- mobile/tablet -->
                <div class="lg:hidden space-y-3">
                    <HeroCarousel v-if="heroMainBanners.length" :slides="heroMainBanners" />
                    <div v-if="heroSideBanners.length" class="grid grid-cols-2 gap-3">
                        <BannerImage v-for="b in heroSideBanners" :key="b.id" :banner="b" />
                    </div>
                </div>
            </section>

            <!-- FLASH SALE -->
            <FlashSaleSection :ends-at="flashSale.ends_at" :products="flashSale.products" />

            <!-- KATEGORI POPULER -->
            <section v-if="popularCategories.length">
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800 font-heading">
                        Kategori <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Populer</span>
                    </h2>
                    <Link href="/search" class="text-sm font-medium text-gray-500 hover:text-blue-600 mb-1 transition-colors duration-150">View All</Link>
                </div>
                <div class="flex gap-3 overflow-x-auto no-scrollbar snap-x pb-2">
                    <ImageTileCard v-for="cat in popularCategories" :key="cat.id" :item="cat" />
                </div>
            </section>

            <!-- LAGI BANYAK DICARI -->
            <section v-if="banyakDicari.length">
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800 font-heading">
                        Lagi <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Banyak Dicari</span>
                    </h2>
                    <Link href="/search" class="text-sm font-medium text-gray-500 hover:text-blue-600 mb-1 transition-colors duration-150">View All</Link>
                </div>
                <div class="flex gap-3 overflow-x-auto no-scrollbar snap-x pb-2">
                    <ImageTileCard v-for="kw in banyakDicari" :key="kw.id" :item="kw" :show-count="true" />
                </div>
            </section>

            <!-- REKOMENDASI -->
            <section v-if="products.length">
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800 font-heading">
                        Rekomendasi <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Untukmu</span>
                    </h2>
                    <Link href="/search" class="text-sm font-medium text-gray-500 hover:text-blue-600 mb-1 transition-colors duration-150">View All</Link>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <ProductCard v-for="product in products" :key="product.id" :product="product" />
                </div>
            </section>

        </div>
    </StorefrontLayout>
</template>
