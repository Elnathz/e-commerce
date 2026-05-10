<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const page = usePage();

// Cart data from shared props - we fetch fresh data when drawer opens
const cartItems = computed(() => {
    // The drawer relies on the cart page data if available,
    // otherwise we show a simplified version from cartCount
    return page.props.cartItems || [];
});

const cartCount = computed(() => page.props.cartCount || 0);

const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};

const goToCart = () => {
    emit('close');
    router.get(route('cart.index'));
};

const updateQuantity = (itemId, newQty) => {
    if (newQty < 1) return;
    router.patch(route('cart.updateItem', itemId), { quantity: newQty }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const removeItem = (itemId) => {
    router.delete(route('cart.removeItem', itemId), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <!-- Overlay -->
    <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed inset-0 bg-black/40 z-[80] backdrop-blur-sm" @click="emit('close')"></div>
    </Transition>

    <!-- Drawer Panel -->
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
    >
        <div v-if="show" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[90] flex flex-col">
            
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                    Keranjang
                    <span v-if="cartCount > 0" class="text-sm font-medium text-gray-500">({{ cartCount }})</span>
                </h2>
                <button @click="emit('close')" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Items List -->
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <!-- Empty State -->
                <div v-if="cartItems.length === 0 && cartCount === 0" class="flex flex-col items-center justify-center h-full text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-20 h-20 text-gray-200 mb-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                    <p class="text-gray-500 font-medium mb-1">Keranjang masih kosong</p>
                    <p class="text-gray-400 text-sm mb-4">Yuk, mulai belanja!</p>
                    <button @click="emit('close')" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Lanjut Belanja</button>
                </div>

                <!-- Cart Items (when full data available from /cart page) -->
                <div v-else-if="cartItems.length > 0" class="space-y-4">
                    <div v-for="item in cartItems" :key="item.id" class="flex gap-3 p-3 rounded-xl border border-gray-100 hover:border-gray-200 transition-colors" :class="{ 'opacity-50': !item.is_active || item.available_stock <= 0 }">
                        <!-- Image -->
                        <Link :href="`/products/${item.product_slug}`" @click="emit('close')" class="w-16 h-16 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 border border-gray-100">
                            <img v-if="item.image" :src="item.image" :alt="item.product_name" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-xs">No Img</div>
                        </Link>
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <Link :href="`/products/${item.product_slug}`" @click="emit('close')" class="text-sm font-semibold text-gray-900 line-clamp-1 hover:text-blue-600">{{ item.product_name }}</Link>
                            <p class="text-xs text-gray-500 mt-0.5">{{ item.variant_name }}</p>
                            
                            <!-- Price change warning -->
                            <p v-if="item.price_changed" class="text-xs text-amber-600 mt-0.5">Harga berubah</p>
                            
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-sm font-bold text-blue-600">{{ formatPrice(item.current_price) }}</span>
                                <!-- Qty Controls -->
                                <div class="flex items-center gap-1">
                                    <button @click="updateQuantity(item.id, item.quantity - 1)" :disabled="item.quantity <= 1" class="w-6 h-6 rounded border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-100 disabled:opacity-30 text-xs">−</button>
                                    <span class="w-7 text-center text-xs font-semibold">{{ item.quantity }}</span>
                                    <button @click="updateQuantity(item.id, item.quantity + 1)" :disabled="item.quantity >= item.available_stock" class="w-6 h-6 rounded border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-100 disabled:opacity-30 text-xs">+</button>
                                </div>
                            </div>
                        </div>
                        <!-- Delete -->
                        <button @click="removeItem(item.id)" class="self-start p-1 text-gray-300 hover:text-red-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Simplified view (when only cartCount is available, no detailed data) -->
                <div v-else class="flex flex-col items-center justify-center h-full text-center">
                    <p class="text-gray-500 font-medium mb-3">{{ cartCount }} item di keranjang</p>
                    <button @click="goToCart" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors">Lihat Keranjang</button>
                </div>
            </div>

            <!-- Footer -->
            <div v-if="cartItems.length > 0" class="border-t border-gray-100 px-5 py-4 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Subtotal</span>
                    <span class="text-lg font-bold text-gray-900">{{ formatPrice(cartItems.reduce((sum, i) => sum + i.subtotal, 0)) }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button @click="goToCart" class="py-3 rounded-xl text-sm font-bold border-2 border-blue-600 text-blue-600 hover:bg-blue-50 transition-colors">
                        Lihat Keranjang
                    </button>
                    <button class="py-3 rounded-xl text-sm font-bold bg-blue-600 text-white hover:bg-blue-700 transition-colors cursor-not-allowed opacity-60" disabled title="Checkout tersedia di Sprint 5">
                        Checkout
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
