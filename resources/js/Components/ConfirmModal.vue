<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: 'Apakah Anda yakin?' },
    confirmText: { type: String, default: 'Hapus' },
    cancelText: { type: String, default: 'Batal' },
    danger: { type: Boolean, default: true },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="show" max-width="sm" @close="emit('cancel')">
        <div class="p-6">
            <h3 class="text-lg font-bold text-slate-900">{{ title }}</h3>
            <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="emit('cancel')"
                        class="px-4 py-2 text-sm font-bold text-slate-600 hover:text-slate-800 cursor-pointer">
                    {{ cancelText }}
                </button>
                <button type="button" @click="emit('confirm')"
                        class="px-4 py-2 text-sm font-bold text-white rounded-lg shadow-sm cursor-pointer"
                        :class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'">
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </Modal>
</template>
