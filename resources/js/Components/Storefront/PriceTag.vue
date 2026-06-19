<script setup>
const props = defineProps({ info: { type: Object, required: true } });
const fmt = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);
</script>
<template>
  <div>
    <div class="text-base font-bold text-gray-900 tabular-nums">{{ fmt(info.effective) }}</div>
    <div v-if="info.source === 'manual'" class="flex items-center gap-1.5 mt-0.5">
      <span class="text-xs text-gray-400 line-through tabular-nums">{{ fmt(info.original) }}</span>
      <span class="text-[11px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded">−{{ info.discount_percent }}%</span>
    </div>
    <!-- cabang 'flash' ditambah Fase 2 -->
  </div>
</template>
