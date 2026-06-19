<script setup>
import { Link } from '@inertiajs/vue3';
import PriceTag from '@/Components/Storefront/PriceTag.vue';

const props = defineProps({ product: { type: Object, required: true } });

const primaryImage = () => {
    if (!props.product.images || props.product.images.length === 0) return null;
    const pri = props.product.images.find(i => i.is_primary);
    return pri ? pri.image_path : props.product.images[0].image_path;
};
</script>

<template>
    <Link :href="route('products.show', product.slug)"
          class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md transition-all duration-300 group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
        <div class="w-16 h-16 sm:w-20 sm:h-20 shrink-0 bg-gray-100 rounded-lg overflow-hidden">
            <img v-if="primaryImage()" :src="'/storage/' + primaryImage()" :alt="product.name"
                 loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-[10px] uppercase">No Image</div>
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-semibold text-gray-800 line-clamp-2 leading-snug font-heading">{{ product.name }}</h3>
            <PriceTag :info="product.price_display" />
        </div>
    </Link>
</template>
