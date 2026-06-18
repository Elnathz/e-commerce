<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ settings: { type: Object, required: true } });

const form = useForm({
    announcement_active: props.settings.announcement_active,
    announcement_text: props.settings.announcement_text ?? '',
    announcement_link_url: props.settings.announcement_link_url ?? '',
    announcement_link_label: props.settings.announcement_link_label ?? '',
});

const submit = () => form.put(route('admin.settings.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Pengaturan Toko" />
    <AdminLayout>
        <div class="py-6 bg-slate-50 min-h-screen">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div>
                    <h1 class="text-xl font-bold text-slate-800">Pengaturan Toko</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Konfigurasi tampilan dan konten storefront.</p>
                </div>

                <form @submit.prevent="submit" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-700">Banner Pengumuman</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Tampil di bar atas storefront. Nonaktif = bar sambutan biasa.</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input v-model="form.announcement_active" type="checkbox" class="rounded border-slate-300 text-blue-600" />
                        Aktifkan banner pengumuman
                    </label>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Teks pengumuman</label>
                        <input v-model="form.announcement_text" type="text" placeholder="Gratis ongkir pembelian pertama!" class="w-full rounded-lg border-slate-300 text-sm" />
                        <p v-if="form.errors.announcement_text" class="text-xs text-red-600 mt-1">{{ form.errors.announcement_text }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Label tautan</label>
                            <input v-model="form.announcement_link_label" type="text" placeholder="Belanja Sekarang" class="w-full rounded-lg border-slate-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">URL tautan</label>
                            <input v-model="form.announcement_link_url" type="text" placeholder="/search" class="w-full rounded-lg border-slate-300 text-sm" />
                        </div>
                    </div>

                    <div v-if="$page.props.flash?.success" class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700 font-medium">
                        {{ $page.props.flash.success }}
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing"
                                class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50 cursor-pointer">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </AdminLayout>
</template>
