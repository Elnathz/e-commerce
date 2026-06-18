<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const open = ref(false);
const root = ref(null);

const close = (e) => { if (root.value && !root.value.contains(e.target)) open.value = false; };
const onKey = (e) => { if (e.key === 'Escape') open.value = false; };

onMounted(() => { document.addEventListener('click', close); document.addEventListener('keydown', onKey); });
onBeforeUnmount(() => { document.removeEventListener('click', close); document.removeEventListener('keydown', onKey); });
</script>

<template>
    <div ref="root" class="relative">
        <button @click.stop="open = !open" :aria-expanded="open" aria-haspopup="true"
                class="flex items-center gap-1.5 bg-[#F3F9FB] text-gray-700 px-3.5 py-2 rounded-full text-sm font-medium whitespace-nowrap hover:bg-blue-50 hover:text-blue-600 transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            Kategori
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition ease-in duration-100" leave-to-class="opacity-0 -translate-y-1">
            <div v-if="open"
                 class="absolute top-full left-0 mt-2 w-[640px] max-w-[90vw] bg-white border border-gray-100 rounded-2xl shadow-xl z-50 p-5 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4">
                <div v-for="parent in (page.props.globalCategories || [])" :key="parent.id">
                    <Link :href="`/search?categories[]=${parent.id}`" @click="open = false"
                          class="block text-sm font-semibold text-gray-800 hover:text-blue-600 mb-1.5 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        {{ parent.name }}
                    </Link>
                    <div v-if="parent.children && parent.children.length" class="space-y-1">
                        <Link v-for="child in parent.children" :key="child.id"
                              :href="`/search?categories[]=${child.id}`" @click="open = false"
                              class="block text-xs text-gray-500 hover:text-blue-600 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                            {{ child.name }}
                        </Link>
                    </div>
                </div>
                <div v-if="!(page.props.globalCategories || []).length" class="col-span-full text-sm text-gray-400">
                    Belum ada kategori.
                </div>
            </div>
        </Transition>
    </div>
</template>
