<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const props = defineProps({
    order: Object,
    activePayment: Object,
    paymentChannels: Array,
});

// === State ===
const currentPayment = ref(props.activePayment);
const paymentChannels = ref(props.paymentChannels || []);
const selectedChannel = ref(null);
const isCreatingPayment = ref(false);
const paymentError = ref('');
const isCancelling = ref(false);
const copiedPayCode = ref(false);

// === Countdown Timer ===
const timeRemaining = ref('');
const isExpired = ref(false);
let countdownInterval = null;

const updateCountdown = () => {
    const expiry = currentPayment.value?.expired_at
        ? new Date(currentPayment.value.expired_at)
        : (props.order.expired_at ? new Date(props.order.expired_at) : null);

    if (!expiry) return;

    const now = new Date();
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

// === Current display state ===
const displayState = computed(() => {
    if (props.order.payment_status === 'paid' || currentPayment.value?.status === 'paid') {
        return 'paid';
    }
    if (props.order.status === 'cancelled') {
        return 'cancelled';
    }
    if (isExpired.value || currentPayment.value?.status === 'expired' || currentPayment.value?.status === 'gagal') {
        return 'expired';
    }
    if (currentPayment.value && currentPayment.value.status === 'pending' && currentPayment.value.pay_code) {
        return 'waiting';
    }
    return 'select'; // Select payment method
});

// === Grouped channels ===
const groupedChannels = computed(() => {
    const groups = {};
    paymentChannels.value.forEach(ch => {
        if (!groups[ch.group]) groups[ch.group] = [];
        groups[ch.group].push(ch);
    });
    return groups;
});

// === Format Price ===
const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};

// === Create Payment ===
const createPayment = async () => {
    if (!selectedChannel.value) {
        paymentError.value = 'Silakan pilih metode pembayaran.';
        return;
    }

    isCreatingPayment.value = true;
    paymentError.value = '';

    try {
        const response = await axios.post(`/payments/${props.order.order_number}/pay`, {
            payment_method: selectedChannel.value.method,
            payment_channel: selectedChannel.value.channel,
        });

        if (response.data.success) {
            currentPayment.value = response.data.payment;
            startPolling();
        } else {
            paymentError.value = response.data.message || 'Gagal membuat invoice.';
        }
    } catch (err) {
        paymentError.value = err.response?.data?.message || 'Terjadi kesalahan. Silakan coba lagi.';
    } finally {
        isCreatingPayment.value = false;
    }
};

// === Copy Pay Code ===
const copyPayCode = () => {
    if (currentPayment.value?.pay_code) {
        navigator.clipboard.writeText(currentPayment.value.pay_code);
        copiedPayCode.value = true;
        setTimeout(() => { copiedPayCode.value = false; }, 2000);
    }
};

// === Cancel Order ===
const showCancelConfirmModal = ref(false);

const requestCancelOrder = () => {
    showCancelConfirmModal.value = true;
};

const cancelOrder = () => {
    showCancelConfirmModal.value = false;
    isCancelling.value = true;
    router.post(`/orders/${props.order.order_number}/cancel`, {}, {
        onFinish: () => { isCancelling.value = false; },
    });
};

// === Status Polling ===
let pollInterval = null;

const startPolling = () => {
    if (pollInterval) return;
    pollInterval = setInterval(async () => {
        try {
            const response = await axios.get(`/payments/${props.order.order_number}/status`);
            if (response.data.success && response.data.payment) {
                const newStatus = response.data.payment.status;
                const orderStatus = response.data.payment_status;

                if (newStatus === 'paid' || orderStatus === 'paid') {
                    currentPayment.value = { ...currentPayment.value, status: 'paid', paid_at: response.data.payment.paid_at };
                    props.order.payment_status = 'paid';
                    props.order.status = 'paid';
                    stopPolling();
                } else if (newStatus === 'expired' || newStatus === 'failed') {
                    currentPayment.value = { ...currentPayment.value, status: newStatus };
                    stopPolling();
                }
            }
        } catch (e) {
            // Silent fail — will retry on next interval
        }
    }, 5000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

// === Lifecycle ===
onMounted(() => {
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);

    // Start polling if there's an active pending payment
    if (currentPayment.value?.status === 'pending' && currentPayment.value?.pay_code) {
        startPolling();
    }
});

onUnmounted(() => {
    if (countdownInterval) clearInterval(countdownInterval);
    stopPolling();
});

// Bank icon mapping
const bankIcons = {
    bca: '🏦', bni: '🏦', bri: '🏦', mandiri: '🏦', cimb: '🏦', qris: '📱',
};
</script>

<template>
    <Head title="Pembayaran Pesanan" />

    <StorefrontLayout>
        <div class="bg-gray-50 py-8 md:py-16 min-h-screen flex items-center justify-center px-4">
            <div :class="[
                'max-w-lg w-full transition-all duration-300',
                displayState === 'paid' ? '' : 'bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100'
            ]">

                <!-- ===== STATE: PAID ===== -->
                <template v-if="displayState === 'paid'">
                    <div class="relative overflow-hidden rounded-3xl shadow-2xl bg-white mb-6 border border-gray-100 transform transition-all hover:scale-[1.01] duration-300">
                        <!-- Header Gradient & Success Icon -->
                        <div class="bg-gradient-to-br from-emerald-500 via-green-500 to-teal-600 p-8 text-center text-white relative">
                            <!-- Background decoration -->
                            <div class="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
                                <svg class="absolute -top-10 -right-10 w-40 h-40 text-white" fill="currentColor" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50"/></svg>
                                <svg class="absolute -bottom-10 -left-10 w-32 h-32 text-white" fill="currentColor" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50"/></svg>
                            </div>
                            
                            <div class="w-24 h-24 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-5 animate-bounce-in shadow-[0_0_30px_rgba(255,255,255,0.3)] border border-white/40">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="white" class="w-12 h-12">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <h1 class="text-3xl font-black mb-2 tracking-tight">Pembayaran Berhasil</h1>
                            <p class="text-emerald-50 font-medium">Terima kasih! Pesanan Anda segera diproses.</p>
                        </div>

                        <!-- Receipt Body -->
                        <div class="px-6 md:px-8 py-8 bg-white relative">
                            <!-- Clean Dashed separator (Receipt effect) -->
                            <div class="absolute top-0 left-0 w-full flex items-center justify-center -mt-[2px]">
                                <div class="w-full border-t-[3px] border-dashed border-gray-200/80 mx-8"></div>
                            </div>
                            
                            <div class="mt-2 space-y-5">
                                <div class="text-center pb-6 border-b border-gray-100">
                                    <span class="block text-sm text-gray-500 font-semibold mb-1 uppercase tracking-wider">Total Pembayaran</span>
                                    <span class="text-4xl font-black text-gray-900 tracking-tight">{{ formatPrice(order.total_amount) }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center text-sm pt-2">
                                    <span class="text-gray-500 font-medium">Nomor Pesanan</span>
                                    <span class="font-bold text-gray-900 font-mono bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-100">{{ order.order_number }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-medium">Metode Pembayaran</span>
                                    <span class="font-bold text-gray-900">{{ currentPayment?.payment_name || order.payment_method || 'Transfer Bank' }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-medium">Waktu Transaksi</span>
                                    <span class="font-semibold text-gray-800">{{ currentPayment?.paid_at ? new Date(currentPayment.paid_at).toLocaleString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : 'Baru saja' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col gap-3">
                        <Link :href="'/orders/' + order.order_number" class="w-full py-4 rounded-2xl font-bold bg-gray-900 text-white hover:bg-black hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 text-center flex items-center justify-center gap-2">
                            <span>Lihat Detail Pesanan</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </Link>
                        <Link href="/" class="w-full py-4 rounded-2xl font-bold bg-white text-gray-700 border-2 border-gray-200 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 transition-all duration-300 text-center">
                            Belanja Lagi
                        </Link>
                    </div>
                </template>

                <!-- ===== STATE: WAITING PAYMENT ===== -->
                <template v-else-if="displayState === 'waiting'">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-amber-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900 mb-1">Menunggu Pembayaran</h1>
                        <p class="text-sm text-gray-500">Selesaikan pembayaran sebelum batas waktu</p>
                    </div>

                    <!-- Countdown -->
                    <div class="mb-5">
                        <div class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl text-sm font-bold bg-amber-50 text-amber-700 border border-amber-200 w-full justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span>{{ timeRemaining }}</span>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="bg-blue-50 rounded-2xl p-5 mb-5 border border-blue-100">
                        <p class="text-sm text-blue-600 font-semibold mb-1">{{ currentPayment.payment_name }}</p>

                        <!-- QRIS Image -->
                        <div v-if="currentPayment.payment_method === 'qris' && currentPayment.pay_code" class="mt-4 flex flex-col items-center">
                            <div class="bg-white p-3 rounded-2xl border border-gray-200 shadow-sm inline-block">
                                <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(currentPayment.pay_code)" alt="QRIS Code" class="w-48 h-48" />
                            </div>
                            <p class="text-xs text-gray-500 mt-3 text-center">Scan QR Code ini menggunakan aplikasi e-wallet atau M-Banking Anda.</p>
                        </div>

                        <!-- VA Number / Pay Code -->
                        <div v-else-if="currentPayment.pay_code" class="flex items-center gap-2 mt-3">
                            <div class="flex-1 bg-white rounded-xl px-4 py-3 border border-blue-200">
                                <p class="text-xs text-gray-500 mb-1">Nomor Pembayaran</p>
                                <p class="text-lg font-bold text-gray-900 font-mono tracking-wider">{{ currentPayment.pay_code }}</p>
                            </div>
                            <button @click="copyPayCode"
                                class="p-3 rounded-xl border border-blue-200 bg-white hover:bg-blue-50 transition-colors"
                                :title="copiedPayCode ? 'Tersalin!' : 'Salin'">
                                <svg v-if="!copiedPayCode" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-green-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </button>
                        </div>

                        <!-- Pay URL (for redirect channels like QRIS) -->
                        <div v-if="currentPayment.pay_url && !currentPayment.pay_code" class="mt-3">
                            <a :href="currentPayment.pay_url" target="_blank"
                                class="block w-full py-3 rounded-xl font-bold bg-blue-600 text-white hover:bg-blue-700 transition-colors text-center">
                                Buka Halaman Pembayaran
                            </a>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="bg-gray-50 rounded-2xl p-5 mb-5 border border-gray-100">
                        <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-200">
                            <span class="text-sm text-gray-500">Nomor Pesanan</span>
                            <span class="font-bold text-gray-900 font-mono text-sm">{{ order.order_number }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Total</span>
                            <span class="text-lg font-bold text-blue-600">{{ formatPrice(order.total_amount) }}</span>
                        </div>
                    </div>

                    <div class="text-center mb-2">
                        <p class="text-xs text-gray-400">Status pembayaran diperbarui otomatis</p>
                    </div>

                    <!-- Cancel -->
                    <button @click="requestCancelOrder"
                        :disabled="isCancelling"
                        class="w-full py-3 rounded-xl font-semibold text-red-500 border border-red-100 bg-red-50 hover:bg-red-100 transition-colors text-center mt-3 text-sm">
                        {{ isCancelling ? 'Membatalkan...' : 'Batalkan Pesanan' }}
                    </button>
                </template>

                <!-- ===== STATE: SELECT PAYMENT METHOD ===== -->
                <template v-else-if="displayState === 'select'">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-blue-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                            </svg>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900 mb-1">Pilih Metode Pembayaran</h1>
                        <p class="text-sm text-gray-500">Pesanan #{{ order.order_number }}</p>
                    </div>

                    <!-- Countdown -->
                    <div v-if="order.expired_at && !isExpired" class="mb-5">
                        <div class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 w-full justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span>Bayar sebelum {{ timeRemaining }}</span>
                        </div>
                    </div>

                    <!-- Total Amount -->
                    <div class="bg-gray-50 rounded-2xl p-4 mb-5 border border-gray-100 flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total Pembayaran</span>
                        <span class="text-xl font-bold text-gray-900">{{ formatPrice(order.total_amount) }}</span>
                    </div>

                    <!-- Error -->
                    <div v-if="paymentError" class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-600">
                        {{ paymentError }}
                    </div>

                    <!-- Channel List -->
                    <div v-for="(channels, groupName) in groupedChannels" :key="groupName" class="mb-4">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 px-1">{{ groupName }}</p>
                        <div class="space-y-2">
                            <button v-for="ch in channels" :key="ch.channel"
                                @click="selectedChannel = ch; paymentError = ''"
                                :class="[
                                    'w-full flex items-center gap-3 p-3.5 rounded-xl border-2 transition-all text-left',
                                    selectedChannel?.channel === ch.channel
                                        ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-200'
                                        : 'border-gray-100 bg-white hover:border-gray-200 hover:bg-gray-50'
                                ]">
                                <span class="text-2xl">{{ bankIcons[ch.channel] || '💳' }}</span>
                                <span class="flex-1">
                                    <span class="text-sm font-semibold text-gray-900">{{ ch.name }}</span>
                                </span>
                                <span v-if="selectedChannel?.channel === ch.channel" class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="white" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Pay Button -->
                    <button @click="createPayment"
                        :disabled="!selectedChannel || isCreatingPayment"
                        :class="[
                            'w-full py-3.5 rounded-xl font-bold transition-all text-center mt-2',
                            selectedChannel && !isCreatingPayment
                                ? 'bg-blue-600 text-white hover:bg-blue-700 active:scale-[0.98]'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                        ]">
                        <span v-if="isCreatingPayment" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Memproses...
                        </span>
                        <span v-else>Bayar Sekarang</span>
                    </button>

                    <!-- Cancel -->
                    <button @click="requestCancelOrder"
                        :disabled="isCancelling"
                        class="w-full py-3 rounded-xl font-semibold text-red-500 border border-red-100 bg-red-50 hover:bg-red-100 transition-colors text-center mt-3 text-sm">
                        {{ isCancelling ? 'Membatalkan...' : 'Batalkan Pesanan' }}
                    </button>
                </template>

                <!-- ===== STATE: EXPIRED / CANCELLED ===== -->
                <template v-else>
                    <div class="text-center">
                        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-red-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">
                            {{ displayState === 'expired' ? 'Waktu Pembayaran Habis' : 'Pesanan Dibatalkan' }}
                        </h1>
                        <p class="text-gray-500 mb-6">
                            {{ displayState === 'expired'
                                ? 'Batas waktu pembayaran telah berakhir. Silakan buat pesanan baru.'
                                : 'Pesanan ini telah dibatalkan.'
                            }}
                        </p>

                        <div class="bg-gray-50 rounded-2xl p-5 mb-6 border border-gray-100">
                            <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-200">
                                <span class="text-sm text-gray-500">Nomor Pesanan</span>
                                <span class="font-bold text-gray-900 font-mono text-sm">{{ order.order_number }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-500">Status</span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    {{ displayState === 'expired' ? 'Kedaluwarsa' : 'Dibatalkan' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3">
                            <Link href="/" class="w-full py-3.5 rounded-xl font-bold bg-blue-600 text-white hover:bg-blue-700 transition-colors text-center">
                                Belanja Lagi
                            </Link>
                        </div>
                    </div>
                </template>

                <!-- ===== ORDER ITEMS (shown on select & waiting states) ===== -->
                <div v-if="(displayState === 'select' || displayState === 'waiting') && order.items && order.items.length > 0"
                    class="mt-5 bg-gray-50 rounded-2xl p-4 border border-gray-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Detail Pesanan</p>
                    <div class="space-y-2">
                        <div v-for="item in order.items" :key="item.id" class="flex justify-between text-sm">
                            <span class="text-gray-600">
                                {{ item.product_name_snapshot }}
                                <span v-if="item.variant_name_snapshot" class="text-gray-400">({{ item.variant_name_snapshot }})</span>
                                <span class="text-gray-400">×{{ item.quantity }}</span>
                            </span>
                            <span class="font-semibold text-gray-900">{{ formatPrice(item.subtotal) }}</span>
                        </div>
                        <div v-if="order.shipping_cost > 0" class="flex justify-between text-sm pt-2 border-t border-gray-200">
                            <span class="text-gray-500">Ongkir</span>
                            <span class="font-semibold text-gray-900">{{ formatPrice(order.shipping_cost) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Order Confirmation Modal -->
        <Modal :show="showCancelConfirmModal" @close="showCancelConfirmModal = false" maxWidth="md">
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Batalkan Pesanan?</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Apakah Anda yakin ingin membatalkan pesanan ini? Tindakan ini bersifat permanen dan tidak dapat dibatalkan.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-3">
                    <button type="button" @click="showCancelConfirmModal = false"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-gray-200 text-gray-700 font-semibold hover:bg-gray-50 transition-colors text-sm">
                        Batal
                    </button>
                    <button type="button" @click="cancelOrder"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold transition-colors text-sm">
                        Ya, Batalkan Pesanan
                    </button>
                </div>
            </div>
        </Modal>
    </StorefrontLayout>
</template>

<style scoped>
@keyframes bounce-in {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.05); }
    70% { transform: scale(0.9); }
    100% { transform: scale(1); opacity: 1; }
}
.animate-bounce-in {
    animation: bounce-in 0.6s ease-out;
}
</style>
