<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: Boolean,
    images: {
        type: Array,
        required: true,
        default: () => []
    },
    activeIndex: {
        type: Number,
        default: 0
    },
    title: {
        type: String,
        default: ''
    },
    subtitle: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['close', 'update:activeIndex']);

const internalIndex = ref(props.activeIndex);
const imgLoading = ref(true);
const imgError = ref(false);

watch(() => props.activeIndex, (newVal) => {
    internalIndex.value = newVal;
    imgLoading.value = true;
    imgError.value = false;
    resetZoom();
});

// Swipe Mobile
const touchStartX = ref(0);
const touchEndX = ref(0);

const onTouchStart = (e) => {
    touchStartX.value = e.changedTouches[0].screenX;
};

const onTouchEnd = (e) => {
    touchEndX.value = e.changedTouches[0].screenX;
    handleSwipe();
};

const handleSwipe = () => {
    const swipeThreshold = 50;
    if (touchEndX.value < touchStartX.value - swipeThreshold) {
        next();
    }
    if (touchEndX.value > touchStartX.value + swipeThreshold) {
        prev();
    }
};

// Zoom logic
const isZoomed = ref(false);
const zoomStyle = ref({ transform: 'scale(1)', transformOrigin: 'center center', cursor: 'zoom-in' });

const toggleZoom = (e) => {
    if (!isZoomed.value) {
        const rect = e.target.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        zoomStyle.value = {
            transform: 'scale(2.5)',
            transformOrigin: `${x}% ${y}%`,
            cursor: 'zoom-out'
        };
        isZoomed.value = true;
    } else {
        resetZoom();
    }
};

const resetZoom = () => {
    zoomStyle.value = { transform: 'scale(1)', transformOrigin: 'center center', cursor: 'zoom-in' };
    isZoomed.value = false;
};

const currentImage = computed(() => {
    if (!props.images || props.images.length === 0) return null;
    return props.images[internalIndex.value];
});

const next = () => {
    if (internalIndex.value < props.images.length - 1) {
        internalIndex.value++;
        emit('update:activeIndex', internalIndex.value);
        imgLoading.value = true;
        imgError.value = false;
    }
};

const prev = () => {
    if (internalIndex.value > 0) {
        internalIndex.value--;
        emit('update:activeIndex', internalIndex.value);
        imgLoading.value = true;
        imgError.value = false;
    }
};

const close = () => {
    emit('close');
};

const handleKeydown = (e) => {
    if (!props.show) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <teleport to="body">
        <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-sm">
                <!-- Header: Metadata & Actions -->
                <div class="absolute top-0 inset-x-0 p-4 flex items-start justify-between bg-gradient-to-b from-black/60 to-transparent pointer-events-none z-10">
                    <div class="text-white pointer-events-auto">
                        <h3 v-if="title" class="font-bold text-lg leading-tight">{{ title }}</h3>
                        <p v-if="subtitle" class="text-sm text-slate-300 mt-0.5">{{ subtitle }}</p>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3 pointer-events-auto">
                        <a v-if="currentImage?.url" :href="currentImage.url" download class="text-white/70 hover:text-white bg-black/20 hover:bg-black/40 p-2 rounded-full transition-colors" title="Download Gambar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        </a>
                        <a v-if="currentImage?.url" :href="currentImage.url" target="_blank" class="text-white/70 hover:text-white bg-black/20 hover:bg-black/40 p-2 rounded-full transition-colors" title="Buka File Asli">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                        <div class="w-px h-6 bg-white/20 mx-1"></div>
                        <button @click="close" class="text-white/70 hover:text-white bg-black/20 hover:bg-black/40 p-2 rounded-full transition-colors" title="Tutup (Esc)">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Navigation -->
                <button v-if="internalIndex > 0" @click.stop="prev" class="absolute left-2 sm:left-4 p-2 sm:p-3 rounded-full bg-black/20 text-white/50 hover:bg-black/60 hover:text-white transition-colors z-10" title="Sebelumnya (Arrow Left)">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <button v-if="internalIndex < images.length - 1" @click.stop="next" class="absolute right-2 sm:right-4 p-2 sm:p-3 rounded-full bg-black/20 text-white/50 hover:bg-black/60 hover:text-white transition-colors z-10" title="Selanjutnya (Arrow Right)">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>

                <!-- Thumbnail Strip & Counter -->
                <div class="absolute bottom-6 inset-x-0 flex flex-col items-center justify-end z-20 pointer-events-none gap-3">
                    <!-- Counter -->
                    <div v-if="images.length > 1" class="px-4 py-1.5 rounded-full bg-black/50 text-white/90 text-sm font-medium tracking-wide backdrop-blur">
                        {{ internalIndex + 1 }} / {{ images.length }}
                    </div>
                    
                    <!-- Thumbnail Strip -->
                    <div v-if="images.length > 1" class="flex gap-2 p-2 bg-black/40 backdrop-blur-md rounded-xl pointer-events-auto overflow-x-auto max-w-[95vw] sm:max-w-[80vw] no-scrollbar shadow-xl border border-white/10">
                        <button v-for="(img, idx) in images" :key="idx" @click.stop="internalIndex = idx; emit('update:activeIndex', idx); resetZoom()" class="relative w-12 h-12 sm:w-16 sm:h-16 rounded-lg overflow-hidden shrink-0 border-2 transition-all focus:outline-none" :class="idx === internalIndex ? 'border-blue-500 scale-100 shadow-lg' : 'border-transparent opacity-50 hover:opacity-100 scale-95 hover:scale-100'">
                            <img :src="img.url" class="w-full h-full object-cover">
                        </button>
                    </div>
                </div>

                <!-- Image Area -->
                <div class="relative w-full h-full flex items-center justify-center p-4 sm:p-12 pb-32 cursor-zoom-out" @click.self="close" @touchstart="onTouchStart" @touchend="onTouchEnd">
                    
                    <div v-show="imgLoading && !imgError" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <svg class="animate-spin h-10 w-10 text-white/30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>

                    <div v-if="imgError" class="flex flex-col items-center gap-3 text-white/50 pointer-events-none">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <p class="font-medium">Gambar gagal dimuat</p>
                    </div>

                    <img 
                        v-if="currentImage?.url && !imgError" 
                        :src="currentImage.url" 
                        @load="imgLoading = false"
                        @error="imgError = true; imgLoading = false"
                        @click.stop="toggleZoom"
                        class="max-w-full max-h-full object-contain select-none transition-all duration-300" 
                        :class="{'opacity-0': imgLoading, 'opacity-100': !imgLoading}"
                        :style="zoomStyle"
                        alt="Preview" 
                        draggable="false"
                    />
                </div>
            </div>
        </transition>
    </teleport>
</template>
