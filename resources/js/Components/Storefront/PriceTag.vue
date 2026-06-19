<script setup>
import { computed } from 'vue';

const props = defineProps({
  info: { type: Object, required: true },
  size: { type: String, default: 'sm' },
});

const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);

// Class map keyed on size: each variant scales effective price, original
// strikethrough, and discount badge together so the whole tag stays
// proportional (sm = product cards, lg = product detail page).
const SIZE_CLASSES = {
  sm: {
    effective: 'text-base font-bold',
    original: 'text-xs',
    badge: 'text-[11px]',
  },
  lg: {
    effective: 'text-2xl md:text-3xl font-extrabold',
    original: 'text-sm',
    badge: 'text-xs',
  },
};

const sizeClasses = computed(() => SIZE_CLASSES[props.size] || SIZE_CLASSES.sm);
</script>
<template>
  <div>
    <div :class="['text-gray-900 tabular-nums', sizeClasses.effective]">{{ fmt(info.effective) }}</div>
    <div v-if="info.source === 'manual'" class="flex items-center gap-1.5 mt-0.5">
      <span :class="['text-gray-400 line-through tabular-nums', sizeClasses.original]">{{ fmt(info.original) }}</span>
      <span :class="['font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded', sizeClasses.badge]">−{{ info.discount_percent }}%</span>
    </div>
    <!-- cabang 'flash' ditambah Fase 2 -->
  </div>
</template>
