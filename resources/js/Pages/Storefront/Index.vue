<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import PromoProductCard from '@/Components/Storefront/PromoProductCard.vue';
import HeroCarousel from '@/Components/Storefront/HeroCarousel.vue';
import { Head, Link } from '@inertiajs/vue3';
import AddressFormModal from '@/Components/Profile/AddressFormModal.vue';

defineProps({
    heroSlides: { type: Array, default: () => [] },
    onSaleProducts: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const categoryImage = (path) => '/storage/' + path;
</script>

<template>
    <Head title="Home - MegaMart" />
    <StorefrontLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-12">

            <!-- HERO BLOCK: carousel (kiri) + daftar produk on-sale (kanan, 1 kolom lebar) -->
            <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2">
                    <HeroCarousel v-if="heroSlides.length" :slides="heroSlides" />
                </div>
                <!-- 1 kolom: kartu lebar penuh agar harga rupiah panjang (8 digit) tidak terpotong -->
                <div v-if="onSaleProducts.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-3 content-start">
                    <PromoProductCard
                        v-for="product in onSaleProducts.slice(0, 4)"
                        :key="product.id"
                        :product="product"
                    />
                </div>
            </section>

            <!-- KATEGORI (2 level: kartu parent ber-ikon + child sbg chip teks; image-independent) -->
            <section v-if="categories.length">
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800 font-heading">
                        <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Kategori</span>
                    </h2>
                    <Link href="/search" class="text-sm font-medium text-gray-500 hover:text-blue-600 flex items-center gap-1 mb-1 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        View All
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="parent in categories" :key="parent.id"
                         class="bg-white border border-gray-100 rounded-2xl p-4 hover:shadow-md transition-shadow duration-200">
                        <!-- Header parent: ikon (atau gambar bila ada) + nama -->
                        <Link :href="`/search?categories[]=${parent.id}`"
                              class="flex items-center gap-3 group mb-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                            <div class="w-11 h-11 rounded-xl bg-[#F3F9FB] flex items-center justify-center overflow-hidden shrink-0 group-hover:bg-blue-50 transition-colors duration-200">
                                <img v-if="parent.image_path" :src="categoryImage(parent.image_path)" :alt="parent.name"
                                     loading="lazy" class="w-full h-full object-contain p-1.5" />
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-6 h-6 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                            </div>
                            <span class="font-semibold text-gray-800 group-hover:text-blue-600 transition-colors duration-200 font-heading">{{ parent.name }}</span>
                        </Link>
                        <!-- Child sbg chip teks (2 level; cucu tidak ada/di-load) -->
                        <div v-if="parent.children && parent.children.length" class="flex flex-wrap gap-2">
                            <Link
                                v-for="child in parent.children"
                                :key="child.id"
                                :href="`/search?categories[]=${child.id}`"
                                class="inline-flex items-center bg-[#F3F9FB] text-gray-600 text-xs font-medium px-3 py-1.5 rounded-full hover:bg-blue-50 hover:text-blue-600 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            >
                                {{ child.name }}
                            </Link>
                        </div>
                        <p v-else class="text-xs text-gray-400">Lihat semua produk &rarr;</p>
                    </div>
                </div>
            </section>

            <!-- PROMO SPESIAL (produk on-sale; Spec C: jadi Flash Sale + countdown) -->
            <section v-if="onSaleProducts.length">
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800 font-heading">
                        Promo <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Spesial</span>
                    </h2>
                    <Link href="/search" class="text-sm font-medium text-gray-500 hover:text-blue-600 flex items-center gap-1 mb-1 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        View All
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <PromoProductCard v-for="product in onSaleProducts" :key="product.id" :product="product" />
                </div>
            </section>

            <!-- REKOMENDASI -->
            <section v-if="products.length">
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800 font-heading">
                        Rekomendasi <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Untukmu</span>
                    </h2>
                    <Link href="/search" class="text-sm font-medium text-gray-500 hover:text-blue-600 flex items-center gap-1 mb-1 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        View All
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <ProductCard v-for="product in products" :key="product.id" :product="product" />
                </div>
            </section>

        </div>
    </StorefrontLayout>
</template>
