<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useImageViewer } from '@/Composables/useImageViewer';
import SharedImageViewerModal from '@/Components/SharedImageViewerModal.vue';

const props = defineProps({
    returnRequest: Object,
});

const { isViewerOpen, viewerImages, viewerActiveIndex, viewerTitle, viewerSubtitle, openViewer, closeViewer } = useImageViewer();

const openReturnImages = (index) => {
    const images = [];
    for(let i=1; i<=5; i++){
        if (props.returnRequest[`evidence_image_${i}`]) {
            const path = props.returnRequest[`evidence_image_${i}`];
            if(path.endsWith('.mp4')){
                // Viewer might not support video, skipping or we could handle it. Let's just push for now if viewer supports it.
            }
            images.push({ url: `/storage/${path}` });
        }
    }
    openViewer(images, index, 'Bukti Retur', `Order: ${props.returnRequest.order?.order_number || '-'}`);
};

const getStatusDisplay = (status) => {
    const displays = {
        'submitted': 'Menunggu Persetujuan',
        'approved': 'Disetujui',
        'waiting_customer_shipment': 'Menunggu Pengiriman Pembeli',
        'customer_shipped': 'Dikirim Pembeli',
        'received': 'Diterima Admin',
        'inspected': 'Diinspeksi',
        'refund_processed': 'Refund Selesai',
        'completed': 'Selesai',
        'rejected': 'Ditolak',
        'cancelled': 'Dibatalkan',
        'expires': 'Kedaluwarsa'
    };
    return displays[status] || status;
};

const getStatusClass = (status) => {
    if (status === 'submitted') return 'bg-yellow-100 text-yellow-800';
    if (status === 'approved' || status === 'waiting_customer_shipment') return 'bg-blue-100 text-blue-800';
    if (status === 'customer_shipped' || status === 'received') return 'bg-indigo-100 text-indigo-800';
    if (status === 'inspected') return 'bg-purple-100 text-purple-800';
    if (status === 'refund_processed' || status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'rejected' || status === 'cancelled' || status === 'expires') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
};

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
};

const getReasonCodeDisplay = (code) => {
    const map = {
        defective: 'Barang Cacat/Rusak',
        wrong_item: 'Salah Kirim',
        missing_part: 'Ada Bagian Kurang',
        damaged_shipping: 'Rusak Pengiriman',
        not_as_described: 'Tidak Sesuai Deskripsi',
        other: 'Lainnya'
    };
    return map[code] || code;
};

const getConditionDisplay = (cond) => {
    const map = {
        opened: 'Sudah Dibuka',
        damaged: 'Rusak Fisik',
        defective: 'Cacat Fungsi',
        wrong_item: 'Salah Barang',
        other: 'Lainnya'
    };
    return map[cond] || cond;
};

// Modals state
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const showReceiveModal = ref(false);
const showInspectModal = ref(false);
const showRefundModal = ref(false);
const showCompleteModal = ref(false);

// Forms
const approveForm = useForm({});
const rejectForm = useForm({ admin_notes: '' });
const receiveForm = useForm({});
const completeForm = useForm({});
const refundForm = useForm({});
const inspectForm = useForm({
    inspection_result: 'passed',
    admin_notes: '',
    items: props.returnRequest.items.map(item => ({
        id: item.id,
        refund_amount: item.suggested_refund ?? 0,
        restock: false,
    }))
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
    receiveForm.post(route('admin.returns.receive', props.returnRequest.id), {
        onSuccess: () => showReceiveModal.value = false,
    });
};

const submitInspect = () => {
    inspectForm.post(route('admin.returns.inspect', props.returnRequest.id), {
        onSuccess: () => showInspectModal.value = false,
    });
};

const submitRefund = () => {
    refundForm.post(route('admin.returns.refund', props.returnRequest.id), {
        onSuccess: () => showRefundModal.value = false,
    });
};

const submitComplete = () => {
    completeForm.post(route('admin.returns.complete', props.returnRequest.id), {
        onSuccess: () => showCompleteModal.value = false,
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
                    'border-blue-400': returnRequest.status === 'approved' || returnRequest.status === 'waiting_customer_shipment',
                    'border-indigo-400': returnRequest.status === 'customer_shipped',
                    'border-indigo-600': returnRequest.status === 'received',
                    'border-purple-400': returnRequest.status === 'inspected',
                    'border-green-400': returnRequest.status === 'refund_processed' || returnRequest.status === 'completed',
                    'border-red-400': returnRequest.status === 'rejected' || returnRequest.status === 'cancelled'
                }">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Status: <span :class="getStatusClass(returnRequest.status)" class="px-3 py-1 rounded-full text-sm ml-2">{{ getStatusDisplay(returnRequest.status) }}</span></h3>
                            <p class="text-sm text-gray-500 mt-2">Dibuat pada: {{ new Date(returnRequest.created_at).toLocaleString('id-ID') }}</p>
                        </div>
                        
                        <div class="flex flex-wrap gap-2">
                            <template v-if="returnRequest.status === 'submitted'">
                                <DangerButton @click="showRejectModal = true">Tolak Pengajuan</DangerButton>
                                <PrimaryButton @click="showApproveModal = true" class="!bg-green-600 hover:!bg-green-700">Setujui Pengajuan</PrimaryButton>
                            </template>
                            
                            <template v-if="returnRequest.status === 'customer_shipped'">
                                <PrimaryButton @click="showReceiveModal = true" class="!bg-indigo-600 hover:!bg-indigo-700">Terima di Gudang</PrimaryButton>
                            </template>

                            <template v-if="returnRequest.status === 'received'">
                                <PrimaryButton @click="showInspectModal = true" class="!bg-purple-600 hover:!bg-purple-700">Mulai Inspeksi</PrimaryButton>
                            </template>

                            <template v-if="returnRequest.status === 'inspected' && returnRequest.inspection_result === 'passed'">
                                <PrimaryButton @click="showRefundModal = true" class="!bg-green-600 hover:!bg-green-700">Proses Pengembalian Dana</PrimaryButton>
                            </template>
                            
                            <template v-if="returnRequest.status === 'inspected' && returnRequest.inspection_result === 'failed'">
                                <PrimaryButton @click="showCompleteModal = true" class="!bg-gray-800 hover:!bg-black">Selesaikan Tanpa Refund</PrimaryButton>
                            </template>

                            <template v-if="returnRequest.status === 'refund_processed'">
                                <PrimaryButton @click="showCompleteModal = true" class="!bg-blue-600 hover:!bg-blue-700">Tandai Selesai</PrimaryButton>
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

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2 space-y-6">
                        <!-- Request Details -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Informasi Retur & Barang</h3>
                            
                            <div class="mb-6">
                                <p class="text-sm font-medium text-gray-500">Catatan/Alasan Umum</p>
                                <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ returnRequest.reason }}</p>
                            </div>

                            <div v-if="returnRequest.admin_notes" class="mb-6">
                                <p class="text-sm font-medium text-gray-500">Catatan Admin</p>
                                <p class="mt-1 text-sm text-red-600 bg-red-50 p-3 rounded border border-red-100">{{ returnRequest.admin_notes }}</p>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Barang</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Qty</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Alasan Detail</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Kondisi</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Refund Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <tr v-for="item in returnRequest.items" :key="item.id">
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900">{{ returnRequest.order.items.find(i => i.id === item.order_item_id)?.product_name_snapshot }}</div>
                                                <div class="text-xs text-gray-500">{{ returnRequest.order.items.find(i => i.id === item.order_item_id)?.variant_name_snapshot }}</div>
                                            </td>
                                            <td class="px-4 py-3 font-medium">{{ item.quantity }}</td>
                                            <td class="px-4 py-3">
                                                <div>{{ getReasonCodeDisplay(item.reason_code) }}</div>
                                                <div class="text-xs text-gray-500 mt-1" v-if="item.reason_notes">"{{ item.reason_notes }}"</div>
                                            </td>
                                            <td class="px-4 py-3">{{ getConditionDisplay(item.condition) }}</td>
                                            <td class="px-4 py-3 text-right font-bold text-green-600">Rp {{ formatPrice(item.refund_amount) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot v-if="returnRequest.refund_amount" class="bg-green-50 border-t border-green-200">
                                        <tr>
                                            <td colspan="4" class="px-4 py-3 text-right font-bold text-green-800">Total Refund:</td>
                                            <td class="px-4 py-3 text-right font-bold text-green-800">Rp {{ formatPrice(returnRequest.refund_amount) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="mt-6">
                                <p class="text-sm font-medium text-gray-500 mb-2">Media Bukti</p>
                                <div class="flex flex-wrap gap-4">
                                    <template v-for="i in 5" :key="i">
                                        <div v-if="returnRequest[`evidence_image_${i}`]" class="relative w-24 h-24 rounded overflow-hidden border">
                                            <video v-if="returnRequest[`evidence_image_${i}`].endsWith('.mp4')" :src="`/storage/${returnRequest['evidence_image_' + i]}`" class="w-full h-full object-cover" controls></video>
                                            <img v-else :src="`/storage/${returnRequest['evidence_image_' + i]}`" class="w-full h-full object-cover hover:opacity-75 cursor-zoom-in" @click="openReturnImages(i-1)">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Histories -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Riwayat (Audit Trail)</h3>
                            <div class="space-y-4">
                                <div v-for="history in returnRequest.histories" :key="history.id" class="flex gap-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-3 h-3 bg-indigo-500 rounded-full mt-1.5"></div>
                                        <div class="w-px h-full bg-indigo-100 my-1"></div>
                                    </div>
                                    <div class="pb-2">
                                        <div class="text-xs text-gray-500 mb-0.5">{{ new Date(history.created_at).toLocaleString('id-ID') }}</div>
                                        <div class="text-sm font-semibold text-gray-900">
                                            Status: <span class="bg-gray-100 px-2 py-0.5 rounded ml-1">{{ getStatusDisplay(history.from_status) || '-' }} &rarr; {{ getStatusDisplay(history.to_status) }}</span>
                                        </div>
                                        <div class="text-sm text-gray-700 mt-1 bg-gray-50 p-2 rounded inline-block" v-if="history.notes">
                                            {{ history.notes }}
                                        </div>
                                    </div>
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
                        </div>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Informasi Pelanggan</h3>
                            <p class="text-sm font-medium text-gray-900">{{ returnRequest.user.name }}</p>
                            <p class="text-sm text-gray-500">{{ returnRequest.user.email }}</p>
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
                    Dengan menyetujui, status akan berubah menjadi "Disetujui" dan pelanggan akan diminta untuk mengirimkan barang ke gudang.
                </p>
                <form @submit.prevent="submitApprove">
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
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <SecondaryButton type="button" @click="showRejectModal = false">Batal</SecondaryButton>
                        <DangerButton type="submit" :disabled="rejectForm.processing">Tolak Retur</DangerButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Receive Modal -->
        <Modal :show="showReceiveModal" @close="showReceiveModal = false" maxWidth="md">
            <div class="p-6 text-center">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Konfirmasi Terima Barang</h3>
                <p class="text-sm text-gray-600 mb-6">
                    Apakah Anda yakin barang retur telah tiba di gudang?
                </p>
                <div class="flex justify-center gap-3">
                    <SecondaryButton type="button" @click="showReceiveModal = false" class="px-5">Batal</SecondaryButton>
                    <PrimaryButton type="button" @click="submitReceive" class="!bg-indigo-600 hover:!bg-indigo-700 px-5">Ya, Diterima</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Inspect Modal -->
        <Modal :show="showInspectModal" @close="showInspectModal = false" maxWidth="2xl">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Inspeksi Barang Retur</h2>
                <p class="text-sm text-gray-600 mb-6">
                    Lakukan pemeriksaan fisik terhadap barang yang diretur. Tentukan apakah lolos inspeksi, jumlah refund per-item, dan apakah stok akan dimasukkan kembali.
                </p>
                <form @submit.prevent="submitInspect">
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-900 mb-2">Hasil Inspeksi Akhir</label>
                        <select v-model="inspectForm.inspection_result" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="passed">Lolos Inspeksi (Lanjut Refund)</option>
                            <option value="failed">Gagal Inspeksi (Tolak Refund, Barang Bodong/Rusak Parah)</option>
                        </select>
                    </div>

                    <div v-if="inspectForm.inspection_result === 'passed'" class="space-y-4 mb-6">
                        <h3 class="font-bold text-gray-700">Tentukan Refund per Item:</h3>
                        <div v-for="(item, idx) in returnRequest.items" :key="item.id" class="p-4 bg-gray-50 border rounded-lg">
                            <div class="font-semibold text-sm mb-2">{{ returnRequest.order.items.find(i => i.id === item.order_item_id)?.product_name_snapshot }}</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700">Nominal Refund (Rp)</label>
                                    <input type="number" v-model="inspectForm.items[idx].refund_amount" min="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <p class="text-xs text-green-600 mt-1 font-medium" v-if="item.suggested_refund !== undefined">Saran Prorata: Rp {{ formatPrice(item.suggested_refund) }}</p>
                                </div>
                                <div class="flex items-center pt-6">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="inspectForm.items[idx].restock" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-700 font-medium">Restock Barang</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-900 mb-2">Catatan Inspeksi</label>
                        <textarea v-model="inspectForm.admin_notes" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Catatan opsional..."></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showInspectModal = false">Batal</SecondaryButton>
                        <PrimaryButton type="submit" class="!bg-purple-600 hover:!bg-purple-700" :disabled="inspectForm.processing">Simpan Hasil Inspeksi</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Refund Modal -->
        <Modal :show="showRefundModal" @close="showRefundModal = false" maxWidth="md">
            <div class="p-6 text-center">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Konfirmasi Pengembalian Dana</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Anda akan memproses pengembalian dana sebesar <span class="font-bold text-green-600">Rp {{ formatPrice(returnRequest.refund_amount) }}</span>.
                </p>
                <div class="flex justify-center gap-3 mt-6">
                    <SecondaryButton type="button" @click="showRefundModal = false" class="px-5">Batal</SecondaryButton>
                    <PrimaryButton type="button" @click="submitRefund" class="!bg-green-600 hover:!bg-green-700 px-5">Ya, Proses Refund</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Complete Modal -->
        <Modal :show="showCompleteModal" @close="showCompleteModal = false" maxWidth="md">
            <div class="p-6 text-center">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Selesaikan Retur</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Apakah Anda yakin ingin menyelesaikan proses retur ini?
                </p>
                <div class="flex justify-center gap-3 mt-6">
                    <SecondaryButton type="button" @click="showCompleteModal = false" class="px-5">Batal</SecondaryButton>
                    <PrimaryButton type="button" @click="submitComplete" class="!bg-blue-600 hover:!bg-blue-700 px-5">Selesaikan</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Shared Image Viewer Modal -->
        <SharedImageViewerModal 
            :show="isViewerOpen"
            :images="viewerImages"
            :activeIndex="viewerActiveIndex"
            :title="viewerTitle"
            :subtitle="viewerSubtitle"
            @close="closeViewer"
            @update:activeIndex="viewerActiveIndex = $event"
        />

    </AdminLayout>
</template>
