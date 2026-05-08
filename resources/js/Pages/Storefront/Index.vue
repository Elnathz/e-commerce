<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    products: Array,
});

// Format currency
const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
};

// Helper to get the primary or first image
const getPrimaryImage = (product) => {
    if (!product.images || product.images.length === 0) return null;
    const primary = product.images.find(img => img.is_primary);
    return primary ? primary.image_path : product.images[0].image_path;
};

// Mock data based on the provided assets for UI demonstration
const mockSmartphones = [
    { id: 1, name: 'iPhone 17 Pro Max Blue', price: 25000000, old_price: 27000000, discount: '7%', save: 'Rp2.000.000', image: '/images/product/ipon17promaxblue.webp' },
    { id: 2, name: 'iPhone 17 Pro Max Orange', price: 25000000, old_price: 27000000, discount: '7%', save: 'Rp2.000.000', image: '/images/product/ipon17promaxorange.webp' },
    { id: 3, name: 'iPhone 17 Pro Max White', price: 25000000, old_price: 27000000, discount: '7%', save: 'Rp2.000.000', image: '/images/product/ipon17promaxwhite.webp' },
    { id: 4, name: 'iPhone 17 Black', price: 15000000, old_price: 17000000, discount: '11%', save: 'Rp2.000.000', image: '/images/product/ipon17black.webp' },
    { id: 5, name: 'iPhone 17 White', price: 15000000, old_price: 17000000, discount: '11%', save: 'Rp2.000.000', image: '/images/product/ipon17white.webp' },
];

const mockCategories = [
    { name: 'Mobile', image: '/images/category/ipon.png' },
    { name: 'Cosmetics', image: null },
    { name: 'Electronics', image: null },
    { name: 'Furniture', image: '/images/category/sofa.png' },
    { name: 'Watches', image: null },
    { name: 'Decor', image: null },
    { name: 'Accessories', image: null },
];
</script>

<template>
    <Head title="Home - MegaMart" />

    <StorefrontLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-12">
            
            <!-- Hero Banner -->
            <div class="w-full rounded-2xl overflow-hidden relative group">
                <!-- Gunakan banner asli yang telah disediakan -->
                <img src="/images/banner/banner.png" alt="Smart Wearable" class="w-full object-cover rounded-2xl shadow-sm" />
                <!-- Nav Arrows (Decorative) -->
                <button class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-blue-600 rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </button>
                <button class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-blue-600 rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </button>
            </div>

            <!-- Grab the best deal on Smartphones -->
            <section>
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800">
                        Grab the best deal on <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Smartphones</span>
                    </h2>
                    <Link href="#" class="text-sm font-medium text-gray-500 hover:text-blue-600 flex items-center gap-1 mb-1">
                        View All <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>

                <div class="flex overflow-x-auto gap-4 pb-4 no-scrollbar">
                    <Link 
                        v-for="phone in mockSmartphones" 
                        :key="phone.id"
                        href="#"
                        class="min-w-[200px] bg-white border border-gray-100 rounded-xl overflow-hidden hover:shadow-lg transition-all duration-300 relative group flex-shrink-0"
                    >
                        <!-- Discount Badge -->
                        <div class="absolute top-0 right-0 bg-[#008ECC] text-white text-[10px] font-bold px-2 py-3 rounded-bl-xl z-10 flex flex-col items-center leading-none">
                            <span>{{ phone.discount }}</span>
                            <span>OFF</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 flex items-center justify-center h-48 relative overflow-hidden rounded-t-xl">
                            <!-- Background Accent (Light Blue rounded shape like MegaMart) -->
                            <div class="absolute inset-0 bg-[#F3F9FB] m-2 rounded-xl"></div>
                            <img :src="phone.image" :alt="phone.name" class="h-full object-contain relative z-10 drop-shadow-md group-hover:scale-110 transition-transform duration-300" />
                        </div>
                        <div class="p-4 bg-white border-t-4 border-[#008ECC]/10 group-hover:border-[#008ECC] transition-colors duration-300 rounded-b-xl">
                            <h3 class="text-sm font-bold text-gray-800 truncate">{{ phone.name }}</h3>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="font-bold text-black">{{ formatPrice(phone.price) }}</span>
                                <span class="text-xs text-gray-400 line-through">{{ formatPrice(phone.old_price) }}</span>
                            </div>
                            <hr class="my-2 border-gray-100" />
                            <div class="text-xs font-semibold text-green-600">
                                Save - {{ phone.save }}
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Shop From Top Categories -->
            <section>
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800">
                        Shop From <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Top Categories</span>
                    </h2>
                    <Link href="#" class="text-sm font-medium text-gray-500 hover:text-blue-600 flex items-center gap-1 mb-1">
                        View All <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>

                <div class="flex flex-wrap md:flex-nowrap justify-center md:justify-between gap-4 md:gap-2 text-center pb-4">
                    <Link 
                        v-for="(cat, index) in mockCategories" 
                        :key="index"
                        href="#"
                        class="flex flex-col items-center gap-3 w-[100px] md:w-auto"
                    >
                        <div class="w-24 h-24 rounded-full bg-[#F3F9FB] flex items-center justify-center overflow-hidden border-2 border-transparent hover:border-blue-500 hover:shadow-md transition-all duration-300 p-4">
                            <img v-if="cat.image" :src="cat.image" :alt="cat.name" class="w-full h-full object-contain drop-shadow-sm" />
                            <span v-else class="text-gray-400 text-xs">No Image</span>
                        </div>
                        <span class="text-sm font-medium text-gray-700">{{ cat.name }}</span>
                    </Link>
                </div>
            </section>

            <!-- Top Electronics Brands -->
            <section>
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800">
                        Top <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Electronics Brands</span>
                    </h2>
                    <Link href="#" class="text-sm font-medium text-gray-500 hover:text-blue-600 flex items-center gap-1 mb-1">
                        View All <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Brand 1 (Mock Apple) -->
                    <div class="bg-gray-900 text-white rounded-2xl p-6 flex items-center justify-between shadow-sm relative overflow-hidden group">
                        <div class="z-10 relative">
                            <div class="bg-white/20 text-white text-xs font-semibold px-3 py-1 rounded-full w-fit mb-3">IPHONE</div>
                            <img src="/images/logo/gemini-svg.svg" class="h-10 mb-2 brightness-0 invert opacity-80" alt="Logo" />
                            <div class="text-lg font-light">UP to 80% OFF</div>
                        </div>
                        <img src="/images/product/ipon17promaxwhite.webp" class="h-28 z-10 drop-shadow-2xl group-hover:scale-110 transition-transform duration-300" alt="iPhone" />
                    </div>

                    <!-- Brand 2 (Mock Realme) -->
                    <div class="bg-[#FFF8E1] text-black rounded-2xl p-6 flex items-center justify-between shadow-sm relative overflow-hidden group">
                        <div class="z-10 relative">
                            <div class="bg-yellow-400 text-black text-xs font-bold px-3 py-1 rounded-full w-fit mb-3">realme</div>
                            <div class="text-2xl font-black text-yellow-500 mb-1">realme</div>
                            <div class="text-lg font-light">UP to 60% OFF</div>
                        </div>
                        <img src="/images/product/ipon17black.webp" class="h-28 z-10 drop-shadow-2xl group-hover:scale-110 transition-transform duration-300" alt="Phone" />
                    </div>

                    <!-- Brand 3 (Mock Xiaomi) -->
                    <div class="bg-[#FFF3E0] text-black rounded-2xl p-6 flex items-center justify-between shadow-sm relative overflow-hidden group">
                        <div class="z-10 relative">
                            <div class="bg-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full w-fit mb-3">XIAOMI</div>
                            <div class="text-2xl font-black text-orange-500 mb-1">mi</div>
                            <div class="text-lg font-light">UP to 80% OFF</div>
                        </div>
                        <img src="/images/product/ipon17promaxblue.webp" class="h-28 z-10 drop-shadow-2xl group-hover:scale-110 transition-transform duration-300" alt="Phone" />
                    </div>
                </div>
            </section>

            <!-- Daily Essentials (Actual DB Products Fallback) -->
            <section v-if="products && products.length > 0">
                <div class="flex justify-between items-end border-b pb-2 mb-6">
                    <h2 class="text-xl md:text-2xl font-semibold text-gray-800">
                        Daily <span class="text-blue-500 font-bold border-b-2 border-blue-500 pb-2 inline-block">Essentials</span>
                    </h2>
                    <Link href="#" class="text-sm font-medium text-gray-500 hover:text-blue-600 flex items-center gap-1 mb-1">
                        View All <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <Link 
                        v-for="product in products.slice(0, 6)" 
                        :key="product.id"
                        :href="route('products.show', product.slug)"
                        class="bg-[#F3F9FB] rounded-xl p-4 flex flex-col items-center text-center hover:shadow-md transition-shadow duration-300 group"
                    >
                        <div class="w-full aspect-square flex items-center justify-center mb-3">
                            <img 
                                v-if="getPrimaryImage(product)" 
                                :src="'/storage/' + getPrimaryImage(product)" 
                                :alt="product.name"
                                class="max-h-full object-contain drop-shadow-sm group-hover:scale-110 transition-transform duration-300"
                            />
                            <div v-else class="text-gray-400 text-xs uppercase">No Image</div>
                        </div>
                        <h3 class="text-sm text-gray-700 font-medium mb-1 line-clamp-1">{{ product.name }}</h3>
                        <p class="text-sm font-bold text-gray-900">UP to 50% OFF</p>
                    </Link>
                </div>
            </section>

        </div>
    </StorefrontLayout>
</template>
