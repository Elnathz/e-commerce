<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-50 p-4">
        <div class="w-full max-w-md">
            <!-- Logo Section -->
            <div class="flex flex-col items-center mb-511">
                <Link href="/" class="group">
                    <img src="/images/logo/gemini-svg.svg?v=4" alt="MegaMart Logo" class="w-80 h-auto object-contain mx-auto transform group-hover:scale-105 transition-transform duration-300 drop-shadow-sm" />
                </Link>
            </div>

            <!-- Card Content -->
            <div class="bg-white rounded-2xl p-8 md:p-10 shadow-xl shadow-slate-200/60 border border-slate-100">
                <slot />
            </div>

            <!-- Footer Links -->
            <div class="mt-8 flex justify-center gap-6 text-sm font-medium text-slate-400">
                <Link href="/" class="hover:text-blue-600 transition-colors">Beranda</Link>
                <span class="text-slate-300">•</span>
                <Link href="/search" class="hover:text-blue-600 transition-colors">Produk</Link>
                <span class="text-slate-300">•</span>
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
