<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    order: Object,
});

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
};

const getStatusDisplay = (status) => {
    const displays = {
        'pending': 'Belum Bayar',
        'paid': 'Dibayar',
        'processing': 'Dikemas',
        'shipped': 'Dikirim',
        'completed': 'Selesai',
        'cancelled': 'Dibatalkan',
        'refunded': 'Dikembalikan',
    };
    return displays[status] || status;
};

const getStatusClass = (status) => {
    if (status === 'pending') return 'bg-yellow-100 text-yellow-800';
    if (status === 'paid' || status === 'processing') return 'bg-purple-100 text-purple-800';
    if (status === 'shipped') return 'bg-indigo-100 text-indigo-800';
    if (status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'cancelled' || status === 'refunded') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
};

const address = props.order.shipping_address_snapshot;

const confirmingReceipt = ref(false);
const confirmingCancel = ref(false);

const confirmReceipt = () => confirmingReceipt.value = true;
const closeReceiptModal = () => confirmingReceipt.value = false;

const confirmCancel = () => confirmingCancel.value = true;
const closeCancelModal = () => confirmingCancel.value = false;

const submitConfirm = () => {
    useForm({}).post(route('orders.confirm', props.order.order_number), {
        preserveScroll: true,
        onSuccess: () => closeReceiptModal(),
    });
};

const submitCancel = () => {
    useForm({}).post(route('orders.cancel', props.order.order_number), {
        preserveScroll: true,
        onSuccess: () => closeCancelModal(),
    });
};

const copyResi = async (resi) => {
    try {
        await navigator.clipboard.writeText(resi);
        alert('Nomor resi berhasil disalin: ' + resi);
    } catch (err) {
        console.error('Gagal menyalin: ', err);
    }
};
</script>

<template>
    <Head :title="`Detail Pesanan - ${order.order_number}`" />

    <StorefrontLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
                
                <div class="flex items-center gap-4">
                    <Link :href="route('orders.index')" class="text-slate-500 hover:text-blue-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="text-2xl font-bold text-slate-900">Detail Pesanan</h2>
                </div>

                <!-- Info Box -->
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Nomor Pesanan</p>
                        <p class="text-lg font-bold text-slate-900">{{ order.order_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Status</p>
                        <span :class="['inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide', getStatusClass(order.status)]">
                            {{ getStatusDisplay(order.status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Tanggal Pemesanan</p>
                        <p class="text-base font-semibold text-slate-900">{{ new Date(order.created_at).toLocaleString('id-ID') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Kiri: Produk & Info Pengiriman -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Actions & Tracking -->
                        <div v-if="order.status === 'shipped' || order.status === 'completed'" class="rounded-2xl border border-indigo-200 bg-indigo-50 shadow-sm p-6">
                            <h3 class="text-lg font-bold text-indigo-900 mb-4">Informasi Pengiriman</h3>
                            
                            <div class="bg-white rounded-xl p-4 border border-indigo-100 mb-5">
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Kurir</p>
                                        <p class="font-bold text-slate-900 uppercase">{{ order.courier }} <span v-if="order.shipping_service" class="text-indigo-600">- {{ order.shipping_service }}</span></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Nomor Resi</p>
                                        <div class="flex items-center gap-2">
                                            <p class="font-mono font-bold text-slate-900">{{ order.tracking_number }}</p>
                                            <button @click="copyResi(order.tracking_number)" class="text-indigo-600 hover:text-indigo-800 p-1 bg-indigo-50 rounded" title="Salin Resi">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <a :href="`https://cekresi.com/?noresi=${order.tracking_number}`" target="_blank" class="block w-full text-center py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-lg transition-colors">
                                    Lacak Paket di CekResi.com ↗
                                </a>
                            </div>
                            
                            <PrimaryButton v-if="order.status === 'shipped'" @click="confirmReceipt" class="w-full justify-center !bg-indigo-600 hover:!bg-indigo-700 !rounded-xl !shadow-lg !shadow-indigo-500/30">
                                Pesanan Telah Diterima
                            </PrimaryButton>
                        </div>

                        <!-- Canceled / Refunded Actions -->
                        <div v-if="order.status === 'pending'" class="rounded-2xl border border-yellow-200 bg-yellow-50 shadow-sm p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-yellow-900">Menunggu Pembayaran</h3>
                                <p class="text-sm text-yellow-800">Segera selesaikan pembayaran Anda sebelum pesanan kedaluwarsa.</p>
                            </div>
                            <Link :href="route('checkout.success', order.order_number)">
                                <PrimaryButton class="!bg-yellow-600 hover:!bg-yellow-700 !rounded-xl text-white">Bayar Sekarang</PrimaryButton>
                            </Link>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Daftar Produk</h3>
                            <div class="space-y-4">
                                <div v-for="item in order.items" :key="item.id" class="flex flex-col sm:flex-row items-start sm:items-center gap-4 border-b border-slate-50 pb-4 last:border-0 last:pb-0">
                                    <div class="flex-1">
                                        <Link 
                                            v-if="item.product_variant && item.product_variant.product"
                                            :href="route('products.show', item.product_variant.product.slug)"
                                            class="font-bold text-slate-900 text-base hover:text-blue-600 hover:underline transition-colors block"
                                        >
                                            {{ item.product_name_snapshot }}
                                        </Link>
                                        <h4 v-else class="font-bold text-slate-900 text-base">{{ item.product_name_snapshot }}</h4>
                                        <p class="text-sm text-slate-500 mt-0.5">Varian: {{ item.variant_name_snapshot }}</p>
                                        <p class="text-sm font-semibold text-slate-700 mt-1">{{ item.quantity }} x Rp {{ formatPrice(item.unit_price) }}</p>
                                    </div>
                                    <div class="flex flex-col sm:items-end gap-2">
                                        <div class="font-bold text-slate-900 text-lg">
                                            Rp {{ formatPrice(item.subtotal) }}
                                        </div>
                                        <Link 
                                            v-if="item.product_variant && item.product_variant.product"
                                            :href="route('products.show', item.product_variant.product.slug)"
                                            class="inline-flex items-center justify-center px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-blue-600 hover:border-blue-300 transition-colors shadow-sm"
                                        >
                                            Lihat Produk
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Info Pengiriman</h3>
                            <div v-if="address" class="text-sm text-slate-700 space-y-1">
                                <p class="font-bold text-slate-900 text-base mb-2">{{ address.recipient_name }} <span class="text-sm font-normal text-slate-500">({{ address.phone }})</span></p>
                                <p>{{ address.address_detail }}</p>
                                <p>{{ address.district }}, {{ address.city }}</p>
                                <p>{{ address.province }} {{ address.postal_code }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Kanan: Ringkasan Harga -->
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 sticky top-24">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Ringkasan Pembayaran</h3>
                            
                            <div class="space-y-3 text-sm text-slate-700">
                                <div class="flex justify-between">
                                    <span>Metode Pembayaran</span>
                                    <span class="font-bold text-slate-900">{{ order.payment_method || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Total Harga Produk</span>
                                    <span class="font-medium">Rp {{ formatPrice(order.subtotal) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Total Ongkos Kirim</span>
                                    <span class="font-medium">Rp {{ formatPrice(order.shipping_cost) }}</span>
                                </div>
                                <div v-if="order.discount_amount > 0" class="flex justify-between text-green-600">
                                    <span>Diskon</span>
                                    <span class="font-medium">- Rp {{ formatPrice(order.discount_amount) }}</span>
                                </div>
                                
                                <div class="flex justify-between pt-3 border-t border-slate-100 mt-2">
                                    <span class="font-bold text-slate-900 text-base">Total Belanja</span>
                                    <span class="font-bold text-blue-600 text-lg">Rp {{ formatPrice(order.total_amount) }}</span>
                                </div>
                            </div>

                            <div v-if="order.status === 'pending' || (order.status === 'paid' && order.status !== 'processing')" class="mt-6 pt-4 border-t border-slate-100">
                                <button @click="confirmCancel" class="w-full text-center text-sm font-bold text-red-600 hover:text-red-800 transition-colors">
                                    Batalkan Pesanan
                                </button>
                                <p class="text-xs text-slate-400 text-center mt-2">Pesanan hanya dapat dibatalkan jika belum diproses oleh admin.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Receipt Confirmation Modal -->
        <Modal :show="confirmingReceipt" @close="closeReceiptModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-2">Konfirmasi Penerimaan</h2>
                <p class="text-sm text-slate-600 mb-6">
                    Pastikan Anda telah menerima pesanan dalam kondisi baik dan lengkap sebelum mengonfirmasi. Setelah dikonfirmasi, Anda tidak dapat mengajukan komplain untuk pesanan ini.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeReceiptModal">Tutup</SecondaryButton>
                    <PrimaryButton @click="submitConfirm" class="!bg-indigo-600 hover:!bg-indigo-700 text-white">
                        Ya, Barang Sudah Diterima
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Cancel Confirmation Modal -->
        <Modal :show="confirmingCancel" @close="closeCancelModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-2">Batalkan Pesanan</h2>
                <p class="text-sm text-slate-600 mb-6">
                    Apakah Anda yakin ingin membatalkan pesanan ini? Aksi ini tidak dapat dibatalkan.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeCancelModal">Kembali</SecondaryButton>
                    <DangerButton @click="submitCancel">
                        Ya, Batalkan
                    </DangerButton>
                </div>
            </div>
        </Modal>

    </StorefrontLayout>
</template>
