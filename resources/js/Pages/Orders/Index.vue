<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    orders: Object,
    currentStatus: String
});

const statuses = [
    { value: 'all', label: 'Semua' },
    { value: 'pending', label: 'Belum Bayar' },
    { value: 'processing', label: 'Diproses' },
    { value: 'shipped', label: 'Dikirim' },
    { value: 'completed', label: 'Selesai' },
    { value: 'returned', label: 'Retur' },
    { value: 'cancelled', label: 'Dibatalkan' },
];

const changeStatus = (status) => {
    router.get(route('orders.index'), { status }, { preserveState: true, replace: true });
};

const getStatusDisplay = (order) => {
    if (order.status === 'refunded') return 'Pengembalian Selesai';
    if (order.status === 'cancelled') return 'Dibatalkan';
    
    if (order.return_request) {
        if (order.return_request.status === 'rejected') {
            return order.status === 'completed' ? 'Selesai' : 'Dikirim';
        }
        if (order.return_request.status === 'cancelled') return 'Retur Dibatalkan';
        
        switch(order.return_request.status) {
            case 'submitted': return 'Sedang Diretur';
            case 'approved': return 'Retur Disetujui';
            case 'returned': return 'Menunggu Barang Kembali';
            case 'received': return 'Barang Diterima Admin';
            case 'refund_processed': return 'Refund Diproses';
        }
    }

    if (order.status === 'pending' || order.status === 'waiting') return 'Belum Bayar';
    if (order.status === 'paid' || order.status === 'processing') return 'Diproses';
    if (order.status === 'shipped') return 'Dikirim';
    if (order.status === 'completed') return 'Selesai';

    return order.status;
};

const getStatusClass = (order) => {
    if (order.status === 'refunded' || order.status === 'cancelled') return 'bg-red-100 text-red-800';
    
    if (order.return_request) {
        if (order.return_request.status === 'rejected') {
            // Revert to original class, but we could add border if needed
        } else {
            return 'bg-orange-100 text-orange-800 border border-orange-300';
        }
    }

    if (order.status === 'pending' || order.status === 'waiting') return 'bg-yellow-100 text-yellow-800';
    if (order.status === 'paid' || order.status === 'processing') return 'bg-purple-100 text-purple-800';
    if (order.status === 'shipped') return 'bg-indigo-100 text-indigo-800';
    if (order.status === 'completed') return 'bg-green-100 text-green-800';
    
    return 'bg-gray-100 text-gray-800';
};

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
};

const getPrimaryImage = (item) => {
    if (!item || !item.product_variant) return null;
    const variant = item.product_variant;
    if (variant.images && variant.images.length > 0) {
        return '/storage/' + variant.images[0].image_path;
    }
    const product = variant.product;
    if (product && product.images && product.images.length > 0) {
        const primary = product.images.find(img => img.is_primary);
        return primary ? '/storage/' + primary.image_path : '/storage/' + product.images[0].image_path;
    }
    return null;
};
</script>

<template>
    <Head title="Pesanan Saya" />

    <StorefrontLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                
                <h2 class="text-2xl font-bold text-slate-900 mb-6">Pesanan Saya</h2>

                <!-- Status Tabs -->
                <div class="bg-white rounded-t-2xl shadow-sm border-b border-slate-200 flex overflow-x-auto">
                    <button
                        v-for="status in statuses"
                            :key="status.value"
                        @click="changeStatus(status.value)"
                        :class="[
                            'whitespace-nowrap px-6 py-4 font-bold text-sm transition-colors flex-1 text-center',
                            currentStatus === status.value
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
                        ]"
                    >
                        {{ status.label }}
                    </button>
                </div>

                <div class="bg-transparent space-y-6 mt-6">
                    
                    <div v-if="orders.data.length === 0" class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <svg class="mx-auto h-12 w-12 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <h3 class="text-lg font-bold text-slate-900 mb-1">Belum ada pesanan</h3>
                        <p class="text-slate-500 mb-6">Anda belum memiliki pesanan dengan status ini.</p>
                        <Link :href="route('home')">
                            <PrimaryButton class="!bg-blue-600 hover:!bg-blue-700 !rounded-xl">Mulai Belanja</PrimaryButton>
                        </Link>
                    </div>

                    <!-- Order Cards -->
                    <div 
                        v-else 
                        v-for="order in orders.data" 
                        :key="order.id" 
                        class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden hover:border-blue-300 transition-colors"
                    >
                        <!-- Card Header -->
                        <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                                <span class="text-sm font-bold text-slate-900">{{ order.order_number }}</span>
                                <span class="hidden sm:inline text-slate-300">|</span>
                                <span class="text-sm text-slate-500">{{ new Date(order.created_at).toLocaleString('id-ID') }}</span>
                            </div>
                            <div>
                                <span :class="['px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide', getStatusClass(order)]">
                                    {{ getStatusDisplay(order) }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body (Summary) -->
                        <div class="p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
                            
                            <!-- Product Summary -->
                            <div class="flex items-center gap-4 flex-1 w-full" v-if="order.items && order.items.length > 0">
                                <div class="w-16 h-16 rounded-xl border border-slate-200 bg-slate-50 overflow-hidden flex-shrink-0">
                                    <img v-if="getPrimaryImage(order.items[0])" :src="getPrimaryImage(order.items[0])" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-slate-300 text-xs font-medium">No Img</div>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-900 line-clamp-1">{{ order.items[0].product_name_snapshot }}</h3>
                                    <p class="text-sm text-slate-500">{{ order.items[0].quantity }} barang x Rp {{ formatPrice(order.items[0].unit_price) }}</p>
                                    <p v-if="order.items.length > 1" class="text-xs font-semibold text-blue-600 mt-1">+ {{ order.items.length - 1 }} barang lainnya</p>
                                    <p v-if="order.return_request" 
                                       :class="['text-xs font-bold mt-1.5 flex items-center gap-1.5', order.return_request.status === 'rejected' ? 'text-gray-500' : 'text-orange-600']">
                                        <svg v-if="order.return_request.status === 'rejected'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                                        </svg>
                                        {{ order.return_request.status === 'rejected' ? 'Pernah Diajukan Retur (Ditolak)' : (order.return_request.is_partial ? 'Retur Sebagian' : '1 Pesanan Diretur') }}
                                    </p>
                                    
                                    <!-- Review Status -->
                                    <div v-if="order.status === 'completed'" class="mt-2">
                                        <p class="text-[13px] font-bold flex items-center gap-1.5" :class="order.reviewed_items_count === order.items_count ? 'text-green-700' : 'text-orange-600'">
                                            <span v-if="order.reviewed_items_count === order.items_count" class="flex items-center gap-1">
                                                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            </span>
                                            <span v-else class="flex items-center gap-1">
                                                <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </span>
                                            <span v-if="order.reviewed_items_count === 0">{{ order.items_count }} Produk Belum Diulas</span>
                                            <span v-else>{{ order.reviewed_items_count }}/{{ order.items_count }} Produk Sudah Diulas</span>
                                        </p>
                                        <div v-if="order.reviewed_items_count === order.items_count && order.reviews_avg_rating" class="flex text-yellow-400 mt-1 pl-6">
                                            <svg v-for="i in 5" :key="i" class="w-3.5 h-3.5" :class="i <= Math.round(order.reviews_avg_rating) ? 'text-yellow-400' : 'text-slate-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex-1 text-slate-500 text-sm">Tidak ada detail item</div>

                            <!-- Pricing & Shipping -->
                            <div class="sm:text-right border-t sm:border-t-0 sm:border-l border-slate-100 sm:border-slate-200 pt-4 sm:pt-0 sm:pl-6 min-w-[140px] w-full sm:w-auto">
                                <p class="text-sm text-slate-500 mb-0.5">Total Belanja</p>
                                <p class="text-lg font-bold text-slate-900">Rp {{ formatPrice(order.total_amount) }}</p>
                                <p v-if="order.courier" class="text-xs text-slate-500 mt-1">Kurir: <span class="uppercase font-bold text-slate-700">{{ order.courier }}</span><span v-if="order.shipping_service"> - {{ order.shipping_service }}</span></p>
                            </div>
                            
                            <div class="w-full sm:w-auto flex flex-col gap-2.5">
                                <Link v-if="order.return_request" :href="route('returns.show', order.return_request.return_number)">
                                    <PrimaryButton class="w-full justify-center !bg-orange-50 !text-orange-700 border border-orange-200 hover:!bg-orange-100 !rounded-xl shadow-sm">
                                        Lihat Status Retur
                                    </PrimaryButton>
                                </Link>
                                <Link :href="route('orders.show', order.order_number)">
                                    <PrimaryButton v-if="order.status === 'completed' && order.reviewed_items_count < order.items_count" class="w-full justify-center !bg-blue-600 !text-white border border-blue-600 hover:!bg-blue-700 !rounded-xl shadow-sm">
                                        Tulis Ulasan
                                    </PrimaryButton>
                                    <PrimaryButton v-else class="w-full justify-center !bg-slate-800 !text-white border border-slate-800 hover:!bg-slate-700 !rounded-xl shadow-sm">
                                        {{ order.status === 'completed' ? 'Lihat Detail Ulasan' : 'Lihat Detail Pesanan' }}
                                    </PrimaryButton>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="orders.links.length > 3" class="mt-8 flex justify-center flex-wrap gap-1">
                        <template v-for="(link, p) in orders.links" :key="p">
                            <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-2 text-sm font-semibold text-slate-400 bg-slate-50 border border-slate-200 rounded-lg" v-html="link.label" />
                            <Link v-else :class="{ 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20': link.active, 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200 hover:text-blue-600': !link.active }" class="mr-1 mb-1 px-4 py-2 text-sm font-semibold border rounded-lg transition-all" :href="link.url" v-html="link.label" />
                        </template>
                    </div>

                </div>

            </div>
        </div>
    </StorefrontLayout>
</template>
