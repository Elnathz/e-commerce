<script setup>
defineProps({
    activities: {
        type: Array,
        default: () => [],
    },
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

const typeMeta = (type) => {
    if (type?.startsWith('order.')) {
        if (type === 'order.cancelled') {
            return { icon: 'cancel', class: 'bg-red-50 text-red-600' };
        }
        if (type === 'order.completed') {
            return { icon: 'check', class: 'bg-emerald-50 text-emerald-600' };
        }
        return { icon: 'order', class: 'bg-blue-50 text-blue-600' };
    }

    if (type?.startsWith('return.')) {
        if (type === 'return.rejected') {
            return { icon: 'cancel', class: 'bg-red-50 text-red-600' };
        }
        if (type === 'return.refund_processed' || type === 'return.completed') {
            return { icon: 'check', class: 'bg-emerald-50 text-emerald-600' };
        }
        return { icon: 'return', class: 'bg-amber-50 text-amber-600' };
    }

    return { icon: 'default', class: 'bg-slate-100 text-slate-500' };
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-bold text-slate-900">
                Aktivitas Saya
            </h2>
            <p class="mt-1 text-sm font-medium text-slate-500">
                Riwayat tindakan yang Anda lakukan pada order dan retur.
            </p>
        </header>

        <div v-if="activities.length === 0" class="mt-6 flex flex-col items-center justify-center text-center py-10 rounded-xl border-2 border-dashed border-slate-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-slate-300">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <p class="mt-3 text-sm font-semibold text-slate-600">Belum ada aktivitas tercatat</p>
            <p class="mt-1 text-xs text-slate-400 max-w-xs">Tindakan Anda pada order dan retur akan muncul di sini.</p>
        </div>

        <ul v-else class="mt-6 space-y-3">
            <li v-for="activity in activities" :key="activity.id" class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="typeMeta(activity.type).class">
                    <svg v-if="typeMeta(activity.type).icon === 'order'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.25 10.5h.008v.008H8.25V10.5zm7.5 0h.008v.008H15.75V10.5z" />
                    </svg>
                    <svg v-else-if="typeMeta(activity.type).icon === 'return'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                    </svg>
                    <svg v-else-if="typeMeta(activity.type).icon === 'check'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else-if="typeMeta(activity.type).icon === 'cancel'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-700">{{ activity.description }}</p>
                    <div class="mt-1 flex items-center gap-2 flex-wrap">
                        <span v-if="activity.subject_label" class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border bg-slate-50 text-slate-500 border-slate-200">
                            {{ activity.subject_label }}
                        </span>
                        <span class="text-xs text-slate-400">{{ formatDate(activity.created_at) }}</span>
                    </div>
                </div>
            </li>
        </ul>
    </section>
</template>
