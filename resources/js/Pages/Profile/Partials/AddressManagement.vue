<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AddressFormModal from '@/Components/Profile/AddressFormModal.vue';

defineProps({
    addresses: {
        type: Array,
        default: () => [],
    },
});

const showFormModal = ref(false);
const editingAddress = ref(null);
const deletingAddress = ref(null);
const isDeleting = ref(false);
const isSettingDefault = ref(null);

const openCreate = () => {
    editingAddress.value = null;
    showFormModal.value = true;
};

const openEdit = (address) => {
    editingAddress.value = address;
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
};

const confirmDelete = (address) => {
    deletingAddress.value = address;
};

const cancelDelete = () => {
    if (isDeleting.value) return;
    deletingAddress.value = null;
};

const destroyAddress = () => {
    if (!deletingAddress.value) return;

    isDeleting.value = true;
    router.delete(route('addresses.destroy', deletingAddress.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            deletingAddress.value = null;
        },
    });
};

const setDefault = (address) => {
    isSettingDefault.value = address.id;
    router.patch(route('addresses.setDefault', address.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSettingDefault.value = null;
        },
    });
};

const fullAddress = (address) => {
    const parts = [address.address_detail, address.district, address.city, address.province];
    const line = parts.filter(Boolean).join(', ');
    return address.postal_code ? `${line} ${address.postal_code}` : line;
};
</script>

<template>
    <section>
        <header class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">
                    Alamat Tersimpan
                </h2>
                <p class="mt-1 text-sm font-medium text-slate-500">
                    Kelola alamat pengiriman Anda untuk mempercepat proses checkout.
                </p>
            </div>

            <button type="button" @click="openCreate"
                class="inline-flex items-center gap-1.5 shrink-0 rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-blue-700 focus:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 active:bg-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Alamat
            </button>
        </header>

        <div v-if="addresses.length === 0" class="mt-6 flex flex-col items-center justify-center text-center py-10 rounded-xl border-2 border-dashed border-slate-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-slate-300">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
            <p class="mt-3 text-sm font-semibold text-slate-600">Belum ada alamat tersimpan</p>
            <p class="mt-1 text-xs text-slate-400 max-w-xs">Tambahkan alamat untuk memudahkan pengisian data saat checkout.</p>
        </div>

        <ul v-else class="mt-6 space-y-3">
            <li v-for="address in addresses" :key="address.id"
                class="rounded-xl border border-slate-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-slate-100 text-slate-600">
                                {{ address.label }}
                            </span>
                            <span v-if="address.is_default" class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                Utama
                            </span>
                        </div>
                        <p class="mt-1.5 text-sm font-bold text-slate-900">
                            {{ address.recipient_name }}
                            <span class="font-medium text-slate-400">&middot;</span>
                            <span class="font-medium text-slate-500">{{ address.phone }}</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-500 max-w-md">
                            {{ fullAddress(address) }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:flex-col sm:items-stretch sm:gap-2 shrink-0">
                    <button v-if="!address.is_default" type="button" @click="setDefault(address)" :disabled="isSettingDefault === address.id"
                        class="inline-flex items-center justify-center gap-1.5 rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors disabled:opacity-50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Jadikan Utama
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="openEdit(address)"
                            class="inline-flex items-center justify-center gap-1.5 rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Edit
                        </button>
                        <button type="button" @click="confirmDelete(address)"
                            class="inline-flex items-center justify-center gap-1.5 rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Hapus
                        </button>
                    </div>
                </div>
            </li>
        </ul>

        <AddressFormModal :show="showFormModal" :address="editingAddress" @close="closeFormModal" />

        <Modal :show="!!deletingAddress" max-width="md" @close="cancelDelete">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900">
                    Hapus alamat ini?
                </h2>
                <p class="mt-1 text-sm font-medium text-slate-500">
                    Alamat <span class="font-semibold text-slate-700">{{ deletingAddress?.label }}</span> milik
                    <span class="font-semibold text-slate-700">{{ deletingAddress?.recipient_name }}</span> akan dihapus secara permanen dan tidak dapat dikembalikan.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="cancelDelete" :disabled="isDeleting">
                        Batal
                    </SecondaryButton>
                    <DangerButton type="button" @click="destroyAddress" :disabled="isDeleting" :class="{ 'opacity-50': isDeleting }">
                        {{ isDeleting ? 'Menghapus...' : 'Hapus Alamat' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
