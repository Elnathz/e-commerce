<script setup>
import { computed } from 'vue';
import FlashCountdown from '@/Components/Storefront/FlashCountdown.vue';
import BoltIcon from '@/Components/Storefront/Icons/BoltIcon.vue';

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
    badge: 'text-[10px]',
  },
  lg: {
    effective: 'text-2xl md:text-3xl font-extrabold',
    original: 'text-sm',
    badge: 'text-xs',
  },
};

const sizeClasses = computed(() => SIZE_CLASSES[props.size] || SIZE_CLASSES.sm);
const isFlash = computed(() => props.info.source === 'flash');
</script>
<template>
  <div>
    <!-- Effective price. Flash deals render red to read as urgent. -->
    <div :class="['tabular-nums', sizeClasses.effective, isFlash ? 'text-red-600' : 'text-gray-900']">{{ fmt(info.effective) }}</div>

    <div v-if="info.source === 'manual'" class="flex items-center gap-1.5 mt-0.5">
      <span :class="['text-gray-400 line-through tabular-nums', sizeClasses.original]">{{ fmt(info.original) }}</span>
      <span :class="['font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded', sizeClasses.badge]">−{{ info.discount_percent }}%</span>
    </div>

    <template v-else-if="isFlash">
      <!-- Detail page (lg): full Flash Sale badge + live countdown. -->
      <div v-if="size === 'lg'" class="mt-1">
        <div class="flex items-center gap-1.5">
          <span :class="['inline-flex items-center gap-1 font-bold text-white bg-red-600 px-2 py-0.5 rounded uppercase tracking-wide', sizeClasses.badge]">
            <BoltIcon class="w-3 h-3" /> Flash Sale
          </span>
          <FlashCountdown v-if="info.flash_ends_at" :ends-at="info.flash_ends_at" />
        </div>
        <span :class="['text-gray-400 line-through tabular-nums block mt-1', sizeClasses.original]">{{ fmt(info.original) }}</span>
      </div>
      <!-- Card (sm): compact strikethrough + percent. The countdown lives once
           on the section header, so it is intentionally omitted here. -->
      <div v-else class="flex items-center gap-1.5 mt-0.5">
        <span :class="['text-gray-400 line-through tabular-nums', sizeClasses.original]">{{ fmt(info.original) }}</span>
        <span :class="['font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded', sizeClasses.badge]">−{{ info.discount_percent }}%</span>
      </div>
    </template>

    <div v-else-if="info.flash_status === 'sold_out'" class="mt-0.5">
      <span :class="['font-bold text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded uppercase tracking-wide', sizeClasses.badge]">Flash Sale Habis</span>
    </div>
  </div>
</template>
