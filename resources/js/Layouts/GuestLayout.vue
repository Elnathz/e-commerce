<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-[#f8fafc] dark:bg-gray-950 p-4 relative overflow-hidden">
        <!-- Abstract Background Elements -->
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-400/10 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-400/10 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="w-full max-w-md relative z-10">
            <!-- Logo Section -->
            <div class="flex flex-col items-center mb-8">
                <Link href="/" class="group">
                    <div class="w-16 h-16 bg-white dark:bg-gray-900 rounded-2xl shadow-xl shadow-blue-500/10 flex items-center justify-center transform group-hover:scale-110 transition-transform duration-300">
                        <svg viewBox="0 0 24 24" class="w-10 h-10 text-blue-600 fill-current" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L1 21h22L12 2zm0 3.45l8.27 14.3H3.73L12 5.45z"/>
                            <path d="M11 16h2v2h-2zm0-7h2v5h-2z"/>
                        </svg>
                    </div>
                </Link>
                <h2 class="mt-6 text-2xl font-black text-gray-900 dark:text-white tracking-tight">MegaMart</h2>
            </div>

            <!-- Card Content -->
            <div class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border border-white dark:border-gray-800 rounded-[2.5rem] p-8 md:p-10 shadow-2xl shadow-gray-200/50 dark:shadow-none">
                <slot />
            </div>

            <!-- Footer Links -->
            <div class="mt-8 flex justify-center gap-6 text-sm font-medium text-gray-500 dark:text-gray-400">
                <Link href="/" class="hover:text-blue-600 transition-colors">Beranda</Link>
                <span>•</span>
                <Link href="/search" class="hover:text-blue-600 transition-colors">Produk</Link>
                <span>•</span>
                <a href="#" class="hover:text-blue-600 transition-colors">Bantuan</a>
            </div>
        </div>
    </div>
</template>

<style>
body {
    font-family: 'Outfit', 'Inter', sans-serif;
}
</style>
