<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    product: Object,
});

// Variant Form
const variantForm = useForm({
    product_id: props.product.id,
    sku: '',
    name: '',
    price: props.product.base_price,
    stock: '',
    weight_gram: props.product.weight_gram,
    is_active: true,
});

const submitVariant = () => {
    variantForm.post(route('admin.products.variants.store'), {
        preserveScroll: true,
        onSuccess: () => variantForm.reset('sku', 'name', 'stock'),
    });
};

// Image Form
const imageForm = useForm({
    product_id: props.product.id,
    images: null,
});

const submitImage = () => {
    imageForm.post(route('admin.products.images.store'), {
        preserveScroll: true,
        onSuccess: () => imageForm.reset('images'),
    });
};

// Update Primary Image
const setPrimaryImage = (imageId) => {
    const form = useForm({ is_primary: true });
    form.put(route('admin.products.images.update', imageId), { preserveScroll: true });
};
</script>

<template>
    <Head :title="'Detail: ' + product.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-900 dark:text-white">
                    Kelola Produk: {{ product.name }}
                </h2>
                <Link :href="route('admin.products.index')" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-black dark:hover:text-white underline transition-colors">
                    &larr; Kembali ke Daftar Produk
                </Link>
            </div>
        </template>

        <div class="py-12 space-y-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                
                <!-- Section 1: Product Variants (Stock) -->
                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 rounded-t-lg">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Varian & Stok</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Produk Anda membutuhkan minimal satu varian (contoh: "Ukuran M" atau sekadar "Standar") untuk bisa memiliki stok.</p>
                    </div>
                    
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-8">
                        
                        <!-- Form Add Variant -->
                        <div class="md:col-span-1">
                            <form @submit.prevent="submitVariant" class="space-y-4">
                                <div>
                                    <InputLabel value="Nama Varian (Misal: Merah / Ukuran L)" />
                                    <TextInput v-model="variantForm.name" class="w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white" required />
                                    <InputError :message="variantForm.errors.name" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Kode SKU" />
                                    <TextInput v-model="variantForm.sku" class="w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white" required />
                                    <InputError :message="variantForm.errors.sku" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Jumlah Stok" />
                                    <TextInput v-model="variantForm.stock" type="number" min="0" class="w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white" required />
                                    <InputError :message="variantForm.errors.stock" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Harga Khusus (Rp)" />
                                    <TextInput v-model="variantForm.price" type="number" min="0" class="w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-black dark:focus:border-white focus:ring-black dark:focus:ring-white" required />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Otomatis terisi harga dasar. Ubah jika berbeda.</p>
                                    <InputError :message="variantForm.errors.price" class="mt-1" />
                                </div>
                                
                                <PrimaryButton :disabled="variantForm.processing" class="w-full justify-center bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white mt-4">
                                    Tambah Varian
                                </PrimaryButton>
                            </form>
                        </div>

                        <!-- Variant List -->
                        <div class="md:col-span-2">
                            <div v-if="product.variants.length === 0" class="h-full border-2 border-dashed border-gray-300 dark:border-gray-600 rounded flex flex-col items-center justify-center p-8 text-gray-500 dark:text-gray-400 text-center">
                                Belum ada varian.<br>Silakan tambah varian di samping agar pembeli bisa membeli produk ini.
                            </div>
                            
                            <div v-else class="flex flex-col gap-3">
                                <div v-for="variant in product.variants" :key="variant.id" class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-4 border border-gray-200 dark:border-gray-700 rounded">
                                    <div>
                                        <h4 class="font-bold text-gray-900 dark:text-white">{{ variant.name }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            SKU: {{ variant.sku }} &bull; Harga: Rp {{ Number(variant.price).toLocaleString('id-ID') }}
                                        </p>
                                        <p class="text-sm font-semibold mt-1 text-gray-800 dark:text-gray-200">
                                            Stok Tersedia: {{ variant.stock - variant.reserved_stock }} 
                                            <span class="text-xs text-gray-400 dark:text-gray-500 font-normal">(Total: {{ variant.stock }}, Dibooking: {{ variant.reserved_stock }})</span>
                                        </p>
                                    </div>
                                    <Link :href="route('admin.products.variants.destroy', variant.id)" method="delete" as="button" class="mt-3 sm:mt-0 px-3 py-1 text-sm text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded border border-transparent transition-colors">
                                        Hapus
                                    </Link>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Section 2: Product Images -->
                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 rounded-t-lg">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Galeri Foto Produk</h3>
                    </div>
                    
                    <div class="p-6">
                        <!-- Upload Form -->
                        <form @submit.prevent="submitImage" class="flex flex-col sm:flex-row items-end gap-4 mb-8">
                            <div class="w-full sm:flex-1">
                                <InputLabel value="Pilih Foto (Bisa lebih dari satu)" />
                                <input 
                                    type="file" 
                                    multiple
                                    @input="imageForm.images = $event.target.files"
                                    class="block w-full mt-1 text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-gray-600 transition-colors"
                                    accept="image/*"
                                />
                                <InputError :message="imageForm.errors['images'] || imageForm.errors['images.0']" class="mt-1" />
                            </div>
                            <PrimaryButton :disabled="imageForm.processing || !imageForm.images" class="w-full sm:w-auto justify-center bg-gray-900 hover:bg-gray-800 text-white dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                                Unggah Foto
                            </PrimaryButton>
                        </form>

                        <!-- Image Grid -->
                        <div v-if="product.images.length === 0" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded p-12 text-center text-gray-500 dark:text-gray-400">
                            Belum ada foto.
                        </div>

                        <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div v-for="img in product.images" :key="img.id" class="relative group border border-gray-200 dark:border-gray-700 rounded overflow-hidden">
                                
                                <div class="aspect-square bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
                                    <img :src="'/storage/' + img.image_path" class="w-full h-full object-cover" />
                                </div>
                                
                                <div v-if="img.is_primary" class="absolute top-2 left-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs px-2 py-1 rounded font-bold">
                                    UTAMA
                                </div>

                                <div class="p-3 flex flex-col gap-2">
                                    <button 
                                        v-if="!img.is_primary"
                                        @click="setPrimaryImage(img.id)"
                                        class="text-xs w-full py-1 border border-gray-900 dark:border-gray-100 text-gray-900 dark:text-gray-100 hover:bg-gray-900 dark:hover:bg-white hover:text-white dark:hover:text-gray-900 rounded transition-colors"
                                    >
                                        Jadikan Utama
                                    </button>
                                    <Link 
                                        :href="route('admin.products.images.destroy', img.id)" 
                                        method="delete" 
                                        as="button" 
                                        class="text-xs w-full py-1 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                                    >
                                        Hapus Foto
                                    </Link>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
