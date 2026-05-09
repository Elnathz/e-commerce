<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';

const showingMobileMenu = ref(false);
const page = usePage();
const searchQuery = ref(page.props.filters?.q || '');

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
</script>

<template>
    <div class="min-h-screen bg-white text-gray-900 font-sans">
        
        <!-- Top Bar (Light Gray) -->
        <div class="bg-gray-100 text-gray-500 text-xs py-2 hidden md:block">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <div>Welcome to MegaMart - Best Deals Online!</div>
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                        Deliver to: <strong>Jakarta Pusat</strong>
                    </span>
                    <span class="flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                        Track your order
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Navbar -->
        <nav class="bg-white border-b border-gray-100 py-4 sticky top-0 z-50 shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center gap-4">
                    
                    <!-- Left: Logo -->
                    <div class="flex items-center shrink-0">
                        <!-- Hamburger for mobile -->
                        <button @click="showingMobileMenu = !showingMobileMenu" class="md:hidden mr-2 text-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 6h18M3 18h18" /></svg>
                        </button>
                        <Link href="/" class="flex items-center gap-2">
                            <img src="/images/logo/gemini-svg.svg" alt="MegaMart" class="h-16 w-auto text-blue-600" />
                        </Link>
                    </div>

                    <!-- Center: Search Bar -->
                    <div class="hidden md:flex flex-1 max-w-2xl ml-4 lg:ml-8 mr-auto">
                        <form @submit.prevent="submitSearch" class="relative w-full flex items-center bg-[#F3F9FB] rounded-md overflow-hidden">
                            <span class="pl-4 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                            </span>
                            <input v-model="searchQuery" type="text" placeholder="Search essentials, groceries and more..." class="w-full border-0 bg-transparent py-2.5 px-3 focus:ring-0 text-sm text-gray-700 placeholder-gray-400" />
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 text-sm font-semibold transition-colors">
                                Search
                            </button>
                        </form>
                    </div>

                    <!-- Right: Auth & Cart -->
                    <div class="flex items-center gap-2 md:gap-6 shrink-0">
                        
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
                                            Profil Saya
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

                        <div class="w-px h-5 bg-gray-300 hidden md:block"></div>

                        <!-- Cart -->
                        <Link href="/" class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                            <span class="hidden md:inline">Cart</span>
                        </Link>
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

        <!-- Sub Navbar (Categories) -->
        <div class="bg-white border-b border-gray-100 hidden md:block">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-6 py-3 overflow-x-auto no-scrollbar">
                    
                    <button class="flex items-center gap-1 bg-blue-500 text-white px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-600 transition-colors">
                        Groceries 
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    
                    <button class="flex items-center gap-1 bg-[#F3F9FB] text-gray-700 px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        Premium Fruits 
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <button class="flex items-center gap-1 bg-[#F3F9FB] text-gray-700 px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        Home & Kitchen
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <button class="flex items-center gap-1 bg-[#F3F9FB] text-gray-700 px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        Fashion
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <button class="flex items-center gap-1 bg-[#F3F9FB] text-gray-700 px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        Electronics
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <button class="flex items-center gap-1 bg-[#F3F9FB] text-gray-700 px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        Beauty
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    
                    <button class="flex items-center gap-1 bg-[#F3F9FB] text-gray-700 px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        Sports, Toys & Luggage
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                </div>
            </div>
        </div>

        <!-- Mobile Menu (Toggleable) -->
        <div v-if="showingMobileMenu" class="md:hidden bg-white border-b border-gray-200 px-4 py-2 space-y-2">
            <Link href="/" class="block text-sm text-gray-700 py-2">Groceries</Link>
            <Link href="/" class="block text-sm text-gray-700 py-2">Premium Fruits</Link>
            <Link href="/" class="block text-sm text-gray-700 py-2">Home & Kitchen</Link>
            <Link href="/" class="block text-sm text-gray-700 py-2">Fashion</Link>
            <Link href="/" class="block text-sm text-gray-700 py-2">Electronics</Link>
        </div>

        <!-- Page Content -->
        <main class="min-h-screen">
            <slot />
        </main>

        <!-- Solid Blue Footer -->
        <footer class="bg-[#008ECC] text-white pt-12 pb-6 mt-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                    
                    <div>
                        <h2 class="text-2xl font-bold mb-6">MegaMart</h2>
                        <div class="space-y-4 text-sm text-blue-100">
                            <p class="flex flex-col">
                                <span class="font-semibold text-white">Contact Us</span>
                                <span>WhatsApp: +1 202-918-2132</span>
                                <span>Call Us: +1 202-918-2132</span>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-green-500"><path d="M3 20.5v-17c0-.83.67-1.5 1.5-1.5h15c.83 0 1.5.67 1.5 1.5v17c0 .83-.67 1.5-1.5 1.5h-15C3.67 22 3 21.33 3 20.5z" opacity="0"/><path d="M17.5 14.5l-5-2.5-5 2.5V5h10v9.5zM7.5 3h9C17.33 3 18 3.67 18 4.5v15c0 .83-.67 1.5-1.5 1.5h-9C6.67 21 6 20.33 6 19.5v-15C6 3.67 6.67 3 7.5 3zm2.5 5.5l2-1.5 2 1.5v-3h-4v3z"/></svg>
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
                        <h3 class="font-bold border-b border-blue-400 pb-2 mb-4">Most Popular Categories</h3>
                        <ul class="space-y-2 text-sm text-blue-100">
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Staples</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Beverages</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Personal Care</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Home Care</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Baby Care</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Vegetables & Fruits</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Snacks & Foods</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">&bull; Dairy & Bakery</a></li>
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
                    <p>&copy; 2026 All rights reserved. Reliance Retail Ltd.</p>
                </div>
            </div>
        </footer>
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
