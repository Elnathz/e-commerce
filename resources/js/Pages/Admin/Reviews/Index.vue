<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { useImageViewer } from '@/Composables/useImageViewer';
import SharedImageViewerModal from '@/Components/SharedImageViewerModal.vue';

const activeReply = ref(null);
const toggleReplyForm = (id) => {
    activeReply.value = activeReply.value === id ? null : id;
};

const selectedReviews = ref([]);
const selectAll = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedReviews.value = props.reviews.data.map(r => r.id);
    } else {
        selectedReviews.value = [];
    }
};

const bulkModerateForm = useForm({
    review_ids: [],
    action: ''
});

const { isViewerOpen, viewerImages, viewerActiveIndex, viewerTitle, viewerSubtitle, openViewer, closeViewer } = useImageViewer();

const submitBulkModerate = (action) => {
    bulkModerateForm.review_ids = selectedReviews.value;
    bulkModerateForm.action = action;
    bulkModerateForm.post(route('admin.reviews.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedReviews.value = [];
            selectAll.value = false;
        }
    });
};

const selectedReviewsData = computed(() => {
    return props.reviews.data.filter(r => selectedReviews.value.includes(r.id));
});

const canPublish = computed(() => {
    return selectedReviewsData.value.some(r => !r.is_published);
});

const canHide = computed(() => {
    return selectedReviewsData.value.some(r => r.is_published);
});

const filterByRating = (rating) => {
    ratingFilter.value = rating;
};

const props = defineProps({
    reviews: Object,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters.q || '');
const ratingFilter = ref(props.filters.rating || '');
const queueFilter = ref(props.filters.queue || 'all');

const getSLA = (createdAt) => {
    const created = new Date(createdAt);
    const now = new Date();
    const diffHours = Math.floor((now - created) / (1000 * 60 * 60));
    
    if (diffHours < 24) {
        return diffHours <= 0 ? 'BARU SAJA' : `${diffHours} JAM`;
    }
    
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays} HARI`;
};

const replyForms = ref({});

const initReplyForm = (id) => {
    if (!replyForms.value[id]) {
        replyForms.value[id] = useForm({
            admin_reply: ''
        });
    }
};

const submitReply = (review) => {
    const form = replyForms.value[review.id];
    if (form) {
        form.post(route('admin.reviews.reply', review.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.admin_reply = '';
                activeReply.value = null;
            }
        });
    }
};

watch([search, ratingFilter, queueFilter], () => {
    router.get(route('admin.reviews.index'), {
        q: search.value,
        rating: ratingFilter.value,
        queue: queueFilter.value
    }, { preserveState: true, replace: true, preserveScroll: true });
}, { deep: true });

watch(() => props.reviews.data, () => {
    selectedReviews.value = [];
    selectAll.value = false;
});

const togglePublish = (review) => {
    router.patch(route('admin.reviews.toggle', review.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Kelola Ulasan" />

    <AdminLayout>
        <template #header>
            <h2 class="font-bold text-xl text-slate-900 leading-tight">Kelola Ulasan</h2>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Stats Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" v-if="stats">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Total Ulasan</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.total_reviews }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Rating Rata-rata</p>
                        <p class="text-3xl font-black text-yellow-500 mt-1 flex items-center gap-1">
                            {{ stats.average_rating }} <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        </p>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm col-span-1 sm:col-span-2 lg:col-span-1 flex flex-col justify-center">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-2">Distribusi Rating</p>
                        <div class="space-y-1 w-full">
                            <button v-for="i in 5" :key="6-i" @click="filterByRating(6-i)" class="flex items-center gap-2 text-xs w-full hover:bg-slate-50 px-2 py-1 -mx-2 rounded transition-colors group cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <div class="flex items-center text-yellow-500 font-medium w-6 group-hover:text-yellow-600 transition-colors">
                                    {{ 6-i }}<svg class="w-3 h-3 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                </div>
                                <div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-yellow-400 h-full rounded-full transition-all duration-500 group-hover:bg-yellow-500" :style="{ width: stats.total_reviews ? ((stats.distribution[6-i] / stats.total_reviews) * 100) + '%' : '0%' }"></div>
                                </div>
                                <span class="w-6 text-right font-bold text-slate-700 group-hover:text-blue-600">{{ stats.distribution[6-i] }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-yellow-200 shadow-sm flex flex-col justify-center bg-yellow-50/30">
                        <p class="text-sm font-semibold text-yellow-600 uppercase tracking-wide">Belum Dibalas</p>
                        <p class="text-3xl font-black text-yellow-700 mt-1">{{ stats.unreplied_reviews }}</p>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <!-- Queue Tabs & Filters -->
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div class="flex gap-1 overflow-x-auto no-scrollbar pb-1 xl:pb-0">
                            <button @click="queueFilter = 'all'" :class="['px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition-colors', queueFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']">
                                Semua Review
                            </button>
                            <button @click="queueFilter = 'action_needed'" :class="['px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition-colors', queueFilter === 'action_needed' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-600 hover:bg-red-100']">
                                Perlu Tindakan
                            </button>
                            <button @click="queueFilter = 'unreplied'" :class="['px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition-colors flex items-center gap-2', queueFilter === 'unreplied' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-600 hover:bg-amber-100']">
                                Belum Dibalas
                                <span class="bg-white/20 px-1.5 py-0.5 rounded text-xs">{{ stats.unreplied_reviews }}</span>
                            </button>
                        </div>
                        
                        <div class="flex gap-3 w-full xl:w-auto">
                            <input type="text" v-model="search" placeholder="Cari ulasan, nama produk..." class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm w-full sm:w-72 text-sm">
                            <select v-model="ratingFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
                                <option value="">Semua Rating</option>
                                <option value="5">Bintang 5</option>
                                <option value="4">Bintang 4</option>
                                <option value="3">Bintang 3</option>
                                <option value="2">Bintang 2</option>
                                <option value="1">Bintang 1</option>
                            </select>
                        </div>
                    </div>
                    
                    <div :class="['divide-y divide-slate-100', selectedReviews.length > 0 ? 'pb-24' : '']">
                        <div v-for="review in reviews.data" :key="review.id" :class="['p-6 transition-colors hover:bg-slate-50/80 flex flex-col md:flex-row gap-6 items-start relative', review.rating <= 2 ? 'bg-red-50/10 border-l-4 border-red-500' : 'bg-white']">
                            
                            <div class="md:mt-1.5 shrink-0 hidden md:block">
                                <input type="checkbox" :value="review.id" v-model="selectedReviews" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 cursor-pointer">
                            </div>

                            <!-- User & Product -->
                            <div class="w-full md:w-1/4 shrink-0 pr-4">
                                <!-- Badges Group -->
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <div class="md:hidden shrink-0 mt-0.5">
                                        <input type="checkbox" :value="review.id" v-model="selectedReviews" class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 cursor-pointer">
                                    </div>
                                    <span v-if="review.rating <= 2 && !review.admin_reply" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 uppercase tracking-wide border border-red-200">
                                        Perlu Ditindaklanjuti
                                    </span>
                                    <div v-if="!review.admin_reply" class="inline-flex overflow-hidden rounded text-xs font-bold border border-amber-200">
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 uppercase tracking-wide">
                                            Belum Dibalas
                                        </span>
                                        <span class="px-2 py-0.5 bg-amber-500 text-white">
                                            {{ getSLA(review.created_at) }}
                                        </span>
                                    </div>
                                    <span v-else class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm bg-green-100 text-green-800 border border-green-200 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Sudah Dibalas
                                    </span>
                                    <span v-if="!review.is_published" class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm bg-slate-200 text-slate-600 border border-slate-300">
                                        Disembunyikan
                                    </span>
                                </div>
                                
                                <Link :href="route('admin.products.edit', review.product.id)" class="block font-bold text-blue-600 hover:text-blue-800 leading-tight text-base mb-1 cursor-pointer">
                                    {{ review.product.name }}
                                </Link>
                                <p v-if="review.order_item?.variant_name_snapshot" class="text-xs text-slate-500 mb-2 font-medium bg-slate-100 inline-block px-2 py-0.5 rounded border border-slate-200">
                                    Varian: {{ review.order_item.variant_name_snapshot }}
                                </p>
                                
                                <div class="text-sm text-slate-600 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                    <span class="font-medium text-slate-900">{{ review.user.name }}</span>
                                </div>

                                <div v-if="review.order_item?.order" class="text-xs text-slate-500 flex items-center gap-1.5 mt-1.5">
                                    <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                                    <Link :href="route('admin.orders.show', review.order_item.order.id)" class="hover:text-blue-600 font-mono font-medium">{{ review.order_item.order.order_number }}</Link>
                                </div>
                                
                                <p class="text-xs text-slate-400 mt-2 font-medium">{{ new Date(review.created_at).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute:'2-digit'}) }}</p>
                            </div>
                            
                            <!-- Rating & Comment -->
                            <div class="flex-1 w-full min-w-0">
                                <div class="flex items-center gap-1 mb-3">
                                    <svg v-for="i in 5" :key="i" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" :class="['w-5 h-5', i <= review.rating ? 'text-yellow-400' : 'text-slate-200']">
                                        <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <p :class="['text-sm whitespace-pre-wrap leading-relaxed', review.comment ? 'text-slate-700' : 'text-slate-400 italic']">
                                    {{ review.comment || 'Tidak ada teks ulasan disertakan.' }}
                                </p>
                                
                                <div v-if="review.images && review.images.length > 0" class="mt-4 grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-2">
                                    <button type="button" v-for="(img, imgIdx) in review.images" :key="img.id" @click.stop="openViewer(review.images.map(i => ({ url: `/storage/${i.image_path}` })), imgIdx, `Ulasan: ${review.product.name}`, review.order_item?.order?.order_number ? `Order: ${review.order_item.order.order_number}` : '')" class="block rounded-lg overflow-hidden border border-slate-200 hover:border-blue-400 transition-colors shadow-sm cursor-zoom-in focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <img :src="`/storage/${img.image_path}`" class="w-full h-24 object-cover" alt="Review photo">
                                    </button>
                                </div>

                                <!-- Admin Reply Section -->
                                <div class="mt-4 border-t border-slate-100 pt-4">
                                    <div v-if="review.admin_reply && activeReply !== review.id" class="bg-slate-100 p-4 rounded-xl border border-slate-200">
                                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Balasan Toko:</p>
                                        <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ review.admin_reply }}</p>
                                        <p class="text-[11px] text-slate-400 mt-2">{{ new Date(review.replied_at).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit'}) }}</p>
                                    </div>
                                    <div v-else class="mt-3">
                                        <button v-if="activeReply !== review.id" @click="toggleReplyForm(review.id); if (review.admin_reply) replyForms[review.id].admin_reply = review.admin_reply;" class="text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                            <span v-if="review.admin_reply"><svg class="w-4 h-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>Edit Balasan</span>
                                            <span v-else>+ Balas Ulasan</span>
                                        </button>
                                        <form v-if="activeReply === review.id" @submit.prevent="submitReply(review)" class="flex flex-col gap-2 mt-2">
                                            {{ initReplyForm(review.id) }}
                                            <div class="flex flex-wrap gap-2 mb-2">
                                                <button type="button" @click="replyForms[review.id].admin_reply = 'Terima kasih atas ulasannya!'" class="text-xs px-3 py-1.5 font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors border border-slate-200">Terima Kasih</button>
                                                <button type="button" @click="replyForms[review.id].admin_reply = 'Mohon maaf atas kendala yang dialami. Silakan hubungi CS kami agar segera dibantu.'" class="text-xs px-3 py-1.5 font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors border border-slate-200">Mohon Maaf</button>
                                                <button type="button" @click="replyForms[review.id].admin_reply = 'Mohon maaf, produk ini akan kami jadikan bahan evaluasi. Anda dapat mengajukan klaim garansi atau retur melalui pesanan Anda.'" class="text-xs px-3 py-1.5 font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors border border-slate-200">Ajukan Retur</button>
                                            </div>
                                            <textarea v-model="replyForms[review.id].admin_reply" placeholder="Ketik balasan untuk pelanggan ini..." class="w-full text-sm rounded-lg border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" rows="3" required></textarea>
                                            <div class="flex gap-2 justify-end">
                                                <button type="button" @click="toggleReplyForm(review.id)" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">Batal</button>
                                                <button type="submit" :disabled="replyForms[review.id].processing" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg text-sm transition-colors shadow-sm disabled:opacity-50">
                                                    Kirim Balasan
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions 3-Dot -->
                            <div class="absolute top-6 right-6">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button class="p-1 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" /></svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <button @click="togglePublish(review)" class="block w-full text-left px-4 py-2 text-sm leading-5 text-slate-700 hover:bg-slate-100 focus:outline-none focus:bg-slate-100 transition duration-150 ease-in-out">
                                            {{ review.is_published ? 'Sembunyikan Ulasan' : 'Tampilkan Ulasan' }}
                                        </button>
                                        <Link v-if="review.order_item?.order" :href="route('admin.orders.show', review.order_item.order.id)" class="block w-full text-left px-4 py-2 text-sm leading-5 text-slate-700 hover:bg-slate-100 focus:outline-none focus:bg-slate-100 transition duration-150 ease-in-out">
                                            Lihat Pesanan
                                        </Link>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                        
                        <div v-if="reviews.data.length === 0" class="p-12 text-center text-slate-500 font-medium bg-slate-50">
                            Tidak ada ulasan ditemukan.
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="reviews.links && reviews.links.length > 3" class="px-6 py-6 border-t border-slate-100 bg-white">
                        <div class="flex flex-wrap gap-1">
                            <template v-for="(link, key) in reviews.links" :key="key">
                                <div v-if="link.url === null" class="px-4 py-2.5 text-sm font-semibold text-slate-400 border border-slate-200 rounded-lg bg-slate-50" v-html="link.label" />
                                <Link v-else :href="link.url" class="px-4 py-2.5 text-sm font-semibold border rounded-lg transition-all" :class="{ 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20': link.active, 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200 hover:text-blue-600': !link.active }" v-html="link.label" preserve-scroll />
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Floating Bulk Action Bar -->
        <div v-if="selectedReviews.length > 0" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 bg-white border border-slate-200 px-4 py-3 rounded-2xl shadow-[0_-4px_12px_rgba(0,0,0,0.15)] flex items-center gap-4 animate-fade-in-up w-max max-w-[95vw] overflow-x-auto">
            <div class="flex items-center gap-3 pr-4 border-r border-slate-200 shrink-0">
                <span class="flex items-center justify-center w-7 h-7 shrink-0 rounded-full bg-blue-100 text-blue-700 text-sm font-bold">{{ selectedReviews.length }}</span>
                <span class="font-bold text-sm text-slate-700 whitespace-nowrap">Ulasan Terpilih</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button v-if="canPublish" @click="submitBulkModerate('publish')" :disabled="bulkModerateForm.processing" class="text-sm font-bold px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 hover:text-blue-600 hover:border-blue-300 rounded-xl transition-all disabled:opacity-50 flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    Tampilkan
                </button>
                <button v-if="canHide" @click="submitBulkModerate('hide')" :disabled="bulkModerateForm.processing" class="text-sm font-bold px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 rounded-xl transition-all disabled:opacity-50 flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    Sembunyikan
                </button>
            </div>
        </div>
        
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
