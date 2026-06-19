<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
    discount_percent: props.product?.discount_percent || '',
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

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                {{ isEditing ? 'Edit Info Dasar Produk' : 'Tambah Produk Baru' }}
            </h2>
        </template>

        <div class="py-12 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-2xl">
                    <div class="p-6 sm:p-8">
                        
                        <p v-if="!isEditing" class="mb-6 text-sm text-blue-800 bg-blue-50 p-4 rounded-xl border border-blue-200">
                            <strong>Langkah 1:</strong> Isi informasi dasar produk terlebih dahulu. Setelah disimpan, Anda akan diarahkan ke halaman khusus untuk menambahkan <strong class="text-blue-900">Stok, Variasi, dan Foto Produk</strong>.
                        </p>

                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <!-- Category -->
                            <div>
                                <InputLabel for="category_id" value="Kategori Produk" class="font-bold text-slate-700" />
                                <select 
                                    id="category_id" 
                                    v-model="form.category_id" 
                                    required
                                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
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
                                <InputLabel for="name" value="Nama Produk" class="font-bold text-slate-700" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm"
                                    v-model="form.name"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Description -->
                            <div>
                                <InputLabel for="description" value="Deskripsi Lengkap" class="font-bold text-slate-700" />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
                                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <!-- Base Price -->
                                <div>
                                    <InputLabel for="base_price" value="Harga Dasar (Rp)" class="font-bold text-slate-700" />
                                    <TextInput
                                        id="base_price"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm"
                                        v-model="form.base_price"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.base_price" />
                                </div>

                                <!-- Weight -->
                                <div>
                                    <InputLabel for="weight_gram" value="Berat (Gram)" class="font-bold text-slate-700" />
                                    <TextInput
                                        id="weight_gram"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm"
                                        v-model="form.weight_gram"
                                        required
                                    />
                                    <p class="text-xs text-slate-500 mt-1">1000 gram = 1 kg</p>
                                    <InputError class="mt-2" :message="form.errors.weight_gram" />
                                </div>
                            </div>

                            <!-- Discount Percent -->
                            <div>
                                <InputLabel for="discount_percent" value="Diskon Produk (%)" class="font-bold text-slate-700" />
                                <TextInput
                                    id="discount_percent"
                                    type="number"
                                    min="0"
                                    max="95"
                                    step="1"
                                    class="mt-1 block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm"
                                    v-model="form.discount_percent"
                                    placeholder="Opsional, contoh: 10"
                                />
                                <p class="text-xs text-slate-500 mt-1">Berlaku semua varian kecuali yang punya harga diskon sendiri</p>
                                <InputError class="mt-2" :message="form.errors.discount_percent" />
                            </div>

                            <!-- Is Active -->
                            <div>
                                <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                                    <input type="checkbox" v-model="form.is_active" class="mt-0.5 w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 shadow-sm" />
                                    <div>
                                        <span class="block font-bold text-slate-900">Aktifkan Produk Ini</span>
                                        <span class="block text-xs text-slate-500 mt-1">Jika tidak dicentang, produk ini akan disembunyikan dari toko.</span>
                                    </div>
                                </label>
                                <InputError class="mt-2" :message="form.errors.is_active" />
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-4 pt-6 border-t border-slate-200">
                                <PrimaryButton :disabled="form.processing" class="w-full sm:w-auto justify-center !bg-blue-600 hover:!bg-blue-700 !rounded-xl">
                                    {{ isEditing ? 'Simpan Perubahan' : 'Lanjut Tambah Stok' }}
                                </PrimaryButton>
                                <Link :href="route('admin.products.index')" class="text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">
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
