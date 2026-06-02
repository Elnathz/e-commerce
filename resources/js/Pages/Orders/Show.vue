<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SharedImageViewerModal from '@/Components/SharedImageViewerModal.vue';
import { useImageViewer } from '@/Composables/useImageViewer';

const { isViewerOpen, viewerImages, viewerActiveIndex, viewerTitle, viewerSubtitle, openViewer, closeViewer } = useImageViewer();

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

// Review Modal State
const reviewingItem = ref(null);
const isEditingReview = ref(false);
const editingReviewId = ref(null);
const reviewForm = useForm({
    order_item_id: '',
    rating: 5,
    comment: '',
    kept_images: [],
    images: [],
});
const previewImages = ref([]);

const openReviewModal = (item) => {
    isEditingReview.value = false;
    editingReviewId.value = null;
    reviewingItem.value = item;
    reviewForm.order_item_id = item.id;
    reviewForm.rating = 5;
    reviewForm.comment = '';
    reviewForm.kept_images = [];
    reviewForm.images = [];
    previewImages.value = [];
};

const openEditReviewModal = (item) => {
    isEditingReview.value = true;
    editingReviewId.value = item.review.id;
    reviewingItem.value = item;
    reviewForm.order_item_id = item.id;
    reviewForm.rating = item.review.rating;
    reviewForm.comment = item.review.comment || '';
    reviewForm.kept_images = item.review.images ? item.review.images.map(img => img.id) : [];
    reviewForm.images = [];
    previewImages.value = item.review.images ? item.review.images.map(img => img.url) : [];
    reviewForm.clearErrors();
};

const closeReviewModal = () => {
    reviewingItem.value = null;
    isEditingReview.value = false;
    editingReviewId.value = null;
    reviewForm.reset();
    reviewForm.clearErrors();
    previewImages.value = [];
};

const handleReviewImageChange = (e) => {
    const files = Array.from(e.target.files);

    // Check total images
    if (previewImages.value.length + files.length > 5) {
        alert('Maksimal 5 foto ulasan.');
        return;
    }

    // Add new files
    files.forEach(file => {
        // validate size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert(`Ukuran file ${file.name} terlalu besar (maksimal 5MB).`);
            return;
        }

        reviewForm.images.push(file);

        // Generate preview
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImages.value.push(e.target.result);
        };
        reader.readAsDataURL(file);
    });

    // Reset file input
    e.target.value = '';
};

const removeReviewImage = (index) => {
    const isKeptImage = isEditingReview.value && index < reviewForm.kept_images.length;
    if (isKeptImage) {
        reviewForm.kept_images.splice(index, 1);
    } else {
        const newImageIndex = isEditingReview.value ? index - reviewForm.kept_images.length : index;
        reviewForm.images.splice(newImageIndex, 1);
    }
    previewImages.value.splice(index, 1);
};

const submitReview = () => {
    if (isEditingReview.value) {
        reviewForm.post(route('reviews.update', editingReviewId.value), {
            preserveScroll: true,
            onSuccess: () => {
                closeReviewModal();
            }
        });
    } else {
        reviewForm.post(route('reviews.store', props.order.order_number), {
            preserveScroll: true,
            onSuccess: () => {
                closeReviewModal();
            }
        });
    }
};

// Return Modal State
const returning = ref(false);
const returnForm = useForm({
    reason: '',
    is_partial: false,
    images: [],
    agreed_to_terms: false,
});
const previewReturnImages = ref([]);

const openReturnModal = () => {
    returning.value = true;
    returnForm.reason = '';
    returnForm.is_partial = false;
    returnForm.images = [];
    returnForm.agreed_to_terms = false;
    previewReturnImages.value = [];
};

const closeReturnModal = () => {
    returning.value = false;
    returnForm.reset();
    returnForm.clearErrors();
    previewReturnImages.value = [];
};

const handleReturnImageChange = (e) => {
    const files = Array.from(e.target.files);

    if (returnForm.images.length + files.length > 3) {
        alert('Maksimal 3 foto bukti.');
        return;
    }

    files.forEach(file => {
        if (file.size > 5 * 1024 * 1024) {
            alert(`Ukuran file ${file.name} terlalu besar (maksimal 5MB).`);
            return;
        }

        returnForm.images.push(file);

        const reader = new FileReader();
        reader.onload = (e) => {
            previewReturnImages.value.push(e.target.result);
        };
        reader.readAsDataURL(file);
    });

    e.target.value = '';
};

const removeReturnImage = (index) => {
    returnForm.images.splice(index, 1);
    previewReturnImages.value.splice(index, 1);
};

const submitReturn = () => {
    returnForm.post(route('returns.store', props.order.order_number), {
        preserveScroll: true,
        onSuccess: () => {
            closeReturnModal();
        }
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="text-2xl font-bold text-slate-900">Detail Pesanan</h2>
                </div>

                <!-- Info Box -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Nomor Pesanan</p>
                        <p class="text-lg font-bold text-slate-900">{{ order.order_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Status</p>
                        <span
                            :class="['inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide', getStatusClass(order.status)]">
                            {{ getStatusDisplay(order.status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Tanggal Pemesanan</p>
                        <p class="text-base font-semibold text-slate-900">{{ new
                            Date(order.created_at).toLocaleString('id-ID') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Kiri: Produk & Info Pengiriman -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Actions & Tracking -->
                        <div v-if="order.status === 'shipped' || order.status === 'completed'"
                            class="rounded-2xl border border-indigo-200 bg-indigo-50 shadow-sm p-6">
                            <h3 class="text-lg font-bold text-indigo-900 mb-4">Informasi Pengiriman</h3>

                            <div class="bg-white rounded-xl p-4 border border-indigo-100 mb-5">
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Kurir</p>
                                        <p class="font-bold text-slate-900 uppercase">{{ order.courier }} <span
                                                v-if="order.shipping_service" class="text-indigo-600">- {{
                                                order.shipping_service }}</span></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Nomor Resi</p>
                                        <div class="flex items-center gap-2">
                                            <p class="font-mono font-bold text-slate-900">{{ order.tracking_number }}
                                            </p>
                                            <button @click="copyResi(order.tracking_number)"
                                                class="text-indigo-600 hover:text-indigo-800 p-1 bg-indigo-50 rounded"
                                                title="Salin Resi">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                                    </path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <a :href="`https://cekresi.com/?noresi=${order.tracking_number}`" target="_blank"
                                    class="block w-full text-center py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-lg transition-colors">
                                    Lacak Paket di CekResi.com ↗
                                </a>
                            </div>

                            <PrimaryButton v-if="order.status === 'shipped'" @click="confirmReceipt"
                                class="w-full justify-center !bg-indigo-600 hover:!bg-indigo-700 !rounded-xl !shadow-lg !shadow-indigo-500/30">
                                Pesanan Telah Diterima
                            </PrimaryButton>

                            <div v-if="order.status === 'shipped' || order.status === 'completed'" class="mt-4">
                                <button
                                    v-if="!order.return_request && (order.status === 'shipped' || order.status === 'completed')"
                                    @click="openReturnModal"
                                    class="w-full text-center text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">
                                    Ajukan Pengembalian (Komplain)
                                </button>
                                <div v-else-if="order.return_request"
                                    :class="[
                                        'p-4 rounded-xl border text-sm mt-3',
                                        order.return_request.status === 'rejected' ? 'bg-gray-50 border-gray-200 text-gray-700' : 'bg-orange-50 border-orange-200 text-orange-800'
                                    ]">
                                    <div class="flex items-center gap-2 mb-1.5 font-bold">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="uppercase tracking-wide">{{ order.return_request.status === 'rejected' ? 'Retur Ditolak' : 'Status Retur: ' + order.return_request.status.replace('_', ' ') }}</span>
                                    </div>
                                    <p v-if="order.return_request.status === 'rejected'" class="text-gray-600 font-normal mb-2 text-xs">
                                        Pengajuan retur Anda pada tanggal {{ new Date(order.return_request.created_at).toLocaleDateString('id-ID') }} tidak dapat kami setujui. 
                                        <br><span class="font-semibold text-gray-800">Alasan Penolakan:</span> "{{ order.return_request.admin_notes }}"
                                    </p>
                                    <Link :href="route('returns.show', order.return_request.return_number)"
                                        :class="['inline-block mt-2 font-semibold underline', order.return_request.status === 'rejected' ? 'text-gray-800 hover:text-black' : 'text-orange-700 hover:text-orange-900']">
                                        Lihat Riwayat & Detail Retur
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Canceled / Refunded Actions -->
                        <div v-if="order.status === 'pending'"
                            class="rounded-2xl border border-yellow-200 bg-yellow-50 shadow-sm p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-yellow-900">Menunggu Pembayaran</h3>
                                <p class="text-sm text-yellow-800">Segera selesaikan pembayaran Anda sebelum pesanan
                                    kedaluwarsa.</p>
                            </div>
                            <Link :href="route('checkout.success', order.order_number)">
                                <PrimaryButton class="!bg-yellow-600 hover:!bg-yellow-700 !rounded-xl text-white">Bayar
                                    Sekarang</PrimaryButton>
                            </Link>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Daftar
                                Produk</h3>
                            <div class="space-y-4">
                                <div v-for="item in order.items" :key="item.id" class="border-b border-slate-50 pb-4 last:border-0 last:pb-0">
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                                        <div class="flex-1">
                                            <Link v-if="item.product_variant && item.product_variant.product"
                                                :href="route('products.show', item.product_variant.product.slug)"
                                                class="font-bold text-slate-900 text-base hover:text-blue-600 hover:underline transition-colors block">
                                                {{ item.product_name_snapshot }}
                                            </Link>
                                            <h4 v-else class="font-bold text-slate-900 text-base">{{
                                                item.product_name_snapshot }}</h4>
                                            <p class="text-sm text-slate-500 mt-0.5">Varian: {{ item.variant_name_snapshot
                                                }}</p>
                                            <p class="text-sm font-semibold text-slate-700 mt-1">{{ item.quantity }} x Rp {{
                                                formatPrice(item.unit_price) }}</p>
                                        </div>
                                        <div class="flex flex-col sm:items-end gap-2">
                                            <div class="font-bold text-slate-900 text-lg">
                                                Rp {{ formatPrice(item.subtotal) }}
                                            </div>
                                            <Link v-if="item.product_variant && item.product_variant.product"
                                                :href="route('products.show', item.product_variant.product.slug)"
                                                class="inline-flex items-center justify-center px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-blue-600 hover:border-blue-300 transition-colors shadow-sm">
                                                Lihat Produk
                                            </Link>

                                            <button v-if="order.status === 'completed' && !item.review && (!order.return_request || order.return_request.status === 'rejected')"
                                                @click="openReviewModal(item)"
                                                class="mt-2 inline-flex items-center justify-center px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-xs font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-300 transition-colors shadow-sm">
                                                Beri Ulasan
                                            </button>
                                            <div v-else-if="order.status === 'completed' && !item.review && order.return_request && order.return_request.status !== 'rejected'"
                                                class="mt-2 text-xs font-semibold text-red-600 bg-red-50 px-2 py-1.5 rounded-md border border-red-200 text-center">
                                                Tidak Dapat Diulas (Ada Retur)
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ULASAN SAYA SECTION -->
                                    <div v-if="item.review" class="mt-4 pt-4 border-t border-slate-100 pl-0 sm:pl-[88px]">
                                        <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                                            <div class="flex-1 space-y-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded uppercase tracking-wider">Ulasan Saya</span>
                                                    <div class="flex text-yellow-400">
                                                        <svg v-for="i in 5" :key="i" class="w-4 h-4" :class="i <= item.review.rating ? 'text-yellow-400' : 'text-slate-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                    </div>
                                                    <span class="text-xs text-slate-400">{{ new Date(item.review.created_at).toLocaleDateString('id-ID') }}</span>
                                                    <span v-if="item.review.is_edited" class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded italic">Telah Diedit</span>
                                                    <button v-if="!item.review.is_edited && ((new Date() - new Date(item.review.created_at)) / (1000 * 60 * 60 * 24)) <= 30" @click="openEditReviewModal(item)" class="text-xs font-semibold text-blue-600 hover:text-blue-800 ml-2 underline">Edit Ulasan</button>
                                                </div>
                                                <p v-if="item.review.comment" class="text-sm text-slate-700 leading-relaxed">{{ item.review.comment }}</p>
                                                
                                                <!-- Review Images -->
                                                <div v-if="item.review.images && item.review.images.length > 0" class="flex gap-2 mt-3 overflow-x-auto pb-2 no-scrollbar">
                                                    <button v-for="(img, idx) in item.review.images" :key="idx" 
                                                        @click="openViewer(item.review.images, idx, 'Foto Ulasan', item.product_name_snapshot)"
                                                        class="relative w-16 h-16 rounded-lg overflow-hidden border border-slate-200 shrink-0 hover:border-blue-400 transition-colors">
                                                        <img :src="img.url" class="w-full h-full object-cover">
                                                    </button>
                                                </div>

                                                <!-- Admin Reply -->
                                                <div v-if="item.review.admin_reply" class="mt-3 bg-slate-50 border border-slate-200 rounded-xl p-4">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Balasan Toko</span>
                                                        <span class="text-xs text-slate-400">• {{ new Date(item.review.replied_at).toLocaleDateString('id-ID') }}</span>
                                                    </div>
                                                    <p class="text-sm text-slate-700 leading-relaxed">{{ item.review.admin_reply }}</p>
                                                </div>
                                            </div>
                                            <div class="shrink-0 flex flex-col items-start sm:items-end gap-1 mt-2 sm:mt-0">
                                                <span v-if="item.review.admin_reply" class="text-[10px] font-bold uppercase tracking-wider text-green-600 bg-green-50 px-2 py-1 rounded-md border border-green-200">
                                                    ✓ Dibalas {{ new Date(item.review.replied_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                                </span>
                                                <span v-else class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 px-2 py-1 rounded-md border border-slate-200">Belum Dibalas</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Info
                                Pengiriman</h3>
                            <div v-if="address" class="text-sm text-slate-700 space-y-1">
                                <p class="font-bold text-slate-900 text-base mb-2">{{ address.recipient_name }} <span
                                        class="text-sm font-normal text-slate-500">({{ address.phone }})</span></p>
                                <p>{{ address.address_detail }}</p>
                                <p>{{ address.district }}, {{ address.city }}</p>
                                <p>{{ address.province }} {{ address.postal_code }}</p>
                            </div>
                            
                            <!-- Catatan Pesanan -->
                            <div v-if="order.notes" class="mt-4 pt-4 border-t border-slate-100">
                                <span class="font-semibold text-slate-900 text-sm block mb-1">Catatan untuk Toko:</span>
                                <p class="text-sm text-slate-700 bg-yellow-50 border border-yellow-100 p-3 rounded-lg italic">
                                    "{{ order.notes }}"
                                </p>
                            </div>
                        </div>

                        <!-- Kebijakan Retur & Refund -->
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Kebijakan Retur & Refund</h3>
                            <div class="text-sm text-slate-600 space-y-4">
                                <div>
                                    <h4 class="font-bold text-slate-800 mb-1">Syarat Pengajuan Retur:</h4>
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>Barang rusak, cacat produksi, atau tidak berfungsi saat diterima.</li>
                                        <li>Barang tidak sesuai dengan pesanan (salah warna, ukuran, atau model).</li>
                                        <li>Barang dalam kondisi belum digunakan (tag/segel masih utuh).</li>
                                        <li>Pengajuan maksimal 7 hari sejak barang berstatus Diterima.</li>
                                    </ul>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 mb-1">Tidak Dapat Diretur:</h4>
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>Barang sudah digunakan, dicuci, atau diubah bentuknya.</li>
                                        <li>Melewati batas waktu pengajuan retur.</li>
                                        <li>Kerusakan diakibatkan oleh kelalaian pembeli.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kanan: Ringkasan Harga -->
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 sticky top-24">
                            <h3 class="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Ringkasan
                                Pembayaran</h3>

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
                                    <span class="font-bold text-blue-600 text-lg">Rp {{ formatPrice(order.total_amount)
                                        }}</span>
                                </div>
                            </div>

                            <div v-if="order.status === 'pending'" class="mt-6 pt-4 border-t border-slate-100">
                                <button @click="confirmCancel"
                                    class="w-full text-center text-sm font-bold text-red-600 hover:text-red-800 transition-colors">
                                    Batalkan Pesanan
                                </button>
                                <p class="text-xs text-slate-400 text-center mt-2">Pesanan hanya dapat dibatalkan
                                    sebelum Anda melakukan pembayaran.</p>
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
                    Pastikan Anda telah menerima pesanan dalam kondisi baik dan lengkap sebelum mengonfirmasi. Setelah
                    dikonfirmasi, Anda tidak dapat mengajukan komplain untuk pesanan ini.
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

        <!-- Review Modal -->
        <Modal :show="!!reviewingItem" @close="closeReviewModal">
            <div class="p-6" v-if="reviewingItem">
                <h2 class="text-lg font-bold text-slate-900 mb-4 border-b pb-2">{{ isEditingReview ? 'Edit Ulasan' : 'Beri Ulasan Produk' }}</h2>

                <div class="flex gap-4 items-center mb-6">
                    <div class="flex-1">
                        <h4 class="font-bold text-slate-900 text-sm">{{ reviewingItem.product_name_snapshot }}</h4>
                        <p class="text-xs text-slate-500">Varian: {{ reviewingItem.variant_name_snapshot }}</p>
                    </div>
                </div>

                <form @submit.prevent="submitReview" class="space-y-6">
                    <!-- Rating -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Penilaian Anda <span
                                class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <button v-for="star in 5" :key="star" type="button" @click="reviewForm.rating = star"
                                class="focus:outline-none transition-transform hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    :class="['w-8 h-8', star <= reviewForm.rating ? 'text-yellow-400' : 'text-slate-200']">
                                    <path fill-rule="evenodd"
                                        d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="reviewForm.errors.rating" class="mt-1 text-sm text-red-600">{{ reviewForm.errors.rating
                            }}</p>
                    </div>

                    <!-- Comment -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Tulis Ulasan (Opsional)</label>
                        <textarea v-model="reviewForm.comment" rows="4"
                            class="w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm text-sm"
                            placeholder="Ceritakan pengalaman Anda menggunakan produk ini..."></textarea>
                        <p v-if="reviewForm.errors.comment" class="mt-1 text-sm text-red-600">{{
                            reviewForm.errors.comment }}</p>
                    </div>

                    <!-- Photos -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Tambah Foto (Max 5)</label>

                        <div class="flex flex-wrap gap-3">
                            <!-- Previews -->
                            <div v-for="(preview, idx) in previewImages" :key="idx"
                                class="relative w-20 h-20 rounded-lg overflow-hidden border border-slate-200 group">
                                <img :src="preview" class="w-full h-full object-cover" />
                                <button type="button" @click="removeReviewImage(idx)"
                                    class="absolute inset-0 bg-black/50 text-white opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Upload Button -->
                            <div v-if="previewImages.length < 5"
                                class="w-20 h-20 rounded-lg border-2 border-dashed border-slate-300 flex items-center justify-center hover:border-indigo-500 hover:bg-indigo-50 transition-colors cursor-pointer relative">
                                <input type="file" multiple accept="image/jpeg,image/png,image/jpg"
                                    @change="handleReviewImageChange"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                        </div>
                        <p v-if="reviewForm.errors.images" class="mt-1 text-sm text-red-600">{{ reviewForm.errors.images
                            }}</p>
                        <p class="text-xs text-slate-500 mt-2">Format: JPG, PNG. Ukuran maksimal 5MB per file.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton type="button" @click="closeReviewModal" :disabled="reviewForm.processing">Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" :class="{ 'opacity-50': reviewForm.processing }"
                            :disabled="reviewForm.processing">
                            Kirim Ulasan
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Return Request Modal -->
        <Modal :show="returning" @close="closeReturnModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900 mb-4 border-b pb-2">Ajukan Pengembalian Barang</h2>

                <p class="text-sm text-slate-600 mb-6">
                    Silakan isi form di bawah ini dengan jelas untuk mengajukan retur barang atau pengembalian dana.
                </p>

                <form @submit.prevent="submitReturn" class="space-y-6">
                    <!-- Reason -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Alasan Pengembalian <span
                                class="text-red-500">*</span></label>
                        <textarea v-model="returnForm.reason" rows="4"
                            class="w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm text-sm"
                            placeholder="Jelaskan kendala pada produk atau alasan pengembalian secara detail..."
                            required></textarea>
                        <p v-if="returnForm.errors.reason" class="mt-1 text-sm text-red-600">{{ returnForm.errors.reason
                            }}</p>
                    </div>

                    <!-- Partial Return -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" v-model="returnForm.is_partial"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="ml-2 text-sm text-slate-700">Pengembalian Sebagian (Hanya beberapa
                                barang)</span>
                        </label>
                    </div>

                    <!-- Photos -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Foto Bukti (Wajib, Max 3) <span
                                class="text-red-500">*</span></label>

                        <div class="flex flex-wrap gap-3">
                            <!-- Previews -->
                            <div v-for="(preview, idx) in previewReturnImages" :key="idx"
                                class="relative w-20 h-20 rounded-lg overflow-hidden border border-slate-200 group">
                                <img :src="preview" class="w-full h-full object-cover" />
                                <button type="button" @click="removeReturnImage(idx)"
                                    class="absolute inset-0 bg-black/50 text-white opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Upload Button -->
                            <div v-if="previewReturnImages.length < 3"
                                class="w-20 h-20 rounded-lg border-2 border-dashed border-slate-300 flex items-center justify-center hover:border-indigo-500 hover:bg-indigo-50 transition-colors cursor-pointer relative">
                                <input type="file" multiple accept="image/jpeg,image/png,image/jpg"
                                    @change="handleReturnImageChange"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                        </div>
                        <p v-if="returnForm.errors.images" class="mt-1 text-sm text-red-600">{{ returnForm.errors.images
                            }}</p>
                        <p class="text-xs text-slate-500 mt-2">Format: JPG, PNG. Ukuran maksimal 5MB per file.</p>
                    </div>

                    <!-- Terms & Agreement -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-sm mb-6">
                        <h4 class="font-bold text-slate-800 mb-2">Penting sebelum retur:</h4>
                        <ul class="list-disc pl-4 text-slate-600 space-y-1 mb-4">
                            <li>Pastikan barang belum digunakan dan tag/segel masih utuh.</li>
                            <li>Sertakan foto bukti kerusakan/kesalahan produk dengan jelas.</li>
                            <li>Retur akan otomatis ditolak jika kerusakan disebabkan oleh kelalaian pembeli.</li>
                        </ul>
                        <label class="flex items-start gap-3 p-2 hover:bg-slate-100 rounded-lg cursor-pointer transition-colors">
                            <input type="checkbox" v-model="returnForm.agreed_to_terms" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" required />
                            <span class="text-slate-800 font-semibold">Saya telah membaca dan menyetujui Kebijakan Retur & Refund.</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton type="button" @click="closeReturnModal" :disabled="returnForm.processing">Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" :class="{ 'opacity-50': returnForm.processing || !returnForm.agreed_to_terms }"
                            :disabled="returnForm.processing || !returnForm.agreed_to_terms">
                            Kirim Pengajuan
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <SharedImageViewerModal
            :show="isViewerOpen"
            :images="viewerImages"
            :activeIndex="viewerActiveIndex"
            :title="viewerTitle"
            :subtitle="viewerSubtitle"
            @close="closeViewer"
            @update:activeIndex="viewerActiveIndex = $event"
        />

    </StorefrontLayout>
</template>
