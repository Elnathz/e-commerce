<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    sort_order: props.category?.sort_order || 0,
    is_active: props.category ? Boolean(props.category.is_active) : true,
    image: null,
});

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

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900 dark:text-white">
                {{ isEditing ? 'Edit Kategori' : 'Tambah Kategori Baru' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 sm:p-8">
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <!-- Name -->
                            <div>
                                <InputLabel for="name" value="Nama Kategori" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white"
                                    v-model="form.name"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Parent Category -->
                            <div>
                                <InputLabel for="parent_id" value="Kategori Induk (Opsional)" />
                                <select 
                                    id="parent_id" 
                                    v-model="form.parent_id" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white sm:text-sm"
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
                                <InputLabel for="image" value="Gambar Kategori (Opsional)" />
                                <div class="mt-1 flex items-center">
                                    <input 
                                        type="file" 
                                        id="image" 
                                        @input="form.image = $event.target.files[0]"
                                        class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-gray-600 transition-colors"
                                        accept="image/*"
                                    />
                                </div>
                                <p v-if="isEditing && category.image_path" class="mt-2 text-sm text-gray-500 dark:text-gray-400">Gambar saat ini sudah tersimpan. Unggah baru untuk mengganti.</p>
                                <InputError class="mt-2" :message="form.errors.image" />
                            </div>

                            <!-- Sort Order -->
                            <div>
                                <InputLabel for="sort_order" value="Urutan Tampilan (Angka)" />
                                <TextInput
                                    id="sort_order"
                                    type="number"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white"
                                    v-model="form.sort_order"
                                />
                                <InputError class="mt-2" :message="form.errors.sort_order" />
                            </div>

                            <!-- Is Active -->
                            <div class="flex items-center gap-3 border border-gray-200 dark:border-gray-700 p-4 rounded-md">
                                <Checkbox name="is_active" v-model:checked="form.is_active" class="text-black dark:text-white focus:ring-black dark:focus:ring-white dark:bg-gray-900 dark:border-gray-700" />
                                <div>
                                    <InputLabel for="is_active" value="Aktifkan Kategori Ini" />
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Jika tidak dicentang, kategori tidak akan muncul di halaman pembeli.</p>
                                </div>
                                <InputError class="mt-2" :message="form.errors.is_active" />
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <PrimaryButton :disabled="form.processing" class="w-full sm:w-auto justify-center bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                                    Simpan Kategori
                                </PrimaryButton>
                                <Link :href="route('admin.categories.index')" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline transition-colors">
                                    Batal
                                </Link>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
