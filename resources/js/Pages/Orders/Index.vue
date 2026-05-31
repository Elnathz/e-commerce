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
    { value: 'paid', label: 'Dibayar' },
    { value: 'processing', label: 'Dikemas' },
    { value: 'shipped', label: 'Dikirim' },
    { value: 'completed', label: 'Selesai' },
    { value: 'cancelled', label: 'Dibatalkan' },
];

const changeStatus = (status) => {
    router.get(route('orders.index'), { status }, { preserveState: true, replace: true });
};

const getStatusDisplay = (status) => {
    const found = statuses.find(s => s.value === status);
    return found ? found.label : status;
};

const getStatusClass = (status) => {
    if (status === 'pending') return 'bg-yellow-100 text-yellow-800';
    if (status === 'paid' || status === 'processing') return 'bg-purple-100 text-purple-800';
    if (status === 'shipped') return 'bg-indigo-100 text-indigo-800';
    if (status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'cancelled' || status === 'refunded') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
};

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
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
                                <span :class="['px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide', getStatusClass(order.status)]">
                                    {{ getStatusDisplay(order.status) }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body (Summary) -->
                        <div class="p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
                            <div>
                                <p class="text-sm text-slate-500 mb-1">Total Belanja</p>
                                <p class="text-lg font-bold text-slate-900">Rp {{ formatPrice(order.total_amount) }}</p>
                                <p class="text-sm text-slate-500 mt-2">Kurir: <span class="uppercase font-semibold text-slate-700">{{ order.courier }}</span></p>
                            </div>
                            
                            <div class="w-full sm:w-auto">
                                <Link :href="route('orders.show', order.order_number)">
                                    <PrimaryButton class="w-full justify-center !bg-white !text-blue-600 border border-blue-600 hover:!bg-blue-50 !rounded-xl shadow-sm">
                                        Lihat Detail Pesanan
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
