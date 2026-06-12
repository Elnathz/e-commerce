<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import RecentActivity from './Partials/RecentActivity.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    recentActivity: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Profil Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-slate-800">Pengaturan Profil</h1>
        </template>

        <div class="max-w-3xl space-y-6">
            <div class="bg-white p-6 shadow-sm rounded-xl border border-slate-200">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="bg-white p-6 shadow-sm rounded-xl border border-slate-200">
                <RecentActivity :activities="recentActivity" />
            </div>

            <div class="bg-white p-6 shadow-sm rounded-xl border border-slate-200">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="bg-white p-6 shadow-sm rounded-xl border border-slate-200">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AdminLayout>
</template>
