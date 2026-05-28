<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close', 'proceed']);

const proceedToCheckout = (method) => {
    emit('proceed', method);
    emit('close');
    
    // Fallback if the parent doesn't handle the routing
    // we just use the router to go to checkout directly.
    // Actually we'll let parent handle it to inject selected items, 
    // but if not, we can just do this:
    // router.get(route('checkout.index', { method: method }));
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
        enter-from-class="translate-y-full md:translate-y-0 md:translate-x-full"
        enter-to-class="translate-y-0 md:translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-y-0 md:translate-x-0"
        leave-to-class="translate-y-full md:translate-y-0 md:translate-x-full"
    >
        <div v-if="show" class="fixed bottom-0 md:top-0 right-0 h-auto md:h-full w-full max-w-md bg-white shadow-2xl z-[90] flex flex-col rounded-t-2xl md:rounded-none">
            
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.65h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .415.336.75.75.75Z" />
                    </svg>
                    Pilih Metode Belanja
                </h2>
                <button @click="emit('close')" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto px-5 py-6">
                <p class="text-sm text-gray-500 mb-6">Bagaimana Anda ingin pesanan ini diproses?</p>
                
                <div class="space-y-4">
                    <!-- Delivery Option -->
                    <button @click="proceedToCheckout('delivery')" class="w-full text-left group flex items-start gap-4 p-4 rounded-xl border-2 border-gray-100 hover:border-blue-600 hover:bg-blue-50 transition-all">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-blue-600 group-hover:text-white transition-colors">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-1 group-hover:text-blue-700">Dikirim (Delivery)</h3>
                            <p class="text-sm text-gray-500">Barang dikirim ke alamat Anda melalui kurir rekanan atau kurir MegaMart.</p>
                        </div>
                    </button>

                    <!-- Pickup Option -->
                    <button @click="proceedToCheckout('pickup')" class="w-full text-left group flex items-start gap-4 p-4 rounded-xl border-2 border-gray-100 hover:border-green-600 hover:bg-green-50 transition-all">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0 group-hover:bg-green-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-green-600 group-hover:text-white transition-colors">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.65h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .415.336.75.75.75Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-1 group-hover:text-green-700">Ambil di Toko (Pickup)</h3>
                            <p class="text-sm text-gray-500">Anda datang langsung ke toko untuk mengambil barang. Tidak perlu antri.</p>
                        </div>
                    </button>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 md:bg-white pb-8 md:pb-4">
                 <button @click="emit('close')" class="w-full py-3 rounded-xl text-sm font-bold border-2 border-gray-200 text-gray-600 hover:bg-gray-100 transition-colors">
                    Batal
                </button>
            </div>
        </div>
    </Transition>
</template>
