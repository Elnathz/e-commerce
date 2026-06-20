<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import FlashCountdown from '@/Components/Storefront/FlashCountdown.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    ends_at: { type: String, default: null },
    products: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Flash Sale - MegaMart" />
    <StorefrontLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

            <!-- Header -->
            <div class="rounded-2xl bg-gradient-to-br from-orange-500 to-red-500 text-white p-5 md:p-7 mb-8">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl md:text-3xl font-bold font-heading">
                            <svg class="w-7 h-7" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M11.3 1.046a1 1 0 01.7 1.17L10.6 8h4.4a1 1 0 01.78 1.625l-7 8.75a1 1 0 01-1.76-.84L8.4 11H4a1 1 0 01-.78-1.625l7-8.75a1 1 0 011.08-.58z" clip-rule="evenodd" />
                            </svg>
                            Flash Sale
                        </h1>
                        <p class="text-white/90 text-sm mt-1">Diskon kilat dengan kuota terbatas — buruan sebelum kehabisan!</p>
                    </div>
                    <FlashCountdown
                        v-if="ends_at"
                        :ends-at="ends_at"
                        class="bg-white/20 px-3 py-1.5 rounded-lg text-base font-semibold"
                    />
                </div>
            </div>

            <!-- Grid -->
            <div v-if="products.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <ProductCard
                    v-for="product in products"
                    :key="product.id"
                    :product="product"
                    :flash-quota="product.flash_quota"
                />
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-20">
                <svg class="w-14 h-14 mx-auto text-gray-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.3 1.046a1 1 0 01.7 1.17L10.6 8h4.4a1 1 0 01.78 1.625l-7 8.75a1 1 0 01-1.76-.84L8.4 11H4a1 1 0 01-.78-1.625l7-8.75a1 1 0 011.08-.58z" clip-rule="evenodd" />
                </svg>
                <h2 class="mt-4 text-lg font-semibold text-gray-700">Belum ada Flash Sale yang aktif</h2>
                <p class="text-gray-500 text-sm mt-1">Pantau terus ya — flash sale berikutnya segera hadir.</p>
                <Link href="/" class="inline-block mt-5 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition-colors">
                    Kembali ke Beranda
                </Link>
            </div>

        </div>
    </StorefrontLayout>
</template>
