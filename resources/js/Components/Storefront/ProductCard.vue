<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PriceTag from '@/Components/Storefront/PriceTag.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    // Optional flash-sale urgency meta: { sold, quota }. quota null = unlimited.
    // Only rendered when the resolved price is actually a flash price.
    flashQuota: {
        type: Object,
        default: null,
    },
});

const showFlashQuota = computed(
    () => props.flashQuota && props.product.price_display?.source === 'flash'
);

// Progress fill: ratio sold/quota (capped 100%); unlimited quota shows a full
// track behind the "terjual" label rather than a misleading partial fill.
const quotaPct = computed(() => {
    const fq = props.flashQuota;
    if (!fq || fq.quota === null || fq.quota === undefined || fq.quota <= 0) return 100;
    return Math.min(100, Math.round((fq.sold / fq.quota) * 100));
});

const quotaLabel = computed(() => {
    const fq = props.flashQuota;
    if (!fq) return '';
    if (fq.quota === null || fq.quota === undefined) return `${fq.sold} terjual`;
    return fq.sold >= fq.quota ? 'Habis' : `Terjual ${fq.sold}`;
});

// Helper to get the primary or first image
const getPrimaryImage = (product) => {
    if (!product.images || product.images.length === 0) return null;
    const primary = product.images.find(img => img.is_primary);
    return primary ? primary.image_path : product.images[0].image_path;
};

// Group variants by variant_type for smart badge display
const getGroupedVariants = (variants) => {
    const groups = {};
    variants.forEach(v => {
        const type = v.variant_type || v.name;
        if (!groups[type]) {
            groups[type] = { type, count: 0 };
        }
        groups[type].count++;
    });
    return Object.values(groups);
};

// Calculate total available stock across variants
const getTotalStock = (product) => {
    if (product.variants && product.variants.length > 0) {
        return product.variants.reduce((total, v) => total + (Math.max(0, v.stock - (v.reserved_stock || 0))), 0);
    }
    return 0;
};
</script>

<template>
    <Link
        :href="route('products.show', product.slug)"
        class="bg-white border border-gray-100 rounded-xl overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col group"
    >
        <!-- Image (full bleed, fixed height, object-cover) -->
        <div class="h-48 bg-gray-100 relative overflow-hidden">
            <img
                v-if="getPrimaryImage(product)"
                :src="'/storage/' + getPrimaryImage(product)"
                :alt="product.name"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-xs uppercase tracking-wide">No Image</div>
        </div>

        <!-- Details -->
        <div class="p-3 flex flex-col flex-1">
            <!-- Product Name -->
            <h3 class="text-sm font-semibold text-gray-800 line-clamp-2 leading-snug mb-1.5">{{ product.name }}</h3>

            <!-- Variant Tags (grouped by type) -->
            <div v-if="product.variants && product.variants.length > 0" class="flex flex-wrap gap-1 mb-2">
                <template v-for="group in getGroupedVariants(product.variants)" :key="group.type">
                    <span class="text-[11px] text-gray-500 border border-gray-200 rounded-full px-2 py-0.5 bg-gray-50">
                        {{ group.type }} <span v-if="group.count > 1" class="text-gray-400">+{{ group.count - 1 }}</span>
                    </span>
                </template>
            </div>

            <!-- Price and Stock -->
            <div class="mt-auto pt-1 flex justify-between items-end gap-1.5">
                <PriceTag :info="product.price_display" />
                <div class="text-[11px] text-gray-500 mb-0.5 text-right whitespace-nowrap">
                    Sisa: <span class="font-medium" :class="{'text-red-600': getTotalStock(product) <= 5}">{{ getTotalStock(product) }}</span>
                </div>
            </div>

            <!-- Flash Sale quota progress (urgency) -->
            <div v-if="showFlashQuota" class="mt-2">
                <div class="relative h-4 rounded-full bg-orange-100 overflow-hidden">
                    <div
                        class="absolute inset-y-0 left-0 bg-gradient-to-r from-orange-500 to-red-500 rounded-full transition-[width] duration-300"
                        :style="{ width: quotaPct + '%' }"
                    ></div>
                    <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white tabular-nums">
                        {{ quotaLabel }}
                    </span>
                </div>
            </div>
        </div>
    </Link>
</template>
