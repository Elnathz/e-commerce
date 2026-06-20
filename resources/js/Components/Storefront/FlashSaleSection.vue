<script setup>
import { Link } from '@inertiajs/vue3';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import FlashCountdown from '@/Components/Storefront/FlashCountdown.vue';
import BoltIcon from '@/Components/Storefront/Icons/BoltIcon.vue';

defineProps({
    // ISO string of the soonest-ending active flash window, or null.
    endsAt: { type: String, default: null },
    products: { type: Array, default: () => [] },
});
</script>

<template>
    <section v-if="products.length" aria-label="Flash Sale">
        <div class="rounded-xl border border-slate-200 bg-white p-3 md:p-4">
            <!-- Compact header: flat red bolt chip, wordmark, dark countdown clock, link. -->
            <div class="flex items-center gap-2.5 mb-3">
                <span class="grid place-items-center w-7 h-7 rounded-md bg-red-600 shrink-0">
                    <BoltIcon class="w-4 h-4 text-white" />
                </span>
                <h2 class="text-lg font-bold text-slate-900 font-heading leading-none whitespace-nowrap">Flash Sale</h2>

                <div v-if="endsAt" class="flex items-center gap-1.5">
                    <span class="hidden sm:inline text-xs text-slate-400">Berakhir</span>
                    <FlashCountdown
                        :ends-at="endsAt"
                        class="!text-white bg-slate-900 px-2 py-1 rounded-md text-xs"
                    />
                </div>

                <Link
                    href="/flash-sale"
                    class="ml-auto shrink-0 inline-flex items-center gap-0.5 text-sm font-semibold text-red-600 hover:text-red-700 transition-colors"
                >
                    Lihat Semua
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg>
                </Link>
            </div>

            <!-- Product rail -->
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
