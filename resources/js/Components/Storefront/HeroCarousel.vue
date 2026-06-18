<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ slides: { type: Array, default: () => [] } });

const current = ref(0);
let timer = null;
const reduceMotion = ref(false);

const slideImage = (path) =>
    path?.startsWith('images/') || path?.startsWith('/images/') ? '/' + path.replace(/^\//, '') : '/storage/' + path;

const go = (i) => { current.value = (i + props.slides.length) % props.slides.length; };
const next = () => go(current.value + 1);
const prev = () => go(current.value - 1);

const start = () => {
    if (reduceMotion.value || props.slides.length <= 1) return;
    stop();
    timer = setInterval(next, 5000);
};
const stop = () => { if (timer) { clearInterval(timer); timer = null; } };

onMounted(() => {
    reduceMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    start();
});
onBeforeUnmount(stop);

const hasMultiple = computed(() => props.slides.length > 1);
</script>

<template>
    <div v-if="slides.length" class="relative rounded-2xl overflow-hidden h-full min-h-[260px] md:min-h-[360px] group bg-gray-900"
         @mouseenter="stop" @mouseleave="start">
        <div v-for="(slide, i) in slides" :key="slide.id ?? i"
             class="absolute inset-0 transition-opacity duration-700 ease-out"
             :class="i === current ? 'opacity-100' : 'opacity-0 pointer-events-none'">
            <img :src="slideImage(slide.image_path)" :alt="slide.title"
                 class="absolute inset-0 w-full h-full object-cover" loading="eager" />
            <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/30 to-transparent"></div>
            <div class="relative z-10 h-full flex flex-col justify-center max-w-md p-6 md:p-10 text-white">
                <h2 class="text-2xl md:text-4xl font-extrabold leading-tight font-heading">{{ slide.title }}</h2>
                <p v-if="slide.subtitle" class="mt-2 text-sm md:text-base text-gray-200">{{ slide.subtitle }}</p>
                <p v-if="slide.badge_label" class="mt-3 text-lg md:text-2xl font-black text-yellow-300">{{ slide.badge_label }}</p>
                <Link v-if="slide.cta_label && slide.cta_url" :href="slide.cta_url"
                      class="mt-5 inline-flex w-fit items-center gap-2 bg-white text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm hover:bg-gray-100 transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                    {{ slide.cta_label }}
                </Link>
            </div>
        </div>

        <!-- Arrows -->
        <template v-if="hasMultiple">
            <button @click="prev" aria-label="Slide sebelumnya"
                    class="absolute left-3 top-1/2 -translate-y-1/2 z-20 bg-white/80 hover:bg-white text-gray-800 rounded-full p-2 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200 shadow cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </button>
            <button @click="next" aria-label="Slide berikutnya"
                    class="absolute right-3 top-1/2 -translate-y-1/2 z-20 bg-white/80 hover:bg-white text-gray-800 rounded-full p-2 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200 shadow cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
            <!-- Dots -->
            <div class="absolute bottom-4 right-6 z-20 flex gap-1.5">
                <button v-for="(s, i) in slides" :key="'dot'+i" @click="go(i)"
                        :aria-label="`Ke slide ${i+1}`"
                        class="h-2 rounded-full transition-all duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1"
                        :class="i === current ? 'w-5 bg-white' : 'w-2 bg-white/50 hover:bg-white/80'"></button>
            </div>
        </template>
    </div>
</template>
