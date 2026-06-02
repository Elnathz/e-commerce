<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    orders: Object,
    currentStatus: String
});

const statuses = [
    { value: 'all', label: 'Semua' },
    { value: 'pending', label: 'Belum Bayar' },
    { value: 'paid', label: 'Dibayar' },
    { value: 'processing', label: 'Diproses' },
    { value: 'shipped', label: 'Dikirim' },
    { value: 'completed', label: 'Selesai' },
    { value: 'cancelled', label: 'Dibatalkan' },
    { value: 'refunded', label: 'Dikembalikan' },
];

const changeStatus = (status) => {
    router.get(route('admin.orders.index'), { status }, { preserveState: true, replace: true });
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

const formatPrice = (price) => {
    return Number(price).toLocaleString('id-ID');
};

const isStale = (order) => {
    const updatedDate = new Date(order.updated_at);
    const threeDaysAgo = new Date();
    threeDaysAgo.setDate(threeDaysAgo.getDate() - 3);
    
    return updatedDate < threeDaysAgo;
};

const groupedOrders = computed(() => {
    if (!props.orders || !props.orders.data) return [];
    
    const groups = {};
    props.orders.data.forEach(order => {
        const dateStr = new Date(order.created_at).toLocaleDateString('id-ID', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
        if (!groups[dateStr]) {
            groups[dateStr] = [];
        }
        groups[dateStr].push(order);
    });
    
    return Object.keys(groups).map(date => ({
        date,
        orders: groups[date]
    }));
});

const collapsedDates = ref({});
const toggleDate = (date) => {
    collapsedDates.value[date] = !collapsedDates.value[date];
};
</script>

<template>
    <Head title="Manajemen Pesanan" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                Manajemen Pesanan
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <!-- Status Tabs -->
                <div class="mb-6 flex overflow-x-auto border-b border-slate-200">
                    <button
                        v-for="status in statuses"
                        :key="status.value"
                        @click="changeStatus(status.value)"
                        :class="[
                            'whitespace-nowrap px-6 py-3 font-medium text-sm transition-colors',
                            currentStatus === status.value
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
                        ]"
                    >
                        {{ status.label }}
                    </button>
                </div>

                <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-2xl">
                    <div class="p-6">
                        
                        <div v-if="orders.data.length === 0" class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center font-medium text-slate-500">
                            Tidak ada pesanan untuk status ini.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-slate-600">
                                <thead class="bg-slate-50 text-xs uppercase text-slate-700 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 font-bold">Nomor Pesanan</th>
                                        <th class="px-4 py-3 font-bold">Waktu Pesan</th>
                                        <th class="px-4 py-3 font-bold">Pelanggan</th>
                                        <th class="px-4 py-3 font-bold">Total</th>
                                        <th class="px-4 py-3 font-bold text-center">Status</th>
                                        <th class="px-4 py-3 font-bold text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="group in groupedOrders" :key="group.date">
                                        <!-- Group Header -->
                                        <tr @click="toggleDate(group.date)" class="bg-slate-100 hover:bg-slate-200 cursor-pointer border-b border-slate-200 transition-colors">
                                            <td colspan="6" class="px-4 py-3 font-bold text-slate-800">
                                                <div class="flex items-center gap-2">
                                                    <svg :class="{'rotate-180': collapsedDates[group.date]}" class="w-4 h-4 transition-transform text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                    {{ group.date }} <span class="text-xs font-normal text-slate-500 ml-2">({{ group.orders.length }} pesanan)</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Orders -->
                                        <template v-if="!collapsedDates[group.date]">
                                            <tr v-for="order in group.orders" :key="order.id" class="border-b border-slate-100 hover:bg-slate-50">
                                                <td class="px-4 py-3 font-semibold text-slate-900">{{ order.order_number }}</td>
                                                <td class="px-4 py-3">{{ new Date(order.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</td>
                                                <td class="px-4 py-3">
                                                    {{ order.user ? order.user.name : 'Unknown' }}
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-slate-700">Rp {{ formatPrice(order.total_amount) }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    <span :class="['px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide inline-block mb-1', getStatusClass(order.status)]">
                                                        {{ statuses.find(s => s.value === order.status)?.label || order.status }}
                                                    </span>
                                                    <div v-if="(order.status === 'paid' || order.status === 'processing') && isStale(order)" class="mt-1">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-red-600 text-white shadow-sm animate-pulse">
                                                            PRIORITAS
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <Link :href="route('admin.orders.show', order.id)" class="text-blue-600 font-bold hover:text-blue-800 hover:underline">
                                                        Lihat Detail
                                                    </Link>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="orders.links.length > 3" class="mt-8 flex flex-wrap gap-1">
                            <template v-for="(link, p) in orders.links" :key="p">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-2 text-sm font-semibold text-slate-400 bg-slate-50 border border-slate-200 rounded-lg" v-html="link.label" />
                                <Link v-else :class="{ 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20': link.active, 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200 hover:text-blue-600': !link.active }" class="mr-1 mb-1 px-4 py-2 text-sm font-semibold border rounded-lg transition-all" :href="link.url" v-html="link.label" />
                            </template>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
