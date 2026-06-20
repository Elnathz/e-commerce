<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  endsAt: { type: String, required: true },
});

const emit = defineEmits(['ended']);

const now = ref(Date.now());
const reduceMotion = ref(false);
let timer = null;

const endsAtMs = computed(() => new Date(props.endsAt).getTime());
const remainingMs = computed(() => Math.max(0, endsAtMs.value - now.value));
const isEnded = computed(() => remainingMs.value <= 0);

const pad = (n) => String(n).padStart(2, '0');

// Compact label: "Hh Mm" when >= 1 hour left, otherwise "MM:SS" countdown.
const label = computed(() => {
  if (isEnded.value) return 'Flash Sale berakhir';

  const totalSeconds = Math.floor(remainingMs.value / 1000);
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (hours >= 1) return `${hours}j ${pad(minutes)}m`;
  return `${pad(minutes)}:${pad(seconds)}`;
});

const stop = () => {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
};

const tick = () => {
  now.value = Date.now();
};

// Fire 'ended' exactly once, the moment the countdown crosses zero
// (covers both the already-expired-on-mount case and the live tick case).
watch(isEnded, (ended) => {
  if (ended) {
    stop();
    emit('ended');
  }
});

onMounted(() => {
  reduceMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  now.value = Date.now();
  if (isEnded.value) {
    emit('ended');
  } else {
    timer = setInterval(tick, 1000);
  }
});

onBeforeUnmount(stop);
</script>

<template>
  <span
    class="inline-flex items-center gap-1 font-semibold tabular-nums"
    :class="isEnded ? 'text-gray-400' : 'text-red-600'"
    role="timer"
    aria-live="polite"
  >
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         class="w-3.5 h-3.5 shrink-0" :class="!reduceMotion && !isEnded ? 'animate-pulse' : ''">
      <circle cx="12" cy="12" r="9" />
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3" />
    </svg>
    <span>{{ label }}</span>
  </span>
</template>
