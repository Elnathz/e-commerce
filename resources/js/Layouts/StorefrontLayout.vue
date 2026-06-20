<script setup>
import { ref, watch, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import CartDrawer from '@/Components/Storefront/CartDrawer.vue';
import ShoppingMethodDrawer from '@/Components/Storefront/ShoppingMethodDrawer.vue';
import CategoryMegaMenu from '@/Components/Storefront/CategoryMegaMenu.vue';
import BoltIcon from '@/Components/Storefront/Icons/BoltIcon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const showingMobileMenu = ref(false);
const showCartDrawer = ref(false);
const showShoppingMethodDrawer = ref(false);
const page = usePage();
const searchQuery = ref(page.props.filters?.q || '');

const cartCount = computed(() => page.props.cartCount || 0);

// Keep search bar in sync if we navigate or search changes
watch(() => page.props.filters?.q, (newQ) => {
    searchQuery.value = newQ || '';
});

const submitSearch = () => {
    if (searchQuery.value.trim()) {
        router.get('/search', { q: searchQuery.value.trim() });
    } else {
        router.get('/search');
    }
};

const handleCheckout = () => {
    showCartDrawer.value = false;
    showShoppingMethodDrawer.value = true;
};

const handleProceedCheckout = (method) => {
    const items = page.props.cartItems || [];
    const activeItemIds = items
        .filter(item => item.is_active && item.available_stock > 0)
        .map(item => item.id);
        
    if (activeItemIds.length === 0) {
        triggerToast('Tidak ada item valid di keranjang untuk checkout.', 'error');
        return;
    }

    router.get(route('checkout.index'), {
        method: method,
        items: activeItemIds.join(',')
    });
};

// Toast notification
const toastMessage = ref('');
const toastType = ref('success');
const showToast = ref(false);
let toastTimeout = null;

const triggerToast = (message, type = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    showToast.value = true;
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => { showToast.value = false; }, 3000);
};

// Watch for flash messages from cart operations
watch(() => page.props.flash?.cart_success, (msg) => {
    if (msg) {
        triggerToast(msg, 'success');
        // Open cart drawer on desktop after successful add
        if (window.innerWidth >= 768) {
            showCartDrawer.value = true;
        }
    }
});

watch(() => page.props.flash?.cart_error, (msg) => {
    if (msg) triggerToast(msg, 'error');
});
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 font-body">
        
        <!-- Top Bar: announcement dinamis (bila aktif) atau fallback jujur -->
        <div class="text-xs py-2 hidden md:block"
             :class="page.props.announcement ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500'">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <!-- Announcement aktif -->
                <template v-if="page.props.announcement">
                    <div class="flex items-center gap-2 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M5.25 9a6.75 6.75 0 0 1 13.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 0 1-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 1 1-7.48 0 24.585 24.585 0 0 1-4.831-1.244.75.75 0 0 1-.298-1.205A8.217 8.217 0 0 0 5.25 9.75V9Z" /></svg>
                        {{ page.props.announcement.text }}
                    </div>
                    <Link v-if="page.props.announcement.link_url"
                          :href="page.props.announcement.link_url"
                          class="font-semibold underline underline-offset-2 hover:opacity-80 transition-opacity duration-150">
                        {{ page.props.announcement.link_label || 'Selengkapnya' }}
                    </Link>
                </template>
                <!-- Fallback jujur -->
                <template v-else>
                    <div>Selamat datang di MegaMart &mdash; belanja hemat tiap hari!</div>
                    <Link :href="route('orders.index')" class="flex items-center gap-1 hover:text-blue-600 transition-colors duration-150">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                        Lacak Pesanan
                    </Link>
                </template>
            </div>
        </div>

        <!-- Main Navbar -->
        <nav class="bg-white border-b border-gray-100 py-4 sticky top-0 z-50 shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center gap-4">
                    
                    <!-- Left: Logo -->
                    <div class="flex items-center shrink-0">
                        <!-- Hamburger for mobile -->
                        <button @click="showingMobileMenu = !showingMobileMenu" class="md:hidden mr-2 text-blue-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 6h18M3 18h18" /></svg>
                        </button>
                        <Link href="/" class="flex items-center gap-2">
                            <img src="/images/logo/gemini-svg.svg?v=4" alt="MegaMart" class="w-40 h-auto object-contain" />
                        </Link>
                    </div>

                    <!-- Kategori mega-menu (desktop) -->
                    <div class="hidden md:flex items-center gap-2 shrink-0 ml-2">
                        <CategoryMegaMenu />
                        <Link
                            href="/flash-sale"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-600 hover:text-red-700 transition-colors"
                        >
                            <span class="grid place-items-center w-5 h-5 rounded bg-red-600">
                                <BoltIcon class="w-3 h-3 text-white" />
                            </span>
                            Flash Sale
                        </Link>
                    </div>

                    <!-- Center: Search Bar -->
                    <div class="hidden md:flex flex-1 max-w-2xl ml-4 lg:ml-8 mr-auto">
                        <form @submit.prevent="submitSearch" class="relative w-full flex items-center bg-[#F3F9FB] rounded-md overflow-hidden">
                            <span class="pl-4 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                            </span>
                            <input v-model="searchQuery" type="text" placeholder="Search essentials, groceries and more..." class="w-full border-0 bg-transparent py-2.5 px-3 focus:ring-0 text-sm text-gray-700 placeholder-gray-400" />
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 text-sm font-semibold transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                Search
                            </button>
                        </form>
                    </div>

                    <!-- Right: Auth & Cart -->
                    <div class="flex items-center gap-2 md:gap-6 shrink-0">
                        
                        <!-- Cart (Mobile: navigate to /cart, Desktop: open drawer) -->
                        <Link :href="route('cart.index')" class="md:hidden relative flex items-center gap-1.5 text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                            <span v-if="cartCount > 0" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">{{ cartCount > 9 ? '9+' : cartCount }}</span>
                        </Link>
                        <button @click="showCartDrawer = true" class="hidden md:flex relative items-center gap-1.5 text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                            <span>Cart</span>
                            <span v-if="cartCount > 0" class="absolute -top-1.5 -right-2.5 bg-red-500 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">{{ cartCount > 9 ? '9+' : cartCount }}</span>
                        </button>
                            
                        <div class="w-px h-5 bg-gray-300 hidden md:block"></div>

                        <!-- Auth / Profile -->
                        <div v-if="$page.props.auth.user" class="relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                        <span class="hidden md:inline">{{ $page.props.auth.user.name.split(' ')[0] }}</span>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="py-1">
                                        <!-- Perbaikan Dropdown Admin -->
                                        <div v-if="$page.props.auth.user.role === 'admin'">
                                            <DropdownLink :href="route('admin.dashboard')" class="font-bold text-blue-600">
                                                Dashboard Admin
                                            </DropdownLink>
                                            <div class="border-t border-gray-100 my-1"></div>
                                        </div>
                                        
                                        <DropdownLink :href="route('profile.edit')">
                                            Pengaturan Profil
                                        </DropdownLink>
                                        <DropdownLink :href="route('orders.index')">
                                            Pesanan Saya
                                        </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Keluar
                                        </DropdownLink>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                        <div v-else>
                            <Link :href="route('login')" class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                <span class="hidden md:inline">Sign Up/Sign In</span>
                            </Link>
                        </div>
                    </div>

                </div>

                <!-- Mobile Search Bar (Visible only on small screens) -->
                <div class="mt-3 md:hidden">
                    <form @submit.prevent="submitSearch" class="relative w-full flex items-center bg-[#F3F9FB] rounded-md overflow-hidden">
                        <span class="pl-3 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                        </span>
                        <input v-model="searchQuery" type="text" placeholder="Search..." class="w-full border-0 bg-transparent py-2 px-2 focus:ring-0 text-sm text-gray-700" />
                    </form>
                </div>
            </div>
        </nav>

        <!-- Mobile Menu (Toggleable) -->
        <div v-if="showingMobileMenu" class="md:hidden bg-white border-b border-gray-200 px-4 py-2 max-h-[70vh] overflow-y-auto shadow-inner">
            <Link
                href="/flash-sale"
                class="flex items-center gap-2 py-2.5 border-b border-gray-50 font-semibold text-sm text-red-600"
                @click="showingMobileMenu = false"
            >
                <span class="grid place-items-center w-5 h-5 rounded bg-red-600">
                    <BoltIcon class="w-3 h-3 text-white" />
                </span>
                Flash Sale
            </Link>
            <template v-for="parent in page.props.globalCategories" :key="parent.id">
                <div class="py-2 border-b border-gray-50 last:border-0">
                    <div class="font-semibold text-sm text-gray-800">{{ parent.name }}</div>
                    <div v-if="parent.children && parent.children.length > 0" class="mt-2 pl-3 border-l-2 border-blue-100 space-y-1">
                        <Link v-for="child in parent.children" :key="child.id" :href="`/search?categories[]=${child.id}`" class="block text-sm text-gray-600 hover:text-blue-600 py-1.5">
                            {{ child.name }}
                        </Link>
                    </div>
                </div>
            </template>
        </div>

        <!-- Page Content -->
        <main class="min-h-screen">
            <slot />
        </main>

        <!-- Solid Blue Footer -->
        <footer class="bg-[#008ECC] text-white pt-12 pb-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                    
                    <div>
                        <h2 class="text-2xl font-bold mb-6">MegaMart</h2>
                        <div class="space-y-4 text-sm text-blue-100">
                            <p class="flex flex-col">
                                <span class="font-semibold text-white">Hubungi Kami</span>
                                <span>WhatsApp: (segera hadir)</span>
                                <span>Email: support@megamart.test</span>
                            </p>
                            <div class="pt-4">
                                <p class="font-semibold text-white mb-2">Download App</p>
                                <div class="flex gap-2">
                                    <div class="bg-black rounded px-3 py-1 flex items-center gap-2 cursor-pointer border border-gray-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-white"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.09 2.31-.9 3.83-.92 1.63-.03 2.87.69 3.65 1.77-3.1 1.83-2.58 6.07.41 7.21-.69 1.65-1.57 3.09-2.97 4.11zm-5.11-13.6c-.1-2.04 1.54-3.8 3.51-4 0 0 .11 2.24-1.63 3.9-1.59 1.54-3.57 1.58-3.57 1.58a3.11 3.11 0 0 0 1.69-1.48z"/></svg>
                                        <div class="flex flex-col">
                                            <span class="text-[8px] text-gray-300 leading-none">Download on the</span>
                                            <span class="text-xs font-bold leading-tight">App Store</span>
                                        </div>
                                    </div>
                                    <div class="bg-black rounded px-3 py-1 flex items-center gap-2 cursor-pointer border border-gray-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-white"><path d="M3.6 2.3c-.3.2-.5.6-.5 1.1v17.2c0 .5.2.9.5 1.1l9.2-9.7L3.6 2.3zm10.3 8.4l2.5-2.6-9.8-5.6 7.3 8.2zm0 2.6l-7.3 8.2 9.8-5.6-2.5-2.6zm5.6-3.4l-2.2-1.3-2.7 2.8 2.7 2.8 2.2-1.3c.8-.5.8-1.7 0-2.2z"/></svg>
                                        <div class="flex flex-col">
                                            <span class="text-[8px] text-gray-300 leading-none">GET IT ON</span>
                                            <span class="text-xs font-bold leading-tight">Google Play</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-bold border-b border-blue-400 pb-2 mb-4">Kategori Populer</h3>
                        <ul class="space-y-2 text-sm text-blue-100">
                            <li v-for="parent in (page.props.globalCategories || []).slice(0, 8)" :key="parent.id">
                                <Link :href="`/search?categories[]=${parent.id}`" class="hover:text-white transition-colors duration-150">
                                    &bull; {{ parent.name }}
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold border-b border-blue-400 pb-2 mb-4">Customer Services</h3>
                        <ul class="space-y-2 text-sm text-blue-100">
                            <li><a href="#" class="hover:text-white transition-colors">&bull; About Us</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Terms & Conditions</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; FAQ</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Privacy Policy</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; E-waste Policy</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Cancellation & Return Policy</a></li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-blue-400 pt-6 text-center text-xs text-blue-200">
                    <p>&copy; {{ new Date().getFullYear() }} MegaMart. All rights reserved.</p>
                </div>
            </div>
        </footer>

        <!-- Cart Drawer (Desktop) -->
        <CartDrawer :show="showCartDrawer" @close="showCartDrawer = false" @checkout="handleCheckout" />

        <!-- Shopping Method Drawer (Pre-Checkout) -->
        <ShoppingMethodDrawer :show="showShoppingMethodDrawer" @close="showShoppingMethodDrawer = false" @proceed="handleProceedCheckout" />

        <!-- Toast Notification -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div v-if="showToast" class="fixed bottom-24 md:bottom-6 left-1/2 -translate-x-1/2 z-[100] px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold flex items-center gap-2" :class="toastType === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
                <svg v-if="toastType === 'success'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" /></svg>
                {{ toastMessage }}
            </div>
        </Transition>
    </div>
</template>
<style>
/* Hide scrollbar for category pills but allow scrolling */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
