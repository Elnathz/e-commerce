<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineProps({
    order: Object
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};
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

                <h1 class="text-2xl font-bold text-gray-900 mb-2">Pesanan Berhasil!</h1>
                <p class="text-gray-500 mb-6">Terima kasih telah berbelanja di MegaMart. Pesanan Anda sedang kami proses.</p>

                <div class="bg-gray-50 rounded-2xl p-5 mb-8 text-left border border-gray-100">
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                        <span class="text-sm text-gray-500">Nomor Pesanan</span>
                        <span class="font-bold text-gray-900">{{ order.order_number }}</span>
                    </div>
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                        <span class="text-sm text-gray-500">Metode</span>
                        <span class="font-semibold text-gray-900">{{ order.fulfillment_type === 'delivery' ? 'Dikirim' : 'Ambil di Toko' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total Pembayaran</span>
                        <span class="text-lg font-bold text-blue-600">{{ formatPrice(order.total_amount) }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <Link :href="route('dashboard')" class="w-full py-3.5 rounded-xl font-bold bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                        Lihat Status Pesanan
                    </Link>
                    <Link href="/" class="w-full py-3.5 rounded-xl font-bold text-blue-600 border border-blue-100 bg-blue-50 hover:bg-blue-100 transition-colors">
                        Kembali ke Beranda
                    </Link>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
