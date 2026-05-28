<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Daftar" />

        <div class="mb-8">
            <h1 class="text-3xl font-black text-gray-900 dark:text-white mb-2">Buat Akun</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Bergabunglah dengan MegaMart dan mulai belanja!</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-2">
                <InputLabel for="name" value="Nama Lengkap" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 ml-1" />

                <TextInput
                    id="name"
                    type="text"
                    class="block w-full h-14 px-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none ring-1 ring-gray-200 dark:ring-gray-700 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 dark:text-white"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Contoh: John Doe"
                />

                <InputError :message="form.errors.name" class="ml-1" />
            </div>

            <div class="space-y-2">
                <InputLabel for="email" value="Alamat Email" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 ml-1" />

                <TextInput
                    id="email"
                    type="email"
                    class="block w-full h-14 px-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none ring-1 ring-gray-200 dark:ring-gray-700 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 dark:text-white"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="nama@email.com"
                />

                <InputError :message="form.errors.email" class="ml-1" />
            </div>

            <div class="space-y-2">
                <InputLabel for="password" value="Kata Sandi" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 ml-1" />

                <TextInput
                    id="password"
                    type="password"
                    class="block w-full h-14 px-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none ring-1 ring-gray-200 dark:ring-gray-700 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 dark:text-white"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                />

                <InputError :message="form.errors.password" class="ml-1" />
            </div>

            <div class="space-y-2">
                <InputLabel
                    for="password_confirmation"
                    value="Konfirmasi Kata Sandi"
                    class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 ml-1"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="block w-full h-14 px-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none ring-1 ring-gray-200 dark:ring-gray-700 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 dark:text-white"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi kata sandi"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="ml-1"
                />
            </div>

            <PrimaryButton
                class="w-full !h-14 !bg-blue-600 hover:!bg-blue-700 !text-white !rounded-2xl flex items-center justify-center !text-base !font-black !shadow-xl !shadow-blue-500/20 transform active:scale-95 transition-all mt-4"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                Daftar Sekarang
            </PrimaryButton>

            <div class="text-center mt-8">
                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                    Sudah punya akun? 
                    <Link :href="route('login')" class="text-blue-600 hover:text-blue-700 font-black">
                        Masuk di sini
                    </Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
