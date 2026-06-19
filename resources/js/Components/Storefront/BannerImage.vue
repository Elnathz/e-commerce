<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    banner: { type: Object, required: true },
    ratioClass: { type: String, default: 'aspect-[2/1]' },
});

const bannerImage = (path) =>
    path?.startsWith('images/') || path?.startsWith('/images/')
        ? '/' + path.replace(/^\//, '') : '/storage/' + path;
</script>

<template>
    <Link v-if="banner.cta_url" :href="banner.cta_url"
          class="block rounded-xl overflow-hidden bg-gray-100 group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" :class="ratioClass">
        <img :src="bannerImage(banner.image_path)" :alt="banner.title || 'Banner promo'" loading="lazy"
             class="w-full h-full object-contain group-hover:scale-[1.02] transition-transform duration-300" />
    </Link>
    <div v-else class="rounded-xl overflow-hidden bg-gray-100" :class="ratioClass">
        <img :src="bannerImage(banner.image_path)" :alt="banner.title || 'Banner promo'" loading="lazy"
             class="w-full h-full object-contain" />
    </div>
</template>
