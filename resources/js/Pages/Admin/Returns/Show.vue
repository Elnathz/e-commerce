<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

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
        'refund_processed': 'Refund Selesai',
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

// Modals state
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const showReceiveModal = ref(false);
const showRefundModal = ref(false);

// Forms
const approveForm = useForm({
    refund_amount: props.returnRequest.order.total_amount, // default to full order amount
});

const rejectForm = useForm({
    admin_notes: '',
});

const submitApprove = () => {
    approveForm.post(route('admin.returns.approve', props.returnRequest.id), {
        onSuccess: () => showApproveModal.value = false,
    });
};

const submitReject = () => {
    rejectForm.post(route('admin.returns.reject', props.returnRequest.id), {
        onSuccess: () => showRejectModal.value = false,
    });
};

const submitReceive = () => {
    useForm({}).post(route('admin.returns.receive', props.returnRequest.id), {
        onSuccess: () => showReceiveModal.value = false,
    });
};

const submitRefund = () => {
    useForm({}).post(route('admin.returns.refund', props.returnRequest.id), {
        onSuccess: () => showRefundModal.value = false,
    });
};

</script>

<template>
    <Head :title="`Detail Retur - ${returnRequest.return_number}`" />

    <AdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Retur: {{ returnRequest.return_number }}</h2>
                <Link :href="route('admin.returns.index')" class="text-sm text-indigo-600 hover:text-indigo-900">
                    &larr; Kembali ke Daftar
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Status & Action Banner -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4" :class="{
                    'border-yellow-400': returnRequest.status === 'submitted',
                    'border-blue-400': returnRequest.status === 'approved',
                    'border-indigo-400': returnRequest.status === 'returned',
                    'border-indigo-600': returnRequest.status === 'received',
                    'border-green-400': returnRequest.status === 'refund_processed',
                    'border-red-400': returnRequest.status === 'rejected'
                }">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Status: <span :class="getStatusClass(returnRequest.status)" class="px-3 py-1 rounded-full text-sm ml-2">{{ getStatusDisplay(returnRequest.status) }}</span></h3>
                            <p class="text-sm text-gray-500 mt-2">Dibuat pada: {{ new Date(returnRequest.created_at).toLocaleString('id-ID') }}</p>
                        </div>
                        
                        <div class="flex flex-wrap gap-2">
                            <!-- Actions for 'submitted' -->
                            <template v-if="returnRequest.status === 'submitted'">
                                <DangerButton @click="showRejectModal = true">Tolak Pengajuan</DangerButton>
                                <PrimaryButton @click="showApproveModal = true" class="!bg-green-600 hover:!bg-green-700">Setujui Pengajuan</PrimaryButton>
                            </template>
                            
                            <!-- Actions for 'returned' -->
                            <template v-if="returnRequest.status === 'returned'">
                                <PrimaryButton @click="showReceiveModal = true" class="!bg-indigo-600 hover:!bg-indigo-700">Tandai Barang Diterima</PrimaryButton>
                            </template>

                            <!-- Actions for 'received' -->
                            <template v-if="returnRequest.status === 'received'">
                                <PrimaryButton @click="showRefundModal = true" class="!bg-green-600 hover:!bg-green-700">Proses Pengembalian Dana</PrimaryButton>
                            </template>
                        </div>
                    </div>

                    <!-- Tracking Info if shipped by customer -->
                    <div v-if="returnRequest.return_tracking_number" class="mt-4 pt-4 border-t border-gray-100 flex gap-4 text-sm bg-gray-50 p-4 rounded-md">
                        <div>
                            <span class="text-gray-500">Kurir Pengembalian:</span>
                            <span class="font-bold ml-2 uppercase">{{ returnRequest.return_courier }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Nomor Resi:</span>
                            <span class="font-mono font-bold ml-2">{{ returnRequest.return_tracking_number }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left: Request Details -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Informasi Komplain</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Alasan</p>
                                <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ returnRequest.reason }}</p>
                            </div>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500">Tipe Pengembalian</p>
                                <p class="mt-1 text-sm text-gray-900 font-bold">
                                    {{ returnRequest.is_partial ? 'Pengembalian Sebagian (Parsial)' : 'Pengembalian Seluruh Pesanan (Full)' }}
                                </p>
                            </div>

                            <div v-if="returnRequest.admin_notes">
                                <p class="text-sm font-medium text-gray-500">Catatan Admin</p>
                                <p class="mt-1 text-sm text-red-600 bg-red-50 p-3 rounded">{{ returnRequest.admin_notes }}</p>
                            </div>

                            <div v-if="returnRequest.refund_amount">
                                <p class="text-sm font-medium text-gray-500">Jumlah Dana Dikembalikan</p>
                                <p class="mt-1 text-lg font-bold text-green-600">Rp {{ formatPrice(returnRequest.refund_amount) }}</p>
                            </div>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500 mb-2">Foto Bukti dari Pelanggan</p>
                                <div class="flex flex-wrap gap-4">
                                    <a v-if="returnRequest.evidence_image_1" :href="`/storage/${returnRequest.evidence_image_1}`" target="_blank">
                                        <img :src="`/storage/${returnRequest.evidence_image_1}`" class="w-24 h-24 object-cover rounded border hover:opacity-75">
                                    </a>
                                    <a v-if="returnRequest.evidence_image_2" :href="`/storage/${returnRequest.evidence_image_2}`" target="_blank">
                                        <img :src="`/storage/${returnRequest.evidence_image_2}`" class="w-24 h-24 object-cover rounded border hover:opacity-75">
                                    </a>
                                    <a v-if="returnRequest.evidence_image_3" :href="`/storage/${returnRequest.evidence_image_3}`" target="_blank">
                                        <img :src="`/storage/${returnRequest.evidence_image_3}`" class="w-24 h-24 object-cover rounded border hover:opacity-75">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Order & User Details -->
                    <div class="space-y-6">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Informasi Pesanan Asli</h3>
                            
                            <div class="mb-4">
                                <p class="text-sm font-medium text-gray-500">Nomor Pesanan</p>
                                <Link :href="route('admin.orders.show', returnRequest.order.id)" class="text-indigo-600 hover:text-indigo-900 font-bold">
                                    {{ returnRequest.order.order_number }} &rarr;
                                </Link>
                            </div>

                            <div class="mb-4">
                                <p class="text-sm font-medium text-gray-500">Total Pembayaran Pesanan</p>
                                <p class="text-sm font-bold text-gray-900">Rp {{ formatPrice(returnRequest.order.total_amount) }}</p>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-500 mb-2">Barang dalam Pesanan</p>
                                <ul class="divide-y divide-gray-200">
                                    <li v-for="item in returnRequest.order.items" :key="item.id" class="py-2 flex justify-between">
                                        <div class="text-sm">
                                            <p class="font-medium text-gray-900">{{ item.product_name_snapshot }}</p>
                                            <p class="text-xs text-gray-500">Var: {{ item.variant_name_snapshot }}</p>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ item.quantity }} x Rp {{ formatPrice(item.unit_price) }}
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Informasi Pelanggan</h3>
                            <p class="text-sm font-medium text-gray-900">{{ returnRequest.user.name }}</p>
                            <p class="text-sm text-gray-500">{{ returnRequest.user.email }}</p>
                            <p class="text-sm text-gray-500 mt-2">Didaftarkan pada: {{ new Date(returnRequest.user.created_at).toLocaleDateString('id-ID') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <Modal :show="showApproveModal" @close="showApproveModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Setujui Pengajuan Retur</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Dengan menyetujui, pelanggan akan diminta untuk mengirimkan barang kembali ke gudang. Silakan tentukan estimasi jumlah dana yang akan dikembalikan.
                </p>
                <form @submit.prevent="submitApprove">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Jumlah Dana (Rp)</label>
                        <input type="number" v-model="approveForm.refund_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required min="0">
                        <p v-if="approveForm.errors.refund_amount" class="text-sm text-red-600 mt-1">{{ approveForm.errors.refund_amount }}</p>
                        <p class="text-xs text-gray-500 mt-1">Total Pesanan Asli: Rp {{ formatPrice(returnRequest.order.total_amount) }}</p>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <SecondaryButton type="button" @click="showApproveModal = false">Batal</SecondaryButton>
                        <PrimaryButton type="submit" class="!bg-green-600 hover:!bg-green-700" :disabled="approveForm.processing">Setujui Retur</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Reject Modal -->
        <Modal :show="showRejectModal" @close="showRejectModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Tolak Pengajuan Retur</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Berikan alasan yang jelas mengapa pengajuan retur/komplain ini ditolak. Alasan akan dapat dilihat oleh pelanggan.
                </p>
                <form @submit.prevent="submitReject">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Alasan Penolakan</label>
                        <textarea v-model="rejectForm.admin_notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required></textarea>
                        <p v-if="rejectForm.errors.admin_notes" class="text-sm text-red-600 mt-1">{{ rejectForm.errors.admin_notes }}</p>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <SecondaryButton type="button" @click="showRejectModal = false">Batal</SecondaryButton>
                        <DangerButton type="submit" :disabled="rejectForm.processing">Tolak Retur</DangerButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Receive Confirmation Modal -->
        <Modal :show="showReceiveModal" @close="showReceiveModal = false" maxWidth="md">
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 text-indigo-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.008 1.24l.885 1.77a2.25 2.25 0 0 0 2.007 1.24h1.98a2.25 2.25 0 0 0 2.007-1.24l.885-1.77a2.25 2.25 0 0 1 2.007-1.24h3.86m-18 0h18" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Konfirmasi Terima Barang</h3>
                <p class="text-sm text-gray-600 mb-6">
                    Apakah Anda yakin barang retur dengan nomor <strong>{{ returnRequest.return_number }}</strong> telah diterima dengan baik di gudang?
                </p>
                <div class="flex justify-center gap-3">
                    <SecondaryButton type="button" @click="showReceiveModal = false" class="px-5">Batal</SecondaryButton>
                    <PrimaryButton type="button" @click="submitReceive" class="!bg-indigo-600 hover:!bg-indigo-700 px-5">Ya, Diterima</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Refund Confirmation Modal -->
        <Modal :show="showRefundModal" @close="showRefundModal = false" maxWidth="md">
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Konfirmasi Pengembalian Dana</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Anda akan memproses pengembalian dana sebesar <span class="font-bold text-green-600">Rp {{ formatPrice(returnRequest.refund_amount) }}</span> untuk pesanan <strong>{{ returnRequest.order.order_number }}</strong>.
                </p>
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-3 mb-6 text-left">
                    <p class="text-xs text-amber-800 leading-relaxed font-medium">
                        ⚠️ <strong>PENTING:</strong> Tindakan ini akan secara otomatis memperbarui status pesanan menjadi <strong>Refunded</strong>. Pastikan transfer dana telah berhasil diproses.
                    </p>
                </div>
                <div class="flex justify-center gap-3">
                    <SecondaryButton type="button" @click="showRefundModal = false" class="px-5">Batal</SecondaryButton>
                    <PrimaryButton type="button" @click="submitRefund" class="!bg-green-600 hover:!bg-green-700 px-5">Ya, Proses Refund</PrimaryButton>
                </div>
            </div>
        </Modal>

    </AdminLayout>
</template>
