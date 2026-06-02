<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    reviews: Object,
    filters: Object,
});

const search = ref(props.filters.q || '');
const ratingFilter = ref(props.filters.rating || '');

watch([search, ratingFilter], () => {
    router.get(route('admin.reviews.index'), {
        q: search.value,
        rating: ratingFilter.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
}, { deep: true });

const togglePublish = (review) => {
    router.patch(route('admin.reviews.toggle', review.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Kelola Ulasan" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kelola Ulasan</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 border-b border-gray-200 flex gap-4">
                        <input type="text" v-model="search" placeholder="Cari ulasan, produk, atau user..." class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-1/3">
                        <select v-model="ratingFilter" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Semua Rating</option>
                            <option value="5">Bintang 5</option>
                            <option value="4">Bintang 4</option>
                            <option value="3">Bintang 3</option>
                            <option value="2">Bintang 2</option>
                            <option value="1">Bintang 1</option>
                        </select>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan / Produk</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating & Ulasan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Foto</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="review in reviews.data" :key="review.id" :class="{'bg-red-50': !review.is_published}">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ review.user.name }}</div>
                                        <div class="text-sm text-gray-500 truncate max-w-[200px]">{{ review.product.name }}</div>
                                        <div class="text-xs text-gray-400 mt-1">{{ new Date(review.created_at).toLocaleDateString('id-ID') }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex text-yellow-400 mb-1">
                                            <svg v-for="i in 5" :key="i" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" :class="['w-4 h-4', i <= review.rating ? 'text-yellow-400' : 'text-gray-200']">
                                                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap line-clamp-3">{{ review.comment || '-' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-1" v-if="review.images.length > 0">
                                            <a v-for="img in review.images" :key="img.id" :href="`/storage/${img.image_path}`" target="_blank">
                                                <img :src="`/storage/${img.image_path}`" class="w-10 h-10 object-cover rounded border border-gray-200 hover:opacity-75">
                                            </a>
                                        </div>
                                        <span v-else class="text-xs text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', review.is_published ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                            {{ review.is_published ? 'Ditampilkan' : 'Disembunyikan' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="togglePublish(review)" class="text-indigo-600 hover:text-indigo-900 focus:outline-none">
                                            {{ review.is_published ? 'Sembunyikan' : 'Tampilkan' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="reviews.data.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">Tidak ada ulasan ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="reviews.links && reviews.links.length > 3" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex flex-wrap -mb-1">
                            <template v-for="(link, key) in reviews.links" :key="key">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                                <Link v-else :href="link.url" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" :class="{ 'bg-indigo-50 text-indigo-600 border-indigo-200': link.active }" v-html="link.label" preserve-scroll />
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>
