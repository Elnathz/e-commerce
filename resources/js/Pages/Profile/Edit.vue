<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import AddressManagement from './Partials/AddressManagement.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    addresses: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Profil" />

    <StorefrontLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-slate-900">
                Pengaturan Profil
            </h2>
        </template>

        <div class="py-12 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-4xl space-y-8 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:rounded-2xl sm:p-10">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:rounded-2xl sm:p-10">
                    <AddressManagement :addresses="addresses" />
                </div>

                <div class="bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:rounded-2xl sm:p-10">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:rounded-2xl sm:p-10">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
