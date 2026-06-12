<script setup>
import { computed } from 'vue';
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

const selectClass = 'mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm';

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};

const previewHeadline = computed(() => {
    if (form.type === 'percentage') return `${form.value || 0}% OFF`;
    if (form.type === 'free_shipping') return 'GRATIS ONGKIR';
    return `${formatRupiah(form.value || 0)} OFF`;
});

const previewSubtext = computed(() => {
    const parts = [];
    parts.push(form.min_purchase > 0 ? `Min. belanja ${formatRupiah(form.min_purchase)}` : 'Tanpa minimum belanja');
    if (form.type === 'free_shipping' && form.max_shipping_discount) {
        parts.push(`maks. ongkir ${formatRupiah(form.max_shipping_discount)}`);
    }
    return parts.join(' · ');
});
</script>

<template>
    <Head :title="isEditing ? 'Edit Voucher' : 'Buat Voucher'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.promotions.index')" class="text-slate-400 hover:text-slate-700">
                    ← Kembali
                </Link>
                <h2 class="font-bold text-xl text-slate-900 leading-tight">
                    {{ isEditing ? 'Edit Voucher' : 'Buat Voucher Baru' }}
                </h2>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Main column -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Informasi Dasar -->
                            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide mb-4">Informasi Dasar</h3>
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
                                        <p class="text-xs text-slate-400 mt-1">{{ isEditing ? 'Kode tidak dapat diubah setelah dibuat.' : 'Akan otomatis diubah ke huruf kapital.' }}</p>
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
                                <div class="mt-6">
                                    <InputLabel for="description" value="Deskripsi (Opsional)" />
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm"
                                        placeholder="Catatan internal mengenai voucher ini..."
                                    ></textarea>
                                    <InputError :message="form.errors.description" class="mt-2" />
                                </div>
                            </section>

                            <!-- Aturan Diskon -->
                            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide mb-4">Aturan Diskon</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="type" value="Tipe Diskon" />
                                        <select id="type" v-model="form.type" :class="selectClass">
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
                                        <p class="text-xs text-slate-400 mt-1">Jika persentase, masukkan angka 1-100.</p>
                                        <InputError :message="form.errors.value" class="mt-2" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
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
                                        <p class="text-xs text-slate-400 mt-1">Kosongkan jika gratis penuh.</p>
                                        <InputError :message="form.errors.max_shipping_discount" class="mt-2" />
                                    </div>
                                </div>

                                <div v-if="form.type === 'free_shipping'" class="mt-6">
                                    <InputLabel for="applicable_shipping_type" value="Kurir yang Berlaku" />
                                    <select id="applicable_shipping_type" v-model="form.applicable_shipping_type" :class="selectClass + ' md:w-1/2'">
                                        <option value="all">Semua Kurir</option>
                                        <option value="internal">Hanya Kurir Internal (Semarang)</option>
                                        <option value="external">Hanya Ekspedisi Luar (RajaOngkir)</option>
                                    </select>
                                    <InputError :message="form.errors.applicable_shipping_type" class="mt-2" />
                                </div>
                            </section>

                            <!-- Batas Kuota -->
                            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide mb-4">Batas Kuota Penggunaan</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="max_usage" value="Maksimal Penggunaan Global (Opsional)" />
                                        <TextInput
                                            id="max_usage"
                                            v-model="form.max_usage"
                                            type="number"
                                            class="mt-1 block w-full"
                                        />
                                        <p class="text-xs text-slate-400 mt-1">Kosongkan jika kuota tidak terbatas.</p>
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
                                        <p class="text-xs text-slate-400 mt-1">Berapa kali 1 user bisa pakai voucher ini.</p>
                                        <InputError :message="form.errors.max_usage_per_user" class="mt-2" />
                                    </div>
                                </div>
                            </section>

                            <!-- Periode Berlaku -->
                            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide mb-4">Periode Berlaku</h3>
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
                                <p class="text-xs text-slate-400 mt-3">Kosongkan kedua kolom untuk voucher tanpa batas waktu.</p>
                            </section>
                        </div>

                        <!-- Side column -->
                        <div class="lg:col-span-1 space-y-6">
                            <!-- Live preview -->
                            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide mb-4">Pratinjau</h3>
                                <div class="rounded-xl border-2 border-dashed border-blue-300 bg-blue-50/50 p-4 text-center">
                                    <p class="font-mono font-black text-lg text-blue-700 tracking-widest">{{ form.code || 'KODE-VOUCHER' }}</p>
                                    <p class="text-2xl font-black text-slate-900 mt-1">{{ previewHeadline }}</p>
                                    <p class="text-xs text-slate-500 mt-2">{{ previewSubtext }}</p>
                                </div>
                            </section>

                            <!-- Status -->
                            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide mb-4">Status</h3>
                                <label class="flex items-center justify-between cursor-pointer gap-4">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900">Voucher Aktif</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Voucher nonaktif tidak dapat dipakai pelanggan.</p>
                                    </div>
                                    <span class="relative inline-flex shrink-0">
                                        <input type="checkbox" id="is_active" v-model="form.is_active" class="sr-only peer">
                                        <span class="w-11 h-6 bg-slate-200 peer-checked:bg-emerald-500 rounded-full transition-colors block"></span>
                                        <span class="absolute top-0.5 left-0.5 bg-white rounded-full h-5 w-5 transition-transform peer-checked:translate-x-5"></span>
                                    </span>
                                </label>
                                <InputError :message="form.errors.is_active" class="mt-2" />
                            </section>

                            <!-- Actions -->
                            <div class="flex flex-col gap-3">
                                <PrimaryButton type="submit" class="w-full justify-center py-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Simpan Voucher
                                </PrimaryButton>
                                <Link :href="route('admin.promotions.index')" class="text-center text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors">
                                    Batal
                                </Link>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
