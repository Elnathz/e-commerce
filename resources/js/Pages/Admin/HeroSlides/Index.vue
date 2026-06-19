<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, active: 0 }) },
    limits: { type: Object, default: () => ({ hero_main: 10, hero_side: 4 }) },
});

const heroMainSlides = computed(() => props.slides.filter(s => s.placement === 'hero_main'));
const heroSideSlides = computed(() => props.slides.filter(s => s.placement === 'hero_side'));

const slideImage = (path) => path.startsWith('images/') ? '/' + path : '/storage/' + path;

const linkSummary = (slide) => {
    if (slide.link_type === 'category') return slide.link_label ? `Tujuan: Kategori ${slide.link_label}` : 'Tujuan: Kategori (terhapus)';
    if (slide.link_type === 'product') return slide.link_label ? `Tujuan: Produk ${slide.link_label}` : 'Tujuan: Produk (terhapus)';
    if (slide.link_type === 'custom') return `Tujuan: ${slide.cta_url}`;
    return slide.cta_url ? `Tujuan: ${slide.cta_url}` : 'Tanpa tujuan';
};

const confirmState = ref({ show: false, target: null });
const askDelete = (slide) => { confirmState.value = { show: true, target: slide }; };
const cancelDelete = () => { confirmState.value = { show: false, target: null }; };
const confirmDelete = () => {
    const slide = confirmState.value.target;
    confirmState.value = { show: false, target: null };
    router.delete(route('admin.hero-slides.destroy', slide.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Hero Banner" />
    <AdminLayout>
        <div class="py-6 bg-slate-50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Row -->
                <div class="grid grid-cols-2 sm:grid-cols-2 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-center">
                        <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Total Slide</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-emerald-200 shadow-sm flex flex-col justify-center bg-emerald-50/30">
                        <p class="text-sm font-semibold text-emerald-600 uppercase tracking-wide">Aktif</p>
                        <p class="text-3xl font-black text-emerald-700 mt-1">{{ stats.active }}</p>
                    </div>
                </div>

                <!-- Header + Add Button -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-slate-800">Hero Banner</h1>
                            <p class="text-sm text-slate-500 mt-0.5">{{ stats.active }} aktif dari {{ stats.total }} slide</p>
                        </div>
                        <Link :href="route('admin.hero-slides.create')"
                              class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 transition-colors shadow-sm shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Tambah Slide
                        </Link>
                    </div>

                    <div class="p-4 space-y-6">
                        <template v-if="slides.length">
                            <div class="space-y-3">
                                <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide">
                                    Hero Utama <span class="text-slate-400 font-normal normal-case">({{ heroMainSlides.length }}/{{ limits.hero_main }})</span>
                                </h2>
                                <div v-if="heroMainSlides.length" class="space-y-3">
                                    <div v-for="slide in heroMainSlides" :key="slide.id"
                                         class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 flex items-center gap-4 hover:border-blue-300 transition-colors">
                                        <div class="w-32 h-16 shrink-0 rounded-lg overflow-hidden bg-slate-100">
                                            <img :src="slideImage(slide.image_path)" :alt="slide.title" class="w-full h-full object-cover" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-slate-800 truncate">{{ slide.title }}</span>
                                                <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold" :class="slide.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                                    {{ slide.is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-400 mt-0.5">Urutan: {{ slide.sort_order }} &middot; {{ linkSummary(slide) }}</p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <Link :href="route('admin.hero-slides.edit', slide.id)"
                                                  class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                                Edit
                                            </Link>
                                            <button @click="askDelete(slide)"
                                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm cursor-pointer">
                                                Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-slate-400">Belum ada slide Hero Utama.</p>
                            </div>

                            <div class="space-y-3">
                                <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide">
                                    Banner Samping <span class="text-slate-400 font-normal normal-case">({{ heroSideSlides.length }}/{{ limits.hero_side }})</span>
                                </h2>
                                <div v-if="heroSideSlides.length" class="space-y-3">
                                    <div v-for="slide in heroSideSlides" :key="slide.id"
                                         class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 flex items-center gap-4 hover:border-blue-300 transition-colors">
                                        <div class="w-32 h-16 shrink-0 rounded-lg overflow-hidden bg-slate-100">
                                            <img :src="slideImage(slide.image_path)" :alt="slide.title" class="w-full h-full object-cover" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-slate-800 truncate">{{ slide.title }}</span>
                                                <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold" :class="slide.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                                    {{ slide.is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-400 mt-0.5">Urutan: {{ slide.sort_order }} &middot; {{ linkSummary(slide) }}</p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <Link :href="route('admin.hero-slides.edit', slide.id)"
                                                  class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                                Edit
                                            </Link>
                                            <button @click="askDelete(slide)"
                                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm cursor-pointer">
                                                Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-slate-400">Belum ada slide Banner Samping.</p>
                            </div>
                        </template>
                        <div v-else class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-500">
                            Belum ada slide. Klik "Tambah Slide" untuk membuat banner pertama.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <ConfirmModal :show="confirmState.show" title="Hapus Hero Banner"
            :message='`Hapus slide "${confirmState.target?.title}"? Tindakan ini tidak dapat dibatalkan.`'
            @confirm="confirmDelete" @cancel="cancelDelete" />
    </AdminLayout>
</template>
