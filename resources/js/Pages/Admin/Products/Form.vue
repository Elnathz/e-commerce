<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    product: Object,
    categories: Array,
});

const isEditing = !!props.product;

const form = useForm({
    category_id: props.product?.category_id || '',
    name: props.product?.name || '',
    description: props.product?.description || '',
    base_price: props.product?.base_price || '',
    weight_gram: props.product?.weight_gram || '',
    is_active: props.product ? Boolean(props.product.is_active) : true,
});

const submit = () => {
    if (isEditing) {
        form.put(route('admin.products.update', props.product.id));
    } else {
        form.post(route('admin.products.store'));
    }
};
</script>

<template>
    <Head :title="isEditing ? 'Edit Produk' : 'Tambah Produk Baru'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900 dark:text-white">
                {{ isEditing ? 'Edit Info Dasar Produk' : 'Tambah Produk Baru' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 sm:p-8">
                        
                        <p v-if="!isEditing" class="mb-6 text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 p-4 rounded border border-gray-200 dark:border-gray-700">
                            <strong>Langkah 1:</strong> Isi informasi dasar produk terlebih dahulu. Setelah disimpan, Anda akan diarahkan ke halaman khusus untuk menambahkan <strong class="text-gray-900 dark:text-white">Stok, Variasi, dan Foto Produk</strong>.
                        </p>

                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <!-- Category -->
                            <div>
                                <InputLabel for="category_id" value="Kategori Produk" />
                                <select 
                                    id="category_id" 
                                    v-model="form.category_id" 
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white sm:text-sm"
                                >
                                    <option value="" disabled>-- Pilih Kategori --</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.category_id" />
                            </div>

                            <!-- Name -->
                            <div>
                                <InputLabel for="name" value="Nama Produk" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white"
                                    v-model="form.name"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Description -->
                            <div>
                                <InputLabel for="description" value="Deskripsi Lengkap" />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white sm:text-sm"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <!-- Base Price -->
                                <div>
                                    <InputLabel for="base_price" value="Harga Dasar (Rp)" />
                                    <TextInput
                                        id="base_price"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white"
                                        v-model="form.base_price"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.base_price" />
                                </div>

                                <!-- Weight -->
                                <div>
                                    <InputLabel for="weight_gram" value="Berat (Gram)" />
                                    <TextInput
                                        id="weight_gram"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white"
                                        v-model="form.weight_gram"
                                        required
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">1000 gram = 1 kg</p>
                                    <InputError class="mt-2" :message="form.errors.weight_gram" />
                                </div>
                            </div>

                            <!-- Is Active -->
                            <div class="flex items-center gap-3 border border-gray-200 dark:border-gray-700 p-4 rounded-md">
                                <Checkbox name="is_active" v-model:checked="form.is_active" class="text-black dark:text-white focus:ring-black dark:focus:ring-white dark:bg-gray-900 dark:border-gray-700" />
                                <div>
                                    <InputLabel for="is_active" value="Aktifkan Produk Ini" />
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Jika tidak dicentang, produk ini akan disembunyikan dari toko.</p>
                                </div>
                                <InputError class="mt-2" :message="form.errors.is_active" />
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <PrimaryButton :disabled="form.processing" class="w-full sm:w-auto justify-center bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                                    {{ isEditing ? 'Simpan Perubahan' : 'Lanjut Tambah Stok' }}
                                </PrimaryButton>
                                <Link :href="route('admin.products.index')" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline transition-colors">
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
