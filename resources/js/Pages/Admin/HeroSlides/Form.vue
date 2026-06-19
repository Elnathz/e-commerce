<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    slide: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const isEdit = !!props.slide;
const preview = ref(props.slide?.image_path
    ? (props.slide.image_path.startsWith('images/') ? '/' + props.slide.image_path : '/storage/' + props.slide.image_path)
    : null);

// Baris seed lama punya cta_url terisi tapi link_type masih null (dibuat sebelum fitur ini ada).
// Perlakukan sebagai 'custom' di form supaya link-nya tidak diam-diam hilang saat admin edit-simpan.
const initialLinkType = props.slide?.link_type || (props.slide?.cta_url ? 'custom' : '');

const form = useForm({
    _method: isEdit ? 'put' : 'post',
    placement: props.slide?.placement ?? 'hero_main',
    title: props.slide?.title ?? '',
    link_type: initialLinkType,
    link_id: props.slide?.link_id ?? '',
    cta_url: props.slide?.cta_url ?? '',
    sort_order: props.slide?.sort_order ?? 0,
    is_active: props.slide?.is_active ?? true,
    image: null,
});

const onLinkTypeChange = () => {
    form.link_id = '';
    if (form.link_type !== 'custom') form.cta_url = '';
};

const onFile = (e) => {
    const file = e.target.files[0];
    form.image = file;
    if (file) preview.value = URL.createObjectURL(file);
};

const submit = () => {
    const url = isEdit ? route('admin.hero-slides.update', props.slide.id) : route('admin.hero-slides.store');
    form.post(url, { forceFormData: true });
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Hero Banner' : 'Tambah Hero Banner'" />
    <AdminLayout>
        <div class="max-w-3xl mx-auto px-4 py-6 space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-800">{{ isEdit ? 'Edit' : 'Tambah' }} Hero Banner</h1>
                <Link :href="route('admin.hero-slides.index')" class="text-sm text-slate-500 hover:text-slate-700">Kembali</Link>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Posisi Banner <span class="text-red-500">*</span></label>
                    <select v-model="form.placement" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="hero_main">Hero Utama (carousel tengah)</option>
                        <option value="hero_side">Banner Samping</option>
                    </select>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ form.placement === 'hero_main' ? 'Rekomendasi gambar 1280×640px (rasio 2:1).' : 'Rekomendasi gambar 640×300px (rasio ~2:1). Maks 4 banner samping tampil.' }}
                    </p>
                    <p v-if="form.errors.placement" class="text-xs text-red-600 mt-1">{{ form.errors.placement }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Gambar <span class="text-red-500">*</span></label>
                    <div v-if="preview" class="mb-2 rounded-xl overflow-hidden border border-slate-200 aspect-[1200/440] bg-slate-100">
                        <img :src="preview" alt="preview" class="w-full h-full object-cover" />
                    </div>
                    <input type="file" accept="image/*" @change="onFile" class="block w-full text-sm text-slate-600" />
                    <p v-if="form.errors.image" class="text-xs text-red-600 mt-1">{{ form.errors.image }}</p>
                    <p class="text-xs text-slate-400 mt-1">Rasio ~1200x440px. Maks 2MB.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Judul / Alt text <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" class="w-full rounded-lg border-slate-300 text-sm" />
                    <p v-if="form.errors.title" class="text-xs text-red-600 mt-1">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tujuan Tombol Banner</label>
                    <p class="text-xs text-slate-400 mb-2">Saat banner ini diklik pengunjung, ke mana mereka diarahkan? (opsional)</p>
                    <select v-model="form.link_type" @change="onLinkTypeChange" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Tanpa tujuan (gambar saja, tidak bisa diklik)</option>
                        <option value="category">Kategori produk</option>
                        <option value="product">Produk tertentu</option>
                        <option value="custom">Link manual (untuk yang familiar URL situs)</option>
                    </select>

                    <div v-if="form.link_type === 'category'" class="mt-2">
                        <select v-model="form.link_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Pilih kategori...</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div v-else-if="form.link_type === 'product'" class="mt-2">
                        <select v-model="form.link_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Pilih produk...</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div v-else-if="form.link_type === 'custom'" class="mt-2">
                        <input v-model="form.cta_url" type="text" placeholder="/search?categories[]=3" class="w-full rounded-lg border-slate-300 text-sm" />
                        <p class="text-xs text-slate-400 mt-1">Path relatif situs ini, mis. /search atau /products/nama-produk.</p>
                    </div>

                    <p v-if="form.errors.link_id" class="text-xs text-red-600 mt-1">{{ form.errors.link_id }}</p>
                    <p v-if="form.errors.cta_url" class="text-xs text-red-600 mt-1">{{ form.errors.cta_url }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Urutan</label>
                    <input v-model="form.sort_order" type="number" min="0" class="w-full rounded-lg border-slate-300 text-sm" />
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-blue-600" />
                    Aktif (tampil di storefront)
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="route('admin.hero-slides.index')" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Batal</Link>
                    <button type="submit" :disabled="form.processing"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50 cursor-pointer">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
