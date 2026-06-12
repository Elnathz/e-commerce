<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const sidebarOpen = ref(false);

const currentRoute = computed(() => route().current());

const isActive = (routeName) => {
    if (routeName === 'admin.dashboard') return currentRoute.value === 'admin.dashboard';
    return currentRoute.value?.startsWith(routeName.replace('.*', '')) || currentRoute.value?.startsWith(routeName);
};

const menuGroups = [
    {
        title: 'Operasional',
        items: [
            { label: 'Dashboard', route: 'admin.dashboard', match: 'admin.dashboard', icon: 'dashboard' },
            { label: 'Pesanan', route: 'admin.orders.index', match: 'admin.orders', icon: 'order' },
            { label: 'Retur / Komplain', route: 'admin.returns.index', match: 'admin.returns', icon: 'return' },
        ]
    },
    {
        title: 'Katalog',
        items: [
            { label: 'Produk', route: 'admin.products.index', match: 'admin.products', icon: 'box' },
            { label: 'Kategori', route: 'admin.categories.index', match: 'admin.categories', icon: 'folder' },
        ]
    },
    {
        title: 'Marketing',
        items: [
            { label: 'Voucher Promosi', route: 'admin.promotions.index', match: 'admin.promotions', icon: 'tag' },
        ]
    },
    {
        title: 'Insight',
        items: [
            { label: 'Ulasan Produk', route: 'admin.reviews.index', match: 'admin.reviews', icon: 'star' },
        ]
    }
];

const bottomItems = [
    { label: 'Lihat Toko', href: '/', icon: 'external', external: true },
    { label: 'Riwayat Export', route: 'admin.exports.index', match: 'admin.exports', icon: 'export' },
    { label: 'Profil', route: 'admin.profile.edit', match: 'admin.profile', icon: 'user' },
];

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile Sidebar Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false"></div>
        </Transition>

        <!-- Sidebar -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-[260px] bg-white border-r border-slate-200 flex flex-col transition-transform duration-300 lg:translate-x-0"
        >
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 h-16 border-b border-slate-200 shrink-0">
                <img src="/images/logo/gemini-svg.svg?v=4" alt="MegaMart Admin" class="h-10 w-auto object-contain " />
                <span class="ml-auto text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">Admin</span>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <template v-for="(group, groupIndex) in menuGroups" :key="group.title">
                    <p :class="groupIndex > 0 ? 'mt-4' : ''" class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">{{ group.title }}</p>
                    
                    <template v-for="item in group.items" :key="item.label">
                        <Link
                            :href="route(item.route)"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                            :class="isActive(item.match)
                                ? 'bg-indigo-50 text-indigo-700 shadow-sm'
                                : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'"
                            @click="sidebarOpen = false"
                        >
                            <!-- Dashboard Icon -->
                            <svg v-if="item.icon === 'dashboard'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                            <!-- Folder Icon -->
                            <svg v-else-if="item.icon === 'folder'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                            <!-- Box Icon -->
                            <svg v-else-if="item.icon === 'box'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            <!-- Order Icon -->
                            <svg v-else-if="item.icon === 'order'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <!-- Return Icon -->
                            <svg v-else-if="item.icon === 'return'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            <!-- Star Icon -->
                            <svg v-else-if="item.icon === 'star'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                            <!-- Tag Icon -->
                            <svg v-else-if="item.icon === 'tag'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                            </svg>
                            {{ item.label }}
                        </Link>
                    </template>
                </template>

                <div class="my-4 border-t border-slate-200"></div>
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-2">Lainnya</p>

                <template v-for="item in bottomItems" :key="item.label">
                    <a
                        v-if="item.external"
                        :href="item.href"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-500 hover:text-slate-900 hover:bg-slate-50 transition-all duration-200"
                        @click="sidebarOpen = false"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                        {{ item.label }}
                    </a>
                    <Link
                        v-else
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                        :class="isActive(item.match)
                            ? 'bg-blue-50 text-blue-700 shadow-sm'
                            : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'"
                        @click="sidebarOpen = false"
                    >
                        <svg v-if="item.icon === 'user'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <svg v-else-if="item.icon === 'export'" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ item.label }}
                    </Link>
                </template>
            </nav>

            <!-- User Info at Bottom -->
            <div class="p-3 border-t border-slate-200 shrink-0">
                <div class="flex items-center gap-3 px-3 py-3 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-900 truncate">{{ $page.props.auth.user.name }}</p>
                        <p class="text-xs font-medium text-slate-500 truncate">{{ $page.props.auth.user.email }}</p>
                    </div>
                    <button @click="logout" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors shrink-0" title="Keluar">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="lg:pl-[260px] min-h-screen flex flex-col">
            <!-- Top Header -->
            <header class="sticky top-0 z-30 h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8">
                <!-- Mobile hamburger -->
                <button @click="sidebarOpen = true" class="lg:hidden p-2 -ml-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Page Title -->
                <div class="hidden lg:block">
                    <slot name="header">
                        <h1 class="text-lg font-semibold text-slate-800">Dashboard</h1>
                    </slot>
                </div>

                <!-- Mobile title -->
                <div class="lg:hidden flex-1 text-center">
                    <slot name="header">
                        <h1 class="text-base font-semibold text-slate-800">Dashboard</h1>
                    </slot>
                </div>

                <!-- Right side -->
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-slate-700 hidden md:block">{{ $page.props.auth.user.name }}</span>
                    <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold">
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
