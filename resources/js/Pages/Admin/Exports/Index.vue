<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    jobs: Array,
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatBytes = (bytes) => {
    if (!bytes) return '-';
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex++;
    }
    return `${value.toFixed(1)} ${units[unitIndex]}`;
};

const statusBadge = (status) => {
    switch (status) {
        case 'completed':
            return { label: 'Selesai', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'processing':
            return { label: 'Diproses', class: 'bg-blue-50 text-blue-600 border-blue-200' };
        case 'pending':
            return { label: 'Menunggu', class: 'bg-amber-50 text-amber-600 border-amber-200' };
        case 'failed':
            return { label: 'Gagal', class: 'bg-red-50 text-red-600 border-red-200' };
        default:
            return { label: status, class: 'bg-slate-100 text-slate-600 border-slate-200' };
    }
};

const isExpired = (job) => !!job.expires_at && new Date(job.expires_at) < new Date();

const isDownloadable = (job) => job.status === 'completed' && !!job.file_path && !isExpired(job);

const filterSummary = (filters) => {
    if (!filters) return 'Semua data';

    const parts = [];
    if (filters.start_date) parts.push(`Dari ${filters.start_date}`);
    if (filters.end_date) parts.push(`Sampai ${filters.end_date}`);
    if (filters.status) parts.push(`Status: ${filters.status}`);

    return parts.length ? parts.join(' · ') : 'Semua data';
};
</script>

<template>
    <Head title="Riwayat Export" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.dashboard')" class="text-slate-400 hover:text-slate-700">
                    ← Kembali
                </Link>
                <h2 class="font-bold text-xl text-slate-900 leading-tight">Riwayat Export</h2>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <div v-if="jobs.length === 0" class="text-center py-12 text-sm text-slate-400">
                        Belum ada riwayat export.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Tanggal</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Filter</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Baris</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Ukuran</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wide">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="job in jobs" :key="job.id" class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-4 align-top text-sm text-slate-700">
                                        {{ formatDate(job.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm text-slate-500">
                                        {{ filterSummary(job.filters) }}
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border" :class="statusBadge(job.status).class">
                                            {{ statusBadge(job.status).label }}
                                        </span>
                                        <p v-if="job.status === 'failed' && job.error_message" class="text-xs text-red-500 mt-1 max-w-xs">
                                            {{ job.error_message }}
                                        </p>
                                        <p v-else-if="job.status === 'completed' && isExpired(job)" class="text-xs text-slate-400 mt-1">
                                            File sudah kadaluarsa
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm text-slate-700">
                                        {{ job.row_count ?? job.estimated_rows ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 align-top text-sm text-slate-700">
                                        {{ formatBytes(job.file_size_bytes) }}
                                    </td>
                                    <td class="px-6 py-4 align-top text-right text-sm font-medium whitespace-nowrap">
                                        <a
                                            v-if="isDownloadable(job)"
                                            :href="route('admin.exports.download', job.id)"
                                            class="text-blue-600 hover:text-blue-800 font-semibold"
                                        >
                                            Download
                                        </a>
                                        <span v-else class="text-slate-300">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
