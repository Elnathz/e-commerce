<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineOptions({ layout: StorefrontLayout });

const props = defineProps({
    cartItems: { type: Array, default: () => [] },
});

const selectedItems = ref(props.cartItems.map(item => item.id));

const isSelected = (id) => selectedItems.value.includes(id);

const toggleItem = (id) => {
    if (isSelected(id)) {
        selectedItems.value = selectedItems.value.filter(i => i !== id);
    } else {
        selectedItems.value.push(id);
    }
};

const toggleAll = () => {
    if (selectedItems.value.length === props.cartItems.length) {
        selectedItems.value = [];
    } else {
        selectedItems.value = props.cartItems.map(i => i.id);
    }
};

const allSelected = computed(() => selectedItems.value.length === props.cartItems.length && props.cartItems.length > 0);

const selectedCartItems = computed(() => props.cartItems.filter(i => selectedItems.value.includes(i.id)));

const subtotal = computed(() => selectedCartItems.value.reduce((sum, i) => sum + i.subtotal, 0));

const totalQuantity = computed(() => selectedCartItems.value.reduce((sum, i) => sum + i.quantity, 0));

const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};

const updateQuantity = (itemId, newQty) => {
    if (newQty < 1) return;
    router.patch(route('cart.updateItem', itemId), { quantity: newQty }, {
        preserveScroll: true,
    });
};

const removeItem = (itemId) => {
    router.delete(route('cart.removeItem', itemId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Keranjang Belanja" />

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 md:py-10">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-6 md:mb-8">
            <Link href="/" class="md:hidden p-1 text-gray-500 hover:text-gray-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </Link>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900">Keranjang Belanja</h1>
            <span v-if="cartItems.length > 0" class="text-sm text-gray-500">({{ cartItems.length }} item)</span>
        </div>

        <!-- Empty Cart -->
        <div v-if="cartItems.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="0.75" stroke="currentColor" class="w-28 h-28 text-gray-200 mb-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Keranjang masih kosong</h2>
            <p class="text-gray-500 mb-6 max-w-sm">Belum ada produk di keranjang kamu. Yuk, mulai belanja dan temukan produk favoritmu!</p>
            <Link href="/" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-xl text-sm font-bold transition-colors">
                Mulai Belanja
            </Link>
        </div>

        <!-- Cart Content -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">

            <!-- Items Column -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Select All Header (Desktop) -->
                <div class="hidden md:flex items-center gap-3 px-4 py-3 bg-gray-50 rounded-xl">
                    <button @click="toggleAll" :class="['w-5 h-5 rounded border-2 flex items-center justify-center transition-colors flex-shrink-0', allSelected ? 'bg-blue-600 border-blue-600' : 'border-gray-300 hover:border-blue-400']">
                        <svg v-if="allSelected" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-white"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" /></svg>
                    </button>
                    <span class="text-sm font-semibold text-gray-700">Pilih Semua ({{ cartItems.length }} produk)</span>
                </div>

                <!-- Cart Item Cards -->
                <div v-for="item in cartItems" :key="item.id" class="flex gap-3 md:gap-4 p-4 rounded-2xl border border-gray-100 hover:border-gray-200 bg-white transition-all" :class="{ 'opacity-50 pointer-events-none': !item.is_active || item.available_stock <= 0 }">
                    
                    <!-- Checkbox -->
                    <button @click="toggleItem(item.id)" :class="['w-5 h-5 rounded border-2 flex items-center justify-center transition-colors flex-shrink-0 mt-1', isSelected(item.id) ? 'bg-blue-600 border-blue-600' : 'border-gray-300 hover:border-blue-400']">
                        <svg v-if="isSelected(item.id)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-white"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" /></svg>
                    </button>

                    <!-- Image -->
                    <Link :href="`/products/${item.product_slug}`" class="w-20 h-20 md:w-24 md:h-24 rounded-xl bg-gray-50 overflow-hidden flex-shrink-0 border border-gray-100">
                        <img v-if="item.image" :src="item.image" :alt="item.product_name" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-xs">No Image</div>
                    </Link>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <Link :href="`/products/${item.product_slug}`" class="text-sm md:text-base font-semibold text-gray-900 line-clamp-2 hover:text-blue-600 transition-colors">{{ item.product_name }}</Link>
                        <p class="text-xs md:text-sm text-gray-500 mt-0.5">Varian: {{ item.variant_name }}</p>
                        
                        <!-- Price Change Warning -->
                        <div v-if="item.price_changed" class="mt-1 flex items-center gap-1 text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full w-fit">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" /></svg>
                            Harga telah berubah
                        </div>
                        
                        <!-- Out of stock warning -->
                        <div v-if="item.available_stock <= 0" class="mt-1 text-xs text-red-600 font-semibold">Stok habis</div>
                        
                        <!-- Price + Controls -->
                        <div class="flex items-center justify-between mt-3">
                            <span class="text-base md:text-lg font-bold text-blue-600">{{ formatPrice(item.current_price) }}</span>
                            <div class="flex items-center gap-2">
                                <!-- Qty Controls -->
                                <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                    <button @click="updateQuantity(item.id, item.quantity - 1)" :disabled="item.quantity <= 1" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 disabled:opacity-30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                                    </button>
                                    <span class="w-10 text-center text-sm font-semibold border-x border-gray-200 py-1">{{ item.quantity }}</span>
                                    <button @click="updateQuantity(item.id, item.quantity + 1)" :disabled="item.quantity >= item.available_stock" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 disabled:opacity-30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </button>
                                </div>
                                <!-- Delete -->
                                <button @click="removeItem(item.id)" class="p-2 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Column (Desktop) -->
            <div class="hidden lg:block">
                <div class="sticky top-28 bg-gray-50 rounded-2xl p-6 space-y-4">
                    <h3 class="text-base font-bold text-gray-900">Ringkasan Belanja</h3>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Total Harga ({{ totalQuantity }} barang)</span>
                            <span class="font-semibold text-gray-900">{{ formatPrice(subtotal) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex justify-between mb-4">
                            <span class="font-bold text-gray-900">Total</span>
                            <span class="text-xl font-black text-gray-900">{{ formatPrice(subtotal) }}</span>
                        </div>
                        <button class="w-full py-3.5 rounded-xl text-sm font-bold bg-blue-600 text-white hover:bg-blue-700 transition-colors cursor-not-allowed opacity-60" disabled title="Checkout tersedia di Sprint 5">
                            Checkout ({{ totalQuantity }})
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Sticky Bottom Bar -->
        <div v-if="cartItems.length > 0" class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 z-50 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
            <div class="flex items-center gap-3">
                <!-- Select All -->
                <button @click="toggleAll" :class="['w-5 h-5 rounded border-2 flex items-center justify-center transition-colors flex-shrink-0', allSelected ? 'bg-blue-600 border-blue-600' : 'border-gray-300']">
                    <svg v-if="allSelected" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-white"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" /></svg>
                </button>
                <span class="text-xs text-gray-500">Semua</span>
                
                <!-- Total -->
                <div class="flex-1 text-right">
                    <div class="text-xs text-gray-500">Total</div>
                    <div class="text-base font-black text-gray-900">{{ formatPrice(subtotal) }}</div>
                </div>
                
                <!-- Checkout Button -->
                <button class="px-6 py-3 rounded-xl text-sm font-bold bg-blue-600 text-white cursor-not-allowed opacity-60" disabled>
                    Checkout ({{ totalQuantity }})
                </button>
            </div>
        </div>
    </div>
</template>
