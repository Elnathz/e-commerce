<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const props = defineProps({
    order: Object
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};

// Countdown timer for payment deadline
const timeRemaining = ref('');
const isExpired = ref(false);
let countdownInterval = null;

const updateCountdown = () => {
    if (!props.order.expired_at) return;

    const now = new Date();
    const expiry = new Date(props.order.expired_at);
    const diff = expiry - now;

    if (diff <= 0) {
        isExpired.value = true;
        timeRemaining.value = 'Batas waktu habis';
        clearInterval(countdownInterval);
        return;
    }

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    timeRemaining.value = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

onMounted(() => {
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
});

onUnmounted(() => {
    if (countdownInterval) clearInterval(countdownInterval);
});
</script>

<template>
    <Head title="Pesanan Berhasil" />

    <StorefrontLayout>
        <div class="bg-gray-50 py-12 min-h-[70vh] flex items-center justify-center px-4">
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 max-w-lg w-full text-center">

                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-green-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>

                <h1 class="text-2xl font-bold text-gray-900 mb-2">Pesanan Berhasil Dibuat!</h1>
                <p class="text-gray-500 mb-6">Silakan selesaikan pembayaran sebelum batas waktu berakhir.</p>

                <!-- Countdown Timer -->
                <div v-if="order.expired_at" class="mb-6">
                    <div :class="['inline-flex items-center gap-2 px-5 py-3 rounded-2xl text-sm font-bold', isExpired ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-amber-50 text-amber-700 border border-amber-200']">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <span v-if="!isExpired">Batas waktu: {{ timeRemaining }}</span>
                        <span v-else>Batas waktu pembayaran telah habis</span>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="bg-gray-50 rounded-2xl p-5 mb-8 text-left border border-gray-100">
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                        <span class="text-sm text-gray-500">Nomor Pesanan</span>
                        <span class="font-bold text-gray-900 font-mono">{{ order.order_number }}</span>
                    </div>
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                        <span class="text-sm text-gray-500">Metode</span>
                        <span class="font-semibold text-gray-900">{{ order.fulfillment_type === 'delivery' ? 'Dikirim' : 'Ambil di Toko' }}</span>
                    </div>
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                        <span class="text-sm text-gray-500">Status</span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Menunggu Pembayaran
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total Pembayaran</span>
                        <span class="text-lg font-bold text-blue-600">{{ formatPrice(order.total_amount) }}</span>
                    </div>
                </div>

                <!-- Order Items Preview -->
                <div v-if="order.items && order.items.length > 0" class="bg-gray-50 rounded-2xl p-5 mb-8 text-left border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-3">Item Pesanan</h3>
                    <div class="space-y-2">
                        <div v-for="item in order.items" :key="item.id" class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ item.product_name_snapshot }} <span v-if="item.variant_name_snapshot" class="text-gray-400">({{ item.variant_name_snapshot }})</span> x{{ item.quantity }}</span>
                            <span class="font-semibold text-gray-900">{{ formatPrice(item.subtotal) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <Link href="/" class="w-full py-3.5 rounded-xl font-bold bg-blue-600 text-white hover:bg-blue-700 transition-colors text-center">
                        Kembali ke Beranda
                    </Link>
                    <Link href="/" class="w-full py-3.5 rounded-xl font-bold text-blue-600 border border-blue-100 bg-blue-50 hover:bg-blue-100 transition-colors text-center">
                        Lanjut Belanja
                    </Link>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
