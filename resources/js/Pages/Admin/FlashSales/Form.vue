<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    sale: { type: Object, default: null },
    products: { type: Array, default: () => [] },
});

const isEdit = !!props.sale;

const toDatetimeLocal = (value) => {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const initialItems = (props.sale?.items ?? []).map((it) => ({
    id: it.id,
    product_id: it.variant?.product?.id ?? '',
    product_variant_id: it.product_variant_id,
    sale_price: it.sale_price,
    quota: it.quota,
    sold_count: it.sold_count ?? 0,
}));

const form = useForm({
    name: props.sale?.name ?? '',
    starts_at: toDatetimeLocal(props.sale?.starts_at),
    ends_at: toDatetimeLocal(props.sale?.ends_at),
    is_active: props.sale?.is_active ?? true,
    items: initialItems.length ? initialItems : [
        { id: null, product_id: '', product_variant_id: '', sale_price: '', quota: '', sold_count: 0 },
    ],
});

const variantsFor = (productId) => {
    const product = props.products.find((p) => p.id === productId);
    return product ? product.variants : [];
};

const addItem = () => {
    form.items.push({ id: null, product_id: '', product_variant_id: '', sale_price: '', quota: '', sold_count: 0 });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const onProductChange = (item) => {
    item.product_variant_id = '';
};

const variantPrice = (item) => {
    for (const p of props.products) {
        const v = p.variants.find((v) => v.id === item.product_variant_id);
        if (v) return v;
    }
    return null;
};

const effectivePreview = (item) => {
    const v = variantPrice(item);
    if (!v || item.sale_price === '' || item.sale_price === null) return null;
    const original = v.price;
    const manual = v.discount_price !== null && v.discount_price < original ? v.discount_price : original;
    return { original, manual, sale: Number(item.sale_price) };
};

const submit = () => {
    const url = isEdit ? route('admin.flash-sales.update', props.sale.id) : route('admin.flash-sales.store');
    if (isEdit) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(url);
    } else {
        form.post(url);
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Flash Sale' : 'Tambah Flash Sale'" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto px-4 py-6 space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-800">{{ isEdit ? 'Edit' : 'Tambah' }} Flash Sale</h1>
                <Link :href="route('admin.flash-sales.index')" class="text-sm text-slate-500 hover:text-slate-700">Kembali</Link>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Nama Flash Sale <span class="text-red-500">*</span></label>
                    <input id="name" v-model="form.name" type="text" class="w-full rounded-lg border-slate-300 text-sm" />
                    <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="starts_at" class="block text-sm font-medium text-slate-700 mb-1">Mulai <span class="text-red-500">*</span></label>
                        <input id="starts_at" v-model="form.starts_at" type="datetime-local" class="w-full rounded-lg border-slate-300 text-sm" />
                        <p v-if="form.errors.starts_at" class="text-xs text-red-600 mt-1">{{ form.errors.starts_at }}</p>
                    </div>
                    <div>
                        <label for="ends_at" class="block text-sm font-medium text-slate-700 mb-1">Selesai <span class="text-red-500">*</span></label>
                        <input id="ends_at" v-model="form.ends_at" type="datetime-local" class="w-full rounded-lg border-slate-300 text-sm" />
                        <p v-if="form.errors.ends_at" class="text-xs text-red-600 mt-1">{{ form.errors.ends_at }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-blue-600" />
                    Aktif
                </label>

                <div class="border-t border-slate-200 pt-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Produk Flash Sale</h2>
                        <button type="button" @click="addItem"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 rounded-lg transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Tambah Item
                        </button>
                    </div>

                    <div v-if="form.errors.items" class="text-xs text-red-600 mb-3">{{ form.errors.items }}</div>

                    <div class="space-y-4">
                        <div v-for="(item, index) in form.items" :key="index"
                             class="rounded-xl border border-slate-200 p-4 space-y-3 relative">
                            <button type="button" v-if="form.items.length > 1" @click="removeItem(index)"
                                    class="absolute top-3 right-3 text-slate-400 hover:text-red-600 transition-colors cursor-pointer" :aria-label="`Hapus item ${index + 1}`">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label :for="`product-${index}`" class="block text-xs font-medium text-slate-600 mb-1">Produk</label>
                                    <select :id="`product-${index}`" v-model="item.product_id" @change="onProductChange(item)"
                                            class="w-full rounded-lg border-slate-300 text-sm">
                                        <option value="">Pilih produk...</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label :for="`variant-${index}`" class="block text-xs font-medium text-slate-600 mb-1">Varian</label>
                                    <select :id="`variant-${index}`" v-model="item.product_variant_id"
                                            :disabled="!item.product_id" class="w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-50">
                                        <option value="">Pilih varian...</option>
                                        <option v-for="v in variantsFor(item.product_id)" :key="v.id" :value="v.id">
                                            {{ v.name }} ({{ v.sku }}) - Rp{{ Number(v.price).toLocaleString('id-ID') }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors[`items.${index}.product_variant_id`]" class="text-xs text-red-600 mt-1">
                                        {{ form.errors[`items.${index}.product_variant_id`] }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label :for="`sale_price-${index}`" class="block text-xs font-medium text-slate-600 mb-1">Harga Flash <span class="text-red-500">*</span></label>
                                    <input :id="`sale_price-${index}`" v-model="item.sale_price" type="number" min="0" step="0.01"
                                           class="w-full rounded-lg border-slate-300 text-sm" />
                                    <p v-if="form.errors[`items.${index}.sale_price`]" class="text-xs text-red-600 mt-1">
                                        {{ form.errors[`items.${index}.sale_price`] }}
                                    </p>
                                </div>
                                <div>
                                    <label :for="`quota-${index}`" class="block text-xs font-medium text-slate-600 mb-1">Kuota</label>
                                    <input :id="`quota-${index}`" v-model="item.quota" type="number" min="0"
                                           class="w-full rounded-lg border-slate-300 text-sm" placeholder="Kosongkan = tanpa batas" />
                                    <p class="text-xs text-slate-400 mt-1">Kosongkan = tanpa batas.</p>
                                    <p v-if="item.sold_count" class="text-xs text-slate-400">Terjual: {{ item.sold_count }}</p>
                                    <p v-if="form.errors[`items.${index}.quota`]" class="text-xs text-red-600 mt-1">
                                        {{ form.errors[`items.${index}.quota`] }}
                                    </p>
                                </div>
                            </div>

                            <p v-if="effectivePreview(item)" class="text-xs text-slate-500">
                                Harga normal Rp{{ Number(effectivePreview(item).original).toLocaleString('id-ID') }}
                                <template v-if="effectivePreview(item).manual < effectivePreview(item).original">
                                    &middot; diskon manual Rp{{ Number(effectivePreview(item).manual).toLocaleString('id-ID') }}
                                </template>
                                &middot; harga flash Rp{{ Number(effectivePreview(item).sale).toLocaleString('id-ID') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="route('admin.flash-sales.index')" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Batal</Link>
                    <button type="submit" :disabled="form.processing"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50 cursor-pointer">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
