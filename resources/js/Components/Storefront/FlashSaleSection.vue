<script setup>
import { Link } from '@inertiajs/vue3';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import FlashCountdown from '@/Components/Storefront/FlashCountdown.vue';

defineProps({
    // ISO string of the soonest-ending active flash window, or null.
    endsAt: { type: String, default: null },
    products: { type: Array, default: () => [] },
});
</script>

<template>
    <section v-if="products.length" aria-label="Flash Sale">
        <div class="rounded-2xl bg-gradient-to-br from-orange-50 to-red-50 border border-orange-100 p-4 md:p-5">
            <!-- Header: title + section countdown + Lihat Semua -->
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="flex items-center gap-1.5 text-xl md:text-2xl font-bold text-gray-900 font-heading">
                        <svg class="w-6 h-6 text-orange-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M11.3 1.046a1 1 0 01.7 1.17L10.6 8h4.4a1 1 0 01.78 1.625l-7 8.75a1 1 0 01-1.76-.84L8.4 11H4a1 1 0 01-.78-1.625l7-8.75a1 1 0 011.08-.58z" clip-rule="evenodd" />
                        </svg>
                        Flash Sale
                    </h2>
                    <FlashCountdown
                        v-if="endsAt"
                        :ends-at="endsAt"
                        class="bg-white/70 px-2.5 py-1 rounded-lg border border-orange-200"
                    />
                </div>
                <Link
                    href="/flash-sale"
                    class="shrink-0 text-sm font-semibold text-orange-600 hover:text-orange-700 transition-colors"
                >
                    Lihat Semua
                </Link>
            </div>

            <!-- Horizontal scroll row of flash products -->
            <div class="flex gap-3 overflow-x-auto no-scrollbar snap-x pb-1">
                <div
                    v-for="product in products"
                    :key="product.id"
                    class="snap-start shrink-0 w-40 sm:w-44 md:w-48"
                >
                    <ProductCard :product="product" :flash-quota="product.flash_quota" />
                </div>
            </div>
        </div>
    </section>
</template>
