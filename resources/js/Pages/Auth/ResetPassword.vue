<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Kata Sandi" />

        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-900 mb-2">Reset Sandi</h1>
            <p class="text-slate-500 font-medium">Silakan masukkan kata sandi baru Anda.</p>
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
                />

                <InputError :message="form.errors.email" class="ml-1" />
            </div>

            <div class="space-y-2">
                <InputLabel for="password" value="Kata Sandi Baru" class="text-xs font-bold uppercase tracking-wider text-slate-500 ml-1" />

                <TextInput
                    id="password"
                    type="password"
                    class="block w-full h-14 px-5 rounded-2xl bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-slate-900"
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
                    class="text-xs font-bold uppercase tracking-wider text-slate-500 ml-1"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="block w-full h-14 px-5 rounded-2xl bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-600 transition-all font-medium text-slate-900"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi kata sandi baru"
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
                Simpan Kata Sandi
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
