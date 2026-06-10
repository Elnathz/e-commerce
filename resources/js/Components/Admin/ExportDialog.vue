<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const filters = ref({
    start_date: '',
    end_date: '',
    status: '',
});

const isSubmitting = ref(false);
const errorMsg = ref('');
const suggestionMsg = ref('');
const successMsg = ref('');
const jobId = ref(null);

const startExport = async () => {
    isSubmitting.value = true;
    errorMsg.value = '';
    suggestionMsg.value = '';
    successMsg.value = '';
    jobId.value = null;

    try {
        const response = await axios.post(route('admin.exports.start'), filters.value);
        successMsg.value = response.data.message;
        jobId.value = response.data.job_id;
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errorMsg.value = error.response.data.message || 'Validasi gagal.';
            suggestionMsg.value = error.response.data.suggestion || '';
        } else {
            errorMsg.value = 'Terjadi kesalahan sistem.';
        }
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    emit('close');
    setTimeout(() => {
        filters.value = { start_date: '', end_date: '', status: '' };
        errorMsg.value = '';
        suggestionMsg.value = '';
        successMsg.value = '';
    }, 300);
};
</script>

<template>
    <Modal :show="show" @close="close" maxWidth="md">
        <div class="p-6 bg-white">
            <h2 class="text-xl font-bold text-gray-800 border-b pb-3 mb-4">
                Export Data Pesanan
            </h2>

            <div class="mt-4 space-y-4">
                <div v-if="successMsg" class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                    <span class="font-medium">Berhasil!</span> {{ successMsg }}
                    <div class="mt-2 text-xs">
                        <a :href="route('admin.exports.index')" target="_blank" class="underline font-bold text-green-700">Lihat riwayat export &rarr;</a>
                    </div>
                </div>

                <div v-if="errorMsg" class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                    <span class="font-medium">Gagal!</span> {{ errorMsg }}
                    <div v-if="suggestionMsg" class="mt-1 text-xs italic">{{ suggestionMsg }}</div>
                </div>

                <div v-if="!successMsg" class="space-y-5">
                    <div>
                        <label class="block font-semibold text-sm text-gray-700 mb-1">Tanggal Mulai</label>
                        <input type="date" v-model="filters.start_date" class="block w-full border-gray-300 bg-white text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm transition">
                    </div>
                    
                    <div>
                        <label class="block font-semibold text-sm text-gray-700 mb-1">Tanggal Akhir</label>
                        <input type="date" v-model="filters.end_date" class="block w-full border-gray-300 bg-white text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm transition">
                    </div>
                    
                    <div>
                        <label class="block font-semibold text-sm text-gray-700 mb-1">Status Order</label>
                        <select v-model="filters.status" class="block w-full border-gray-300 bg-white text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm transition">
                            <option value="">Semua Status</option>
                            <option value="completed">Completed</option>
                            <option value="paid">Paid</option>
                            <option value="processing">Processing</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="close">
                    Tutup
                </SecondaryButton>

                <PrimaryButton
                    v-if="!successMsg"
                    class="ms-3"
                    :class="{ 'opacity-25': isSubmitting }"
                    :disabled="isSubmitting"
                    @click="startExport"
                >
                    Mulai Export
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
