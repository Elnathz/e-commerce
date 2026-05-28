<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <div class="mb-8">
            <h1 class="text-3xl font-black text-gray-900 dark:text-white mb-2">Selamat Datang!</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Silakan masuk ke akun Anda untuk melanjutkan belanja.</p>
        </div>

        <div v-if="status" class="mb-6 p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 text-sm font-semibold text-green-600 dark:text-green-400 border border-green-100 dark:border-green-800">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-2">
                <InputLabel for="email" value="Email" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 ml-1" />

                <TextInput
                    id="email"
                    type="email"
                    class="block w-full h-14 px-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none ring-1 ring-gray-200 dark:ring-gray-700 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 dark:text-white"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="nama@email.com"
                />

                <InputError :message="form.errors.email" class="ml-1" />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between ml-1">
                    <InputLabel for="password" value="Kata Sandi" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors"
                    >
                        Lupa Sandi?
                    </Link>
                </div>

                <TextInput
                    id="password"
                    type="password"
                    class="block w-full h-14 px-5 rounded-2xl bg-gray-50 dark:bg-gray-800 border-none ring-1 ring-gray-200 dark:ring-gray-700 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-gray-900 dark:text-white"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />

                <InputError :message="form.errors.password" class="ml-1" />
            </div>

            <div class="flex items-center">
                <label class="flex items-center group cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" class="w-5 h-5 rounded-lg border-gray-300 text-blue-600 focus:ring-blue-600" />
                    <span class="ms-3 text-sm font-semibold text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors"
                        >Ingat saya</span
                    >
                </label>
            </div>

            <PrimaryButton
                class="w-full !h-14 !bg-blue-600 hover:!bg-blue-700 !text-white !rounded-2xl flex items-center justify-center !text-base !font-black !shadow-xl !shadow-blue-500/20 transform active:scale-95 transition-all"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                Masuk Sekarang
            </PrimaryButton>

            <div class="text-center mt-8">
                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                    Belum punya akun? 
                    <Link :href="route('register')" class="text-blue-600 hover:text-blue-700 font-black">
                        Daftar Gratis
                    </Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
