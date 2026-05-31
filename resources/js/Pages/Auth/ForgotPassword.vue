<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Lupa Kata Sandi" />

        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-900 mb-2">Lupa Kata Sandi?</h1>
            <p class="text-slate-500 font-medium">Masukkan email Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi.</p>
        </div>

        <div v-if="status" class="mb-6 p-4 rounded-2xl bg-green-50 text-sm font-semibold text-green-600 border border-green-100">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-2">
                <InputLabel for="email" value="Email" class="text-xs font-bold uppercase tracking-wider text-slate-500 ml-1" />

                <TextInput
                    id="email"
                    type="email"
                    class="block w-full h-14 px-5 rounded-2xl bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-slate-900"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="nama@email.com"
                />

                <InputError :message="form.errors.email" class="ml-1" />
            </div>

            <PrimaryButton
                class="w-full !h-14 !bg-blue-600 hover:!bg-blue-700 !text-white !rounded-2xl flex items-center justify-center !text-base !font-black !shadow-xl !shadow-blue-500/20 transform active:scale-95 transition-all"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                Kirim Tautan Reset
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
