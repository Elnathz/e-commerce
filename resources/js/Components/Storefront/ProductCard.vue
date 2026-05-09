<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

// Format currency
const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};

// Helper to get the primary or first image
const getPrimaryImage = (product) => {
    if (!product.images || product.images.length === 0) return null;
    const primary = product.images.find(img => img.is_primary);
    return primary ? primary.image_path : product.images[0].image_path;
};

// Get lowest price among variants, fallback to base_price
const getLowestPrice = (product) => {
    if (product.variants && product.variants.length > 0) {
        const prices = product.variants.map(v => parseFloat(v.price));
        const lowest = Math.min(...prices);
        return lowest < parseFloat(product.base_price) ? lowest : parseFloat(product.base_price);
    }
    return parseFloat(product.base_price);
};

// Calculate discount percentage
const getDiscount = (product) => {
    const lowest = getLowestPrice(product);
    const base = parseFloat(product.base_price);
    if (base <= lowest) return 0;
    return Math.round(((base - lowest) / base) * 100);
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

            <!-- Price -->
            <div class="mt-auto pt-1">
                <div class="text-base font-bold text-gray-900">{{ formatPrice(getLowestPrice(product)) }}</div>
                <div v-if="product.base_price > getLowestPrice(product)" class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-xs text-gray-400 line-through">{{ formatPrice(product.base_price) }}</span>
                    <span class="text-[11px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded">{{ getDiscount(product) }}%</span>
                </div>
            </div>
        </div>
    </Link>
</template>
