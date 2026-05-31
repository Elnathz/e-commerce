<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    order: Object,
});

const formShip = useForm({
    tracking_number: props.order.tracking_number || '',
});

const confirmingProcess = ref(false);

const confirmProcess = () => {
    confirmingProcess.value = true;
};

const closeModal = () => {
    confirmingProcess.value = false;
};

const submitProcess = () => {
    useForm({}).post(route('admin.orders.process', props.order.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const submitShip = () => {
    formShip.post(route('admin.orders.ship', props.order.id), {
        preserveScroll: true
    });
};

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
};

const getStatusClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'paid': 'bg-blue-100 text-blue-800',
        'processing': 'bg-purple-100 text-purple-800',
        'shipped': 'bg-indigo-100 text-indigo-800',
        'completed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800',
        'refunded': 'bg-gray-100 text-gray-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const address = props.order.shipping_address_snapshot;
</script>

<template>
    <Head :title="`Detail Pesanan - ${order.order_number}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-slate-900">
                    Detail Pesanan <span class="text-blue-600">#{{ order.order_number }}</span>
                </h2>
                <a :href="route('admin.orders.print', order.id)" target="_blank" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 rounded-lg font-bold text-xs text-slate-700 uppercase tracking-widest shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak Resi
                </a>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Kiri: Detail & Item -->
                    <div class="md:col-span-2 space-y-6">
                        
                        <!-- Rincian Pelanggan & Alamat -->
                        <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 rounded-2xl p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Informasi Pengiriman</h3>
                            
                            <div v-if="address" class="space-y-2 text-sm text-slate-700">
                                <p><span class="font-semibold text-slate-900">Penerima:</span> {{ address.recipient_name }}</p>
                                <p><span class="font-semibold text-slate-900">No. HP:</span> {{ address.phone }}</p>
                                <p><span class="font-semibold text-slate-900">Alamat Lengkap:</span><br/>
                                    {{ address.address_detail }}<br/>
                                    {{ address.district }}, {{ address.city }}, {{ address.province }} {{ address.postal_code }}
                                </p>
                                <p class="mt-4"><span class="font-semibold text-slate-900">Kurir & Layanan:</span> <span class="uppercase font-bold">{{ order.courier }}</span> <span v-if="order.shipping_service"> - {{ order.shipping_service }}</span></p>
                                <p v-if="order.tracking_number"><span class="font-semibold text-slate-900">Nomor Resi:</span> <span class="font-mono bg-slate-100 px-2 py-1 rounded text-blue-700 font-bold">{{ order.tracking_number }}</span></p>
                            </div>
                            <div v-else class="text-sm text-red-500">Data alamat tidak tersedia.</div>
                        </div>

                        <!-- Daftar Item -->
                        <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 rounded-2xl p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Produk yang Dipesan</h3>
                            
                            <div class="space-y-4">
                                <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between border-b border-slate-50 pb-4 last:border-0 last:pb-0">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-900">{{ item.product_name_snapshot }}</span>
                                        <span class="text-xs text-slate-500">Varian: {{ item.variant_name_snapshot }} | Berat: {{ item.weight_gram }}g</span>
                                        <span class="text-sm text-slate-600">{{ item.quantity }} x Rp {{ formatPrice(item.unit_price) }}</span>
                                    </div>
                                    <div class="font-bold text-slate-800">
                                        Rp {{ formatPrice(item.subtotal) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Kanan: Status & Pembayaran -->
                    <div class="space-y-6">
                        
                        <!-- Panel Aksi Status -->
                        <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 rounded-2xl p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Manajemen Status</h3>
                            
                            <div class="mb-6 flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-500">Status Saat Ini:</span>
                                <span :class="['px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide', getStatusClass(order.status)]">
                                    {{ order.status }}
                                </span>
                            </div>

                            <div v-if="order.status === 'paid'" class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-800 mb-4">
                                Pembayaran telah dikonfirmasi. Anda dapat mulai memproses pesanan ini.
                                <PrimaryButton @click="confirmProcess" type="button" class="mt-3 w-full justify-center !bg-blue-600 hover:!bg-blue-700 !rounded-lg">
                                    Proses Pesanan Sekarang
                                </PrimaryButton>
                            </div>

                            <div v-if="order.status === 'processing'" class="bg-purple-50 border border-purple-100 rounded-xl p-4 mb-4">
                                <p class="text-sm text-purple-800 mb-3 font-medium">Pesanan sedang diproses. Masukkan nomor resi jika barang sudah diserahkan ke kurir.</p>
                                <form @submit.prevent="submitShip" class="space-y-3">
                                    <div>
                                        <InputLabel for="tracking_number" value="Nomor Resi Pengiriman" />
                                        <TextInput
                                            id="tracking_number"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="formShip.tracking_number"
                                            required
                                        />
                                        <InputError class="mt-2" :message="formShip.errors.tracking_number" />
                                    </div>
                                    <PrimaryButton :disabled="formShip.processing" type="submit" class="w-full justify-center !bg-purple-600 hover:!bg-purple-700 !rounded-lg">
                                        Kirim & Simpan Resi
                                    </PrimaryButton>
                                </form>
                            </div>

                            <div v-if="order.status === 'shipped'" class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-sm text-indigo-800">
                                Barang telah dikirim dengan nomor resi <span class="font-bold">{{ order.tracking_number }}</span>.<br/> Menunggu konfirmasi penerimaan dari pelanggan.
                            </div>

                            <div v-if="order.status === 'completed'" class="bg-green-50 border border-green-100 rounded-xl p-4 text-sm text-green-800">
                                Pesanan ini telah selesai dan barang telah diterima pelanggan.
                            </div>

                            <div v-if="order.status === 'cancelled' || order.status === 'refunded'" class="bg-red-50 border border-red-100 rounded-xl p-4 text-sm text-red-800">
                                Pesanan ini telah dibatalkan atau dikembalikan.
                            </div>
                        </div>

                        <!-- Rincian Pembayaran -->
                        <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 rounded-2xl p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Rincian Harga</h3>
                            
                            <div class="space-y-2 text-sm text-slate-700">
                                <div class="flex justify-between">
                                    <span>Subtotal Produk</span>
                                    <span class="font-semibold">Rp {{ formatPrice(order.subtotal) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Ongkos Kirim</span>
                                    <span class="font-semibold">Rp {{ formatPrice(order.shipping_cost) }}</span>
                                </div>
                                <div v-if="order.discount_amount > 0" class="flex justify-between text-green-600">
                                    <span>Diskon</span>
                                    <span class="font-semibold">- Rp {{ formatPrice(order.discount_amount) }}</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-slate-100 mt-2 text-base font-bold text-slate-900">
                                    <span>Total Akhir</span>
                                    <span class="text-blue-600">Rp {{ formatPrice(order.total_amount) }}</span>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100">
                                <p class="text-xs text-slate-500 mb-1">Metode Pembayaran</p>
                                <p class="text-sm font-bold text-slate-900">{{ order.payment_method || 'Belum dipilih' }}</p>
                                <p v-if="order.paid_at" class="text-xs text-slate-500 mt-1">Dibayar pada: {{ new Date(order.paid_at).toLocaleString('id-ID') }}</p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <!-- Process Confirmation Modal -->
        <Modal :show="confirmingProcess" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-2">Konfirmasi Proses Pesanan</h2>
                <p class="text-sm text-slate-600 mb-6">
                    Apakah Anda yakin ingin memproses pesanan ini? Status akan berubah menjadi <strong>Diproses</strong> dan Anda tidak bisa membatalkannya setelah ini.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">Batal</SecondaryButton>
                    <PrimaryButton @click="submitProcess" class="!bg-blue-600 hover:!bg-blue-700 text-white">
                        Ya, Proses Pesanan
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

    </AdminLayout>
</template>
