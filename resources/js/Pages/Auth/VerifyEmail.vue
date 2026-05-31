<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="mb-8 text-center">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </div>
            <h1 class="text-3xl font-black text-slate-900 mb-2">Periksa Email Anda</h1>
            <p class="text-slate-500 font-medium">
                Terima kasih telah mendaftar! Sebelum memulai, mohon verifikasi alamat email Anda dengan mengeklik tautan yang baru saja kami kirimkan ke email Anda. Jika tidak menerima email tersebut, kami akan dengan senang hati mengirimkannya kembali.
            </p>
        </div>

        <div
            class="mb-6 p-4 rounded-2xl bg-green-50 text-sm font-semibold text-green-600 border border-green-100 text-center"
            v-if="verificationLinkSent"
        >
            Tautan verifikasi baru telah dikirimkan ke alamat email yang Anda berikan saat pendaftaran.
        </div>

        <form @submit.prevent="submit">
            <PrimaryButton
                class="w-full !h-14 !bg-blue-600 hover:!bg-blue-700 !text-white !rounded-2xl flex items-center justify-center !text-base !font-black !shadow-xl !shadow-blue-500/20 transform active:scale-95 transition-all mb-4"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                Kirim Ulang Email
            </PrimaryButton>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="w-full h-14 bg-white hover:bg-slate-50 text-slate-600 rounded-2xl flex items-center justify-center text-base font-bold transition-all border-none ring-1 ring-slate-200"
            >
                Keluar Akun
            </Link>
        </form>
    </GuestLayout>
</template>
