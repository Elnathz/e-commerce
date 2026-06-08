<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    promotion: Object,
});

const isEditing = !!props.promotion.id;

const form = useForm({
    code: props.promotion.code || '',
    name: props.promotion.name || '',
    type: props.promotion.type || 'percentage',
    value: props.promotion.value || 0,
    min_purchase: props.promotion.min_purchase || 0,
    max_usage: props.promotion.max_usage || null,
    max_usage_per_user: props.promotion.max_usage_per_user || null,
    applicable_shipping_type: props.promotion.applicable_shipping_type || 'all',
    max_shipping_discount: props.promotion.max_shipping_discount || null,
    valid_from: props.promotion.valid_from ? props.promotion.valid_from.substring(0, 16) : '',
    valid_until: props.promotion.valid_until ? props.promotion.valid_until.substring(0, 16) : '',
    description: props.promotion.description || '',
    is_active: props.promotion.is_active ?? true,
});

const submit = () => {
    if (isEditing) {
        form.put(route('admin.promotions.update', props.promotion.id));
    } else {
        form.post(route('admin.promotions.store'));
    }
};
</script>

<template>
    <Head :title="isEditing ? 'Edit Voucher' : 'Buat Voucher'" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ isEditing ? 'Edit Voucher' : 'Buat Voucher Baru' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800 p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Code & Name -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="code" value="Kode Voucher" />
                                <TextInput
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    class="mt-1 block w-full uppercase"
                                    :disabled="isEditing"
                                    required
                                />
                                <InputError :message="form.errors.code" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="name" value="Nama Promo" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>
                        </div>

                        <!-- Type & Value -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="type" value="Tipe Diskon" />
                                <select 
                                    id="type"
                                    v-model="form.type"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="percentage">Persentase (%)</option>
                                    <option value="fixed_amount">Nominal (Rp)</option>
                                    <option value="free_shipping">Gratis Ongkir</option>
                                </select>
                                <InputError :message="form.errors.type" class="mt-2" />
                            </div>
                            <div v-if="form.type !== 'free_shipping'">
                                <InputLabel for="value" value="Nilai Diskon" />
                                <TextInput
                                    id="value"
                                    v-model="form.value"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <div class="text-xs text-gray-500 mt-1">Jika persentase, masukkan angka 1-100.</div>
                                <InputError :message="form.errors.value" class="mt-2" />
                            </div>
                        </div>

                        <!-- Rules -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="min_purchase" value="Minimal Pembelian (Rp)" />
                                <TextInput
                                    id="min_purchase"
                                    v-model="form.min_purchase"
                                    type="number"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.min_purchase" class="mt-2" />
                            </div>
                            <div v-if="form.type === 'free_shipping'">
                                <InputLabel for="max_shipping_discount" value="Maksimal Diskon Ongkir (Opsional)" />
                                <TextInput
                                    id="max_shipping_discount"
                                    v-model="form.max_shipping_discount"
                                    type="number"
                                    class="mt-1 block w-full"
                                />
                                <div class="text-xs text-gray-500 mt-1">Kosongkan jika gratis penuh.</div>
                                <InputError :message="form.errors.max_shipping_discount" class="mt-2" />
                            </div>
                            <div v-if="form.type === 'free_shipping'">
                                <InputLabel for="applicable_shipping_type" value="Kurir yang Berlaku" />
                                <select 
                                    id="applicable_shipping_type"
                                    v-model="form.applicable_shipping_type"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="all">Semua Kurir</option>
                                    <option value="internal">Hanya Kurir Internal (Semarang)</option>
                                    <option value="external">Hanya Ekspedisi Luar (RajaOngkir)</option>
                                </select>
                                <InputError :message="form.errors.applicable_shipping_type" class="mt-2" />
                            </div>
                        </div>

                        <!-- Quota limits -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="max_usage" value="Maksimal Penggunaan Global (Opsional)" />
                                <TextInput
                                    id="max_usage"
                                    v-model="form.max_usage"
                                    type="number"
                                    class="mt-1 block w-full"
                                />
                                <div class="text-xs text-gray-500 mt-1">Kosongkan jika kuota tidak terbatas.</div>
                                <InputError :message="form.errors.max_usage" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="max_usage_per_user" value="Maksimal Penggunaan per User (Opsional)" />
                                <TextInput
                                    id="max_usage_per_user"
                                    v-model="form.max_usage_per_user"
                                    type="number"
                                    class="mt-1 block w-full"
                                />
                                <div class="text-xs text-gray-500 mt-1">Berapa kali 1 user bisa pakai voucher ini.</div>
                                <InputError :message="form.errors.max_usage_per_user" class="mt-2" />
                            </div>
                        </div>

                        <!-- Validity -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="valid_from" value="Berlaku Mulai (Opsional)" />
                                <TextInput
                                    id="valid_from"
                                    v-model="form.valid_from"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.valid_from" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="valid_until" value="Berlaku Sampai (Opsional)" />
                                <TextInput
                                    id="valid_until"
                                    v-model="form.valid_until"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.valid_until" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="description" value="Deskripsi (Opsional)" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            ></textarea>
                            <InputError :message="form.errors.description" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                v-model="form.is_active"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            >
                            <label for="is_active" class="font-medium text-sm text-gray-700 dark:text-gray-300">Voucher Aktif</label>
                            <InputError :message="form.errors.is_active" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <Link :href="route('admin.promotions.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900">
                                Batal
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Simpan Voucher
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
