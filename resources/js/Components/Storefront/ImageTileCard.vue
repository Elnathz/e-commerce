<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    item: { type: Object, required: true },   // {id, name, image, product_count}
    showCount: { type: Boolean, default: false },
});
</script>

<template>
    <Link :href="`/search?categories[]=${item.id}`"
          class="shrink-0 w-36 snap-start bg-white border border-gray-100 rounded-xl p-3 hover:shadow-md transition-shadow duration-200 flex flex-col items-center text-center group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
        <div class="w-full aspect-square rounded-lg bg-gray-100 overflow-hidden mb-2">
            <img v-if="item.image" :src="'/storage/' + item.image" :alt="item.name" loading="lazy"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-[10px] uppercase">No Image</div>
        </div>
        <span class="text-sm font-semibold text-gray-800 line-clamp-2 leading-tight">{{ item.name }}</span>
        <span v-if="showCount" class="text-xs text-gray-400 mt-0.5">{{ item.product_count }} produk</span>
    </Link>
</template>
