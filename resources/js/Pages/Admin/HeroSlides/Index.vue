<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    slides: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, active: 0 }) },
});

const slideImage = (path) => path.startsWith('images/') ? '/' + path : '/storage/' + path;

const destroy = (slide) => {
    if (confirm(`Hapus slide "${slide.title}"?`)) {
        router.delete(route('admin.hero-slides.destroy', slide.id), { preserveScroll: true });
    }
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

                    <div class="p-4 space-y-3">
                        <div v-if="slides.length" class="space-y-3">
                            <div v-for="slide in slides" :key="slide.id"
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
                                    <p class="text-sm text-slate-500 truncate mt-0.5">{{ slide.subtitle }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Urutan: {{ slide.sort_order }} &middot; CTA: {{ slide.cta_label || '-' }}</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <Link :href="route('admin.hero-slides.edit', slide.id)"
                                          class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm">
                                        Edit
                                    </Link>
                                    <button @click="destroy(slide)"
                                            class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm cursor-pointer">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-500">
                            Belum ada slide. Klik "Tambah Slide" untuk membuat banner pertama.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>
