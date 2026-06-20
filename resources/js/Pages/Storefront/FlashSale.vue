<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import FlashCountdown from '@/Components/Storefront/FlashCountdown.vue';
import BoltIcon from '@/Components/Storefront/Icons/BoltIcon.vue';
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
            <div class="flex items-center gap-3 flex-wrap mb-8 pb-5 border-b border-slate-200">
                <span class="grid place-items-center w-11 h-11 rounded-lg bg-red-600 shrink-0">
                    <BoltIcon class="w-6 h-6 text-white" />
                </span>
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold font-heading text-slate-900 leading-none">Flash Sale</h1>
                    <p class="text-slate-500 text-sm mt-1.5">Diskon kilat dengan kuota terbatas — buruan sebelum kehabisan!</p>
                </div>
                <div v-if="ends_at" class="ml-auto text-right">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400 mb-1">Berakhir dalam</p>
                    <FlashCountdown
                        :ends-at="ends_at"
                        class="!text-white bg-slate-900 px-3 py-1.5 rounded-lg text-base font-semibold"
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
                <BoltIcon class="w-14 h-14 mx-auto text-gray-300" />
                <h2 class="mt-4 text-lg font-semibold text-gray-700">Belum ada Flash Sale yang aktif</h2>
                <p class="text-gray-500 text-sm mt-1">Pantau terus ya — flash sale berikutnya segera hadir.</p>
                <Link href="/" class="inline-block mt-5 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition-colors">
                    Kembali ke Beranda
                </Link>
            </div>

        </div>
    </StorefrontLayout>
</template>
