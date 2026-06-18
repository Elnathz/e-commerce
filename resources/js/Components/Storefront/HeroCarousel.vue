<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ slides: { type: Array, default: () => [] } });

const current = ref(0);
let timer = null;
const reduceMotion = ref(false);

const slideImage = (path) =>
    path?.startsWith('images/') || path?.startsWith('/images/')
        ? '/' + path.replace(/^\//, '') : '/storage/' + path;

const go = (i) => { current.value = (i + props.slides.length) % props.slides.length; };
const next = () => go(current.value + 1);
const prev = () => go(current.value - 1);
const start = () => { if (reduceMotion.value || props.slides.length <= 1) return; stop(); timer = setInterval(next, 5000); };
const stop = () => { if (timer) { clearInterval(timer); timer = null; } };

onMounted(() => {
    reduceMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    start();
});
onBeforeUnmount(stop);

const hasMultiple = computed(() => props.slides.length > 1);
</script>

<template>
    <div v-if="slides.length" class="relative rounded-2xl overflow-hidden aspect-[2/1] group bg-gray-100"
         @mouseenter="stop" @mouseleave="start">
        <component :is="slide.cta_url ? 'a' : 'div'" v-for="(slide, i) in slides" :key="slide.id ?? i"
             :href="slide.cta_url || undefined"
             class="absolute inset-0 transition-opacity duration-700 ease-out"
             :class="i === current ? 'opacity-100' : 'opacity-0 pointer-events-none'">
            <img :src="slideImage(slide.image_path)" :alt="slide.title || 'Banner'"
                 class="w-full h-full object-cover" :loading="i === 0 ? 'eager' : 'lazy'" />
        </component>

        <template v-if="hasMultiple">
            <button @click.prevent="prev" aria-label="Slide sebelumnya"
                    class="absolute left-3 top-1/2 -translate-y-1/2 z-20 bg-white/80 hover:bg-white text-gray-800 rounded-full p-2 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200 shadow cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </button>
            <button @click.prevent="next" aria-label="Slide berikutnya"
                    class="absolute right-3 top-1/2 -translate-y-1/2 z-20 bg-white/80 hover:bg-white text-gray-800 rounded-full p-2 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200 shadow cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
            <div class="absolute bottom-3 right-4 z-20 flex gap-1.5">
                <button v-for="(s, i) in slides" :key="'dot'+i" @click.prevent="go(i)" :aria-label="`Ke slide ${i+1}`"
                        class="h-2 rounded-full transition-all duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        :class="i === current ? 'w-5 bg-white' : 'w-2 bg-white/60 hover:bg-white/90'"></button>
            </div>
        </template>
    </div>
</template>
