<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ product: { type: Object, required: true } });

const formatPrice = (p) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(p);

const primaryImage = () => {
    if (!props.product.images || props.product.images.length === 0) return null;
    const pri = props.product.images.find(i => i.is_primary);
    return pri ? pri.image_path : props.product.images[0].image_path;
};
const lowestPrice = () => {
    const base = parseFloat(props.product.base_price);
    if (props.product.variants?.length) {
        const low = Math.min(...props.product.variants.map(v => parseFloat(v.price)));
        return low < base ? low : base;
    }
    return base;
};
const discount = () => {
    const base = parseFloat(props.product.base_price);
    const low = lowestPrice();
    return base > low ? Math.round(((base - low) / base) * 100) : 0;
};
</script>

<template>
    <Link :href="route('products.show', product.slug)"
          class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md transition-all duration-300 group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
        <div class="w-20 h-20 shrink-0 bg-gray-100 rounded-lg overflow-hidden">
            <img v-if="primaryImage()" :src="'/storage/' + primaryImage()" :alt="product.name"
                 loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-[10px] uppercase">No Image</div>
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-semibold text-gray-800 line-clamp-2 leading-snug font-heading">{{ product.name }}</h3>
            <div class="mt-1 flex items-center gap-2 flex-wrap">
                <span v-if="discount() > 0" class="text-xs text-gray-400 line-through">{{ formatPrice(product.base_price) }}</span>
                <span class="text-base font-bold text-blue-600">{{ formatPrice(lowestPrice()) }}</span>
                <span v-if="discount() > 0" class="text-[11px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded">-{{ discount() }}%</span>
            </div>
        </div>
    </Link>
</template>
