<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    category: Object,
    parentCategories: Array,
});

const isEditing = !!props.category;

const form = useForm({
    name: props.category?.name || '',
    parent_id: props.category?.parent_id || '',
    image: null,
    sort_order: props.category?.sort_order || 0,
    is_active: props.category ? props.category.is_active : true,
});

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file && file.size > 2 * 1024 * 1024) { // 2MB
        form.errors.image = "Ukuran gambar tidak boleh lebih dari 2MB.";
        form.image = null;
        event.target.value = ''; // Reset input
    } else {
        form.image = file;
        form.errors.image = null;
    }
};

const submit = () => {
    if (isEditing) {
        // We use post with _method=PUT to support file uploads in Laravel
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('admin.categories.update', props.category.id));
    } else {
        form.post(route('admin.categories.store'));
    }
};
</script>

<template>
    <Head :title="isEditing ? 'Edit Kategori' : 'Tambah Kategori'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                {{ isEditing ? 'Edit Kategori' : 'Tambah Kategori Baru' }}
            </h2>
        </template>

        <div class="py-12 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-2xl">
                    <div class="p-6 sm:p-8">
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <!-- Name -->
                            <div>
                                <InputLabel for="name" value="Nama Kategori" class="font-bold text-slate-700" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm"
                                    v-model="form.name"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Parent Category -->
                            <div>
                                <InputLabel for="parent_id" value="Kategori Induk (Opsional)" class="font-bold text-slate-700" />
                                <select 
                                    id="parent_id" 
                                    v-model="form.parent_id" 
                                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                >
                                    <option value="">-- Tidak Ada (Kategori Utama) --</option>
                                    <option v-for="parent in parentCategories" :key="parent.id" :value="parent.id">
                                        {{ parent.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.parent_id" />
                            </div>

                            <!-- Image -->
                            <div>
                                <InputLabel for="image" value="Gambar Kategori (Opsional, Maks 2MB)" class="font-bold text-slate-700" />
                                <div class="mt-1 flex items-center">
                                    <input 
                                        type="file" 
                                        id="image" 
                                        @change="handleImageUpload"
                                        class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors cursor-pointer"
                                        accept="image/*"
                                    />
                                </div>
                                <p v-if="isEditing && category.image_path" class="mt-2 text-sm text-slate-500">Gambar saat ini sudah tersimpan. Unggah baru untuk mengganti.</p>
                                <InputError class="mt-2" :message="form.errors.image" />
                            </div>

                            <!-- Is Active -->
                            <div>
                                <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                    <input type="checkbox" v-model="form.is_active" class="mt-0.5 w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 shadow-sm" />
                                    <div>
                                        <span class="block font-bold text-slate-900">Aktifkan Kategori Ini</span>
                                        <span class="block text-xs text-slate-500 mt-1">Jika tidak dicentang, kategori tidak akan muncul di halaman pembeli.</span>
                                    </div>
                                </label>
                                <InputError class="mt-2" :message="form.errors.is_active" />
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-4 pt-6 border-t border-slate-200">
                                <PrimaryButton :disabled="form.processing" class="w-full sm:w-auto justify-center !bg-blue-600 hover:!bg-blue-700 !rounded-xl">
                                    Simpan Kategori
                                </PrimaryButton>
                                <Link :href="route('admin.categories.index')" class="text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">
                                    Batal
                                </Link>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
