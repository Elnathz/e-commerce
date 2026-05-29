<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import AddressFormModal from '@/Components/Storefront/AddressFormModal.vue';
import axios from 'axios';

const props = defineProps({
    method: String,
    addresses: Array,
    cartItems: Array,
    subtotal: Number,
    totalWeight: Number,
    rajaongkirKeyExists: Boolean,
    itemIds: String
});

// State
const selectedAddressId = ref(null);
const selectedCourier = ref('jne');
const shippingCost = ref(0);
const shippingOptions = ref([]);
const isLoadingShipping = ref(false);
const shippingError = ref('');
const showAddressModal = ref(false);
const isPlacingOrder = ref(false);
const orderError = ref('');

// Form for placing order
const form = useForm({
    method: props.method,
    address_id: null,
    courier: null,
    shipping_service: null,
    shipping_cost: 0,
    notes: '',
    item_ids: props.itemIds || ''
});

// Format currency
const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};

const totalAmount = computed(() => {
    return props.subtotal + shippingCost.value;
});

const selectedAddress = computed(() => {
    if (!props.addresses || props.addresses.length === 0) return null;
    return props.addresses.find(a => a.id === selectedAddressId.value) || props.addresses.find(a => a.is_default) || props.addresses[0];
});

const isInternalCourierAvailable = computed(() => {
    if (!selectedAddress.value) return false;
    return selectedAddress.value.city && selectedAddress.value.city.toLowerCase().includes('semarang');
});

// Check for out-of-stock items
const outOfStockItems = computed(() => {
    return props.cartItems.filter(item => item.available_stock < item.quantity);
});

const hasStockIssue = computed(() => outOfStockItems.value.length > 0);

// Initialize
onMounted(() => {
    if (props.method === 'delivery' && selectedAddress.value) {
        selectedAddressId.value = selectedAddress.value.id;
        form.address_id = selectedAddress.value.id;
        calculateShipping();
    }
});

// Watch for address or courier change to recalculate shipping
watch([selectedAddressId, selectedCourier], () => {
    if (props.method === 'delivery') {
        if (selectedCourier.value === 'internal' && !isInternalCourierAvailable.value) {
            selectedCourier.value = 'jne'; // fallback
            return;
        }
        form.address_id = selectedAddressId.value;
        calculateShipping();
    }
});

const calculateShipping = async () => {
    if (props.method !== 'delivery' || !selectedAddress.value) return;

    isLoadingShipping.value = true;
    shippingError.value = '';
    shippingOptions.value = [];
    shippingCost.value = 0;
    form.courier = null;
    form.shipping_service = null;
    form.shipping_cost = 0;

    try {
        const response = await axios.post('/checkout/shipping-cost', {
            destination_city: selectedAddress.value.city_id,
            weight: props.totalWeight,
            courier: selectedCourier.value
        });

        if (response.data.success && response.data.results && response.data.results.length > 0) {
            shippingOptions.value = response.data.results[0].costs;

            // Auto-select first option
            if (shippingOptions.value.length > 0) {
                selectShippingOption(shippingOptions.value[0]);
            }
        } else {
            shippingError.value = 'Tidak ada layanan pengiriman yang tersedia.';
        }
    } catch (error) {
        shippingError.value = error.response?.data?.message || 'Gagal menghitung ongkos kirim.';
    } finally {
        isLoadingShipping.value = false;
    }
};

const selectShippingOption = (option) => {
    shippingCost.value = option.cost[0].value;
    form.courier = selectedCourier.value;
    form.shipping_service = option.service;
    form.shipping_cost = option.cost[0].value;
};

const placeOrder = () => {
    if (hasStockIssue.value) {
        orderError.value = 'Beberapa item stoknya tidak mencukupi. Silakan kembali ke keranjang.';
        return;
    }

    if (props.method === 'delivery' && (!form.address_id || !form.shipping_service)) {
        orderError.value = 'Pilih alamat dan layanan pengiriman terlebih dahulu.';
        return;
    }

    orderError.value = '';
    form.post(route('checkout.store'));
};

const onAddressSaved = () => {
    // Reload page to get updated addresses list
    router.reload({ only: ['addresses'] });
};
</script>

<template>
    <Head title="Checkout" />

    <StorefrontLayout>
        <div class="bg-gray-50 py-8 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-8">Checkout</h1>

                <!-- Global Error -->
                <div v-if="orderError" class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-sm font-semibold text-red-600 flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mt-0.5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z" /></svg>
                    <span>{{ orderError }}</span>
                </div>

                <!-- Stock Warning -->
                <div v-if="hasStockIssue" class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200">
                    <h4 class="font-bold text-amber-800 text-sm mb-2">⚠️ Stok Tidak Mencukupi</h4>
                    <ul class="text-sm text-amber-700 space-y-1">
                        <li v-for="item in outOfStockItems" :key="item.id">
                            <strong>{{ item.product_name }}</strong> ({{ item.variant_name }})
                            — tersedia: {{ item.available_stock }}, diminta: {{ item.quantity }}
                        </li>
                    </ul>
                    <Link href="/cart" class="inline-block mt-3 text-sm font-bold text-amber-800 underline">Kembali ke Keranjang</Link>
                </div>

                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Left Column: Shipping & Payment Info -->
                    <div class="flex-1 space-y-6">

                        <!-- Alert if API Key is missing -->
                        <div v-if="method === 'delivery' && !rajaongkirKeyExists" class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z" /></svg>
                            <div>
                                <h4 class="font-bold">Konfigurasi API RajaOngkir Belum Lengkap</h4>
                                <p class="text-sm">Admin harus mengatur RAJAONGKIR_API_KEY di environment agar kalkulasi ongkir berfungsi.</p>
                            </div>
                        </div>

                        <!-- Fulfillment Method Info -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                                Metode Pengiriman
                            </h2>
                            <div class="flex items-center justify-between p-4 bg-blue-50 border border-blue-100 rounded-xl">
                                <div>
                                    <p class="font-bold text-blue-900">{{ method === 'delivery' ? 'Dikirim (Delivery)' : 'Ambil di Toko (Pickup)' }}</p>
                                    <p class="text-sm text-blue-700">{{ method === 'delivery' ? 'Pesanan akan dikirim ke alamat Anda.' : 'Silakan ambil pesanan Anda di MegaMart Semarang.' }}</p>
                                </div>
                                <Link :href="route('cart.index')" class="text-sm font-semibold text-blue-600 hover:text-blue-800">Ubah</Link>
                            </div>
                        </div>

                        <!-- Address Selection (Only for Delivery) -->
                        <div v-if="method === 'delivery'" class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                    Alamat Pengiriman
                                </h2>
                                <button @click="showAddressModal = true" class="text-sm font-semibold text-blue-600 hover:text-blue-800">+ Tambah Alamat Baru</button>
                            </div>

                            <div v-if="addresses.length > 0" class="space-y-3">
                                <label v-for="address in addresses" :key="address.id" class="flex gap-3 p-4 rounded-xl border-2 cursor-pointer transition-colors" :class="selectedAddressId === address.id ? 'border-blue-600 bg-blue-50/50' : 'border-gray-100 hover:border-blue-200'">
                                    <div class="pt-0.5">
                                        <input type="radio" :value="address.id" v-model="selectedAddressId" class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-bold text-gray-900">{{ address.recipient_name }}</span>
                                            <span class="text-sm text-gray-500">({{ address.label }})</span>
                                            <span v-if="address.is_default" class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase">Utama</span>
                                        </div>
                                        <p class="text-sm text-gray-800">{{ address.phone }}</p>
                                        <p class="text-sm text-gray-500 mt-1">{{ address.address_detail }}</p>
                                        <p class="text-sm text-gray-500">{{ address.district ? address.district + ', ' : '' }}{{ address.city }}, {{ address.province }} {{ address.postal_code }}</p>
                                    </div>
                                </label>
                            </div>
                            <div v-else class="text-center py-6 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                <p class="text-gray-500 mb-3">Anda belum memiliki alamat tersimpan.</p>
                                <button @click="showAddressModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700">Tambah Alamat</button>
                            </div>
                        </div>

                        <!-- Shipping Options (Only for Delivery) -->
                        <div v-if="method === 'delivery' && addresses.length > 0" class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                                Opsi Pengiriman
                            </h2>

                            <!-- Courier Selector -->
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Pilih Kurir</label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <label class="cursor-pointer">
                                        <input type="radio" value="jne" v-model="selectedCourier" class="peer sr-only">
                                        <div class="h-full flex items-center justify-center p-3 text-center rounded-xl border-2 peer-checked:border-blue-600 peer-checked:bg-blue-50 border-gray-100 hover:border-blue-200 font-bold text-gray-700 peer-checked:text-blue-700 transition-colors">JNE</div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" value="pos" v-model="selectedCourier" class="peer sr-only">
                                        <div class="h-full flex items-center justify-center p-3 text-center rounded-xl border-2 peer-checked:border-blue-600 peer-checked:bg-blue-50 border-gray-100 hover:border-blue-200 font-bold text-gray-700 peer-checked:text-blue-700 transition-colors">POS</div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" value="tiki" v-model="selectedCourier" class="peer sr-only">
                                        <div class="h-full flex items-center justify-center p-3 text-center rounded-xl border-2 peer-checked:border-blue-600 peer-checked:bg-blue-50 border-gray-100 hover:border-blue-200 font-bold text-gray-700 peer-checked:text-blue-700 transition-colors">TIKI</div>
                                    </label>
                                    <label v-if="isInternalCourierAvailable" class="cursor-pointer">
                                        <input type="radio" value="internal" v-model="selectedCourier" class="peer sr-only">
                                        <div class="h-full flex flex-col items-center justify-center p-2 text-center rounded-xl border-2 peer-checked:border-blue-600 peer-checked:bg-blue-50 border-gray-100 hover:border-blue-200 font-bold text-gray-700 peer-checked:text-blue-700 transition-colors">
                                            <span>Internal</span>
                                            <span class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 mt-0.5 rounded uppercase leading-tight text-center">Semarang</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div v-if="isLoadingShipping" class="py-6 text-center text-gray-500">
                                <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <p class="text-sm">Menghitung ongkos kirim...</p>
                            </div>

                            <!-- Error State -->
                            <div v-else-if="shippingError" class="py-4 text-center text-red-500 bg-red-50 rounded-xl">
                                <p class="text-sm font-semibold">{{ shippingError }}</p>
                            </div>

                            <!-- Services List -->
                            <div v-else-if="shippingOptions.length > 0" class="space-y-3">
                                <label v-for="option in shippingOptions" :key="option.service" class="flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-colors" :class="form.shipping_service === option.service ? 'border-blue-600 bg-blue-50/50' : 'border-gray-100 hover:border-blue-200'">
                                    <div class="flex items-center gap-3">
                                        <input type="radio" :value="option.service" @change="selectShippingOption(option)" :checked="form.shipping_service === option.service" class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <div>
                                            <p class="font-bold text-gray-900">{{ option.service }}</p>
                                            <p class="text-sm text-gray-500">{{ option.description }} <span v-if="option.cost[0].etd">({{ option.cost[0].etd }} hari)</span></p>
                                        </div>
                                    </div>
                                    <div class="font-bold text-gray-900">
                                        {{ formatPrice(option.cost[0].value) }}
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                Catatan Pesanan
                            </h2>
                            <textarea v-model="form.notes" rows="3" placeholder="Contoh: Tolong packing rapi, barang fragile..."
                                class="w-full px-4 py-3 rounded-xl bg-gray-50 border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 text-sm resize-none"></textarea>
                        </div>

                    </div>

                    <!-- Right Column: Order Summary -->
                    <div class="lg:w-96 shrink-0">
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 sticky top-24">
                            <h2 class="text-lg font-bold text-gray-900 mb-4">Ringkasan Pesanan</h2>

                            <!-- Items -->
                            <div class="space-y-4 mb-6 max-h-64 overflow-y-auto pr-2">
                                <div v-for="item in cartItems" :key="item.id" class="flex gap-3">
                                    <div class="w-16 h-16 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 border border-gray-100">
                                        <img v-if="item.image" :src="item.image" :alt="item.product_name" class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-xs">No Img</div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-semibold text-gray-900 line-clamp-1">{{ item.product_name }}</h3>
                                        <p class="text-xs text-gray-500">{{ item.variant_name }}</p>
                                        <div class="flex justify-between items-center mt-1">
                                            <span class="text-xs text-gray-500">{{ item.quantity }} x {{ formatPrice(item.current_price) }}</span>
                                            <span class="text-sm font-bold text-gray-900">{{ formatPrice(item.current_price * item.quantity) }}</span>
                                        </div>
                                        <!-- Stock warning per item -->
                                        <p v-if="item.available_stock < item.quantity" class="text-xs text-red-500 font-semibold mt-1">
                                            ⚠️ Stok tersisa {{ item.available_stock }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 pt-4 space-y-3 mb-6">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Total Harga ({{ cartItems.length }} barang)</span>
                                    <span class="font-semibold text-gray-900">{{ formatPrice(subtotal) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Total Berat</span>
                                    <span class="font-semibold text-gray-900">{{ totalWeight / 1000 }} kg</span>
                                </div>
                                <div v-if="method === 'delivery'" class="flex justify-between text-sm">
                                    <span class="text-gray-500">Ongkos Kirim</span>
                                    <span class="font-semibold text-gray-900">{{ shippingCost > 0 ? formatPrice(shippingCost) : '-' }}</span>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 pt-4 mb-6">
                                <div class="flex justify-between items-end">
                                    <span class="font-bold text-gray-900">Total Belanja</span>
                                    <span class="text-xl font-bold text-blue-600">{{ formatPrice(totalAmount) }}</span>
                                </div>
                            </div>

                            <button
                                @click="placeOrder"
                                class="w-full py-3.5 rounded-xl text-sm font-bold bg-blue-600 text-white hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="hasStockIssue || form.processing || (method === 'delivery' && (!form.address_id || !form.shipping_service || isLoadingShipping))"
                            >
                                {{ form.processing ? 'Memproses...' : 'Buat Pesanan' }}
                            </button>

                            <p class="text-xs text-gray-400 text-center mt-3">Batas waktu pembayaran: 24 jam setelah pesanan dibuat</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Address Form Modal -->
        <AddressFormModal :show="showAddressModal" @close="showAddressModal = false" @saved="onAddressSaved" />
    </StorefrontLayout>
</template>
