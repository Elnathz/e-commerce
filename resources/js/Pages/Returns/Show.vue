<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    returnRequest: Object,
});

const getStatusDisplay = (status) => {
    const displays = {
        'submitted': 'Menunggu Persetujuan',
        'approved': 'Disetujui',
        'rejected': 'Ditolak',
        'returned': 'Dikirim Pembeli',
        'received': 'Diterima Admin',
        'refund_processed': 'Refund Diproses',
        'completed': 'Selesai',
        'cancelled': 'Dibatalkan',
        'expires': 'Kedaluwarsa'
    };
    return displays[status] || status;
};

const getStatusClass = (status) => {
    if (status === 'submitted') return 'bg-yellow-100 text-yellow-800';
    if (status === 'approved') return 'bg-blue-100 text-blue-800';
    if (status === 'returned' || status === 'received') return 'bg-indigo-100 text-indigo-800';
    if (status === 'refund_processed' || status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'rejected' || status === 'cancelled' || status === 'expires') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
};

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
};

const trackingForm = useForm({
    return_courier: '',
    return_tracking_number: '',
});

const submitTracking = () => {
    trackingForm.post(route('returns.tracking', props.returnRequest.return_number), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Detail Pengembalian - ${returnRequest.return_number}`" />

    <StorefrontLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                
                <div class="flex items-center gap-4">
                    <Link :href="route('orders.show', returnRequest.order.order_number)" class="text-slate-500 hover:text-blue-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="text-2xl font-bold text-slate-900">Detail Pengembalian</h2>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Nomor Retur</p>
                        <p class="text-lg font-bold text-slate-900">{{ returnRequest.return_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Nomor Pesanan</p>
                        <Link :href="route('orders.show', returnRequest.order.order_number)" class="text-base font-bold text-blue-600 hover:underline">
                            {{ returnRequest.order.order_number }}
                        </Link>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Status</p>
                        <span :class="['inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide', getStatusClass(returnRequest.status)]">
                            {{ getStatusDisplay(returnRequest.status) }}
                        </span>
                    </div>
                </div>

                <!-- Alert for Action Required -->
                <div v-if="returnRequest.status === 'approved'" class="rounded-xl border border-blue-200 bg-blue-50 p-6 flex flex-col gap-4">
                    <div>
                        <h3 class="font-bold text-blue-900 mb-1">Pengajuan Disetujui!</h3>
                        <p class="text-sm text-blue-800">
                            Mohon segera kembalikan barang ke alamat gudang kami sebelum <strong>{{ new Date(returnRequest.expires_at).toLocaleDateString('id-ID') }}</strong>. 
                            Setelah mengirim barang, masukkan informasi kurir dan nomor resi di bawah ini.
                        </p>
                    </div>
                    <form @submit.prevent="submitTracking" class="bg-white p-4 rounded-lg border border-blue-100 flex flex-col sm:flex-row gap-4 items-end">
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Kurir (Misal: JNE, J&T)</label>
                            <input type="text" v-model="trackingForm.return_courier" class="w-full text-sm border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg" required>
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Nomor Resi</label>
                            <input type="text" v-model="trackingForm.return_tracking_number" class="w-full text-sm border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg" required>
                        </div>
                        <PrimaryButton type="submit" class="w-full sm:w-auto h-[42px] whitespace-nowrap" :disabled="trackingForm.processing">
                            Simpan Resi
                        </PrimaryButton>
                    </form>
                </div>

                <!-- Admin Notes -->
                <div v-if="returnRequest.admin_notes" class="rounded-xl border border-slate-200 bg-slate-50 p-6">
                    <h3 class="text-sm font-bold text-slate-900 mb-2">Catatan Admin</h3>
                    <p class="text-sm text-slate-700">{{ returnRequest.admin_notes }}</p>
                </div>

                <!-- Refund Info -->
                <div v-if="returnRequest.refund_amount" class="rounded-xl border border-green-200 bg-green-50 p-6">
                    <h3 class="text-sm font-bold text-green-900 mb-1">Informasi Pengembalian Dana (Refund)</h3>
                    <p class="text-sm text-green-800 mb-2">Dana yang akan / telah dikembalikan ke rekening Anda.</p>
                    <p class="text-2xl font-bold text-green-700">Rp {{ formatPrice(returnRequest.refund_amount) }}</p>
                </div>

                <!-- Return Details -->
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">Alasan Pengembalian</h3>
                        <p class="text-sm text-slate-700 mt-2">{{ returnRequest.reason }}</p>
                        <p class="text-xs text-slate-500 mt-2 font-medium">Tipe: {{ returnRequest.is_partial ? 'Sebagian Barang' : 'Seluruh Pesanan' }}</p>
                    </div>
                    
                    <div class="p-6 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900 mb-4">Foto Bukti</h3>
                        <div class="flex flex-wrap gap-4">
                            <a v-if="returnRequest.evidence_image_1" :href="`/storage/${returnRequest.evidence_image_1}`" target="_blank">
                                <img :src="`/storage/${returnRequest.evidence_image_1}`" class="w-24 h-24 object-cover rounded-lg border border-slate-200 hover:opacity-80">
                            </a>
                            <a v-if="returnRequest.evidence_image_2" :href="`/storage/${returnRequest.evidence_image_2}`" target="_blank">
                                <img :src="`/storage/${returnRequest.evidence_image_2}`" class="w-24 h-24 object-cover rounded-lg border border-slate-200 hover:opacity-80">
                            </a>
                            <a v-if="returnRequest.evidence_image_3" :href="`/storage/${returnRequest.evidence_image_3}`" target="_blank">
                                <img :src="`/storage/${returnRequest.evidence_image_3}`" class="w-24 h-24 object-cover rounded-lg border border-slate-200 hover:opacity-80">
                            </a>
                        </div>
                    </div>
                    
                    <div class="p-6 bg-slate-50">
                        <h3 class="font-bold text-slate-900 mb-4">Barang dalam Pesanan</h3>
                        <div class="space-y-4">
                            <div v-for="item in returnRequest.order.items" :key="item.id" class="flex gap-4">
                                <div class="flex-1">
                                    <h4 class="font-bold text-slate-900 text-sm">{{ item.product_name_snapshot }}</h4>
                                    <p class="text-xs text-slate-500">Varian: {{ item.variant_name_snapshot }}</p>
                                    <p class="text-xs font-semibold text-slate-700 mt-1">{{ item.quantity }} x Rp {{ formatPrice(item.unit_price) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </StorefrontLayout>
</template>
