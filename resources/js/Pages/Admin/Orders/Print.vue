<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    order: Object,
});

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
};

const address = props.order.shipping_address_snapshot;

onMounted(() => {
    // Automatically trigger print dialog when page loads
    setTimeout(() => {
        window.print();
    }, 500);
});
</script>

<template>
    <Head :title="`Cetak Resi - ${order.order_number}`" />

    <div class="bg-white text-black font-sans min-h-screen p-4 sm:p-8 max-w-3xl mx-auto print:p-0 print:max-w-none">
        
        <!-- Header Invoice -->
        <div class="flex justify-between items-start border-b-2 border-black pb-4 mb-4">
            <div>
                <h1 class="text-2xl font-black uppercase tracking-tight">LABEL PENGIRIMAN</h1>
                <p class="text-sm font-bold mt-1">MegaMart E-Commerce</p>
                <p class="text-xs">Jl. Pemuda No. 123, Semarang, Jawa Tengah</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold">Kurir: <span class="uppercase text-lg">{{ order.courier }}</span></p>
                <p class="text-xs mt-1">Order: {{ order.order_number }}</p>
                <p class="text-xs">Tgl: {{ new Date(order.created_at).toLocaleDateString('id-ID') }}</p>
            </div>
        </div>

        <!-- Alamat Pengiriman -->
        <div class="border-2 border-black p-4 mb-6 rounded-lg">
            <h2 class="text-xs font-bold uppercase border-b border-gray-300 pb-1 mb-2">Tujuan Pengiriman</h2>
            <div v-if="address">
                <p class="font-bold text-lg">{{ address.recipient_name }}</p>
                <p class="font-bold text-md mt-1">{{ address.phone }}</p>
                <p class="text-sm mt-2 leading-relaxed">
                    {{ address.address_detail }}<br/>
                    {{ address.district }}, {{ address.city }}<br/>
                    {{ address.province }} {{ address.postal_code }}
                </p>
            </div>
        </div>

        <!-- Daftar Barang -->
        <div>
            <h2 class="text-xs font-bold uppercase border-b border-gray-300 pb-1 mb-2">Daftar Barang (Total Berat: {{ order.items.reduce((sum, item) => sum + (item.weight_gram * item.quantity), 0) }}g)</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-black">
                    <tr>
                        <th class="py-2">No</th>
                        <th class="py-2">Nama Produk</th>
                        <th class="py-2 text-center">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in order.items" :key="item.id" class="border-b border-gray-200">
                        <td class="py-2">{{ index + 1 }}</td>
                        <td class="py-2">
                            <span class="font-bold">{{ item.product_name_snapshot }}</span><br/>
                            <span class="text-xs">Varian: {{ item.variant_name_snapshot }}</span>
                        </td>
                        <td class="py-2 text-center font-bold">{{ item.quantity }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-8 text-center text-xs text-gray-500 print:block hidden">
            Dicetak pada: {{ new Date().toLocaleString('id-ID') }}
        </div>
        
        <!-- Print Button for web view -->
        <div class="mt-8 text-center print:hidden">
            <button @click="window.print()" class="px-6 py-2 bg-black text-white font-bold rounded hover:bg-gray-800">
                Cetak Sekarang
            </button>
            <p class="text-xs text-gray-500 mt-2">Atur margin ke "None" dan hapus "Headers and footers" pada dialog cetak.</p>
        </div>

    </div>
</template>

<style>
/* Reset body background for print view */
@page {
    margin: 0.5cm;
}
@media print {
    body {
        background-color: white !important;
    }
}
</style>
