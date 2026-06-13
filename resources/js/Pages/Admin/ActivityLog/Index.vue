<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { activityMeta } from '@/Utils/activityMeta';

const props = defineProps({
    logs: Object,
    filters: Object,
});

// WHITELIST type (rev.3) — JANGAN diturunkan dari data. Tambah manual saat domain baru resmi masuk.
const TYPE_OPTIONS = [
    { value: 'order', label: 'Order' },
    { value: 'return', label: 'Retur' },
];

const search = ref(props.filters?.q || '');
const typeFilter = ref(props.filters?.type || '');
const actorFilter = ref(props.filters?.actor || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

const apply = () => {
    router.get(route('admin.activity-log.index'), {
        q: search.value,
        type: typeFilter.value,
        actor: actorFilter.value,
        start_date: startDate.value,
        end_date: endDate.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

// Debounce search; filter lain langsung.
let searchTimer = null;
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(apply, 350); });
watch([typeFilter, actorFilter, startDate, endDate], apply);

const formatDateTime = (s) => s ? new Date(s).toLocaleString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
}) : '-';

const dayKey = (s) => new Date(s).toLocaleDateString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
});

// Grouping per tanggal untuk halaman ini saja. Header bisa berulang antar-halaman -> accepted;
// kronologi global tetap benar (created_at desc), bukan duplikasi data.
const grouped = computed(() => {
    const out = [];
    let last = null;
    for (const log of props.logs.data) {
        const k = dayKey(log.created_at);
        if (k !== last) { out.push({ day: k, items: [] }); last = k; }
        out[out.length - 1].items.push(log);
    }
    return out;
});

const formatRupiah = (v) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', minimumFractionDigits: 0,
}).format(v || 0);

// Deep-link ke entitas (rev.3, payoff tertinggi). null -> render badge biasa tanpa link.
const subjectLink = (log) => {
    if (log.subject_type === 'order') return route('admin.orders.show', log.subject_id);
    if (log.subject_type === 'return_request') return route('admin.returns.show', log.subject_id);
    return null;
};
</script>

<template>
    <Head title="Log Aktivitas" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-slate-800">Log Aktivitas</h1>
        </template>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-200">
                    <!-- Filter bar -->
                    <div class="p-4 border-b border-slate-200 flex flex-wrap gap-3 items-center">
                        <input
                            type="text"
                            v-model="search"
                            placeholder="Cari deskripsi, subjek, atau aktor..."
                            class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm w-full sm:w-64 text-sm"
                        >
                        <select v-model="typeFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
                            <option value="">Semua Tipe</option>
                            <option v-for="opt in TYPE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <select v-model="actorFilter" class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm font-medium text-slate-700">
                            <option value="">Semua Aktor</option>
                            <option value="admin">Admin</option>
                            <option value="system">Sistem</option>
                        </select>
                        <div class="flex items-center gap-2">
                            <input
                                type="date"
                                v-model="startDate"
                                class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm text-slate-700"
                            >
                            <span class="text-slate-400 text-sm">s/d</span>
                            <input
                                type="date"
                                v-model="endDate"
                                class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm text-slate-700"
                            >
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="logs.data.length === 0" class="flex flex-col items-center justify-center text-center py-16">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-slate-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <p class="mt-3 text-sm font-semibold text-slate-600">Belum ada aktivitas tercatat</p>
                        <p class="mt-1 text-xs text-slate-400 max-w-xs">Coba ubah filter, atau tunggu hingga ada aktivitas order/retur baru.</p>
                    </div>

                    <!-- Grouped list -->
                    <div v-else class="divide-y divide-slate-100">
                        <div v-for="group in grouped" :key="group.day">
                            <div class="px-4 py-2 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-400">
                                {{ group.day }}
                            </div>
                            <ul class="divide-y divide-slate-100">
                                <li v-for="log in group.items" :key="log.id" class="flex items-start gap-3 p-4">
                                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="activityMeta(log.type).class">
                                        <svg v-if="activityMeta(log.type).icon === 'order'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.25 10.5h.008v.008H8.25V10.5zm7.5 0h.008v.008H15.75V10.5z" />
                                        </svg>
                                        <svg v-else-if="activityMeta(log.type).icon === 'return'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                        </svg>
                                        <svg v-else-if="activityMeta(log.type).icon === 'check'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <svg v-else-if="activityMeta(log.type).icon === 'cancel'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-slate-700">
                                            {{ log.description }}
                                            <span v-if="log.subject_type === 'order' && log.metadata?.amount" class="text-slate-500">
                                                ({{ formatRupiah(log.metadata.amount) }})
                                            </span>
                                        </p>
                                        <div class="mt-1 flex items-center gap-2 flex-wrap">
                                            <Link
                                                v-if="log.subject_label && subjectLink(log)"
                                                :href="subjectLink(log)"
                                                data-testid="activity-subject-link"
                                                class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border bg-blue-50 text-blue-600 border-blue-200 hover:underline"
                                            >
                                                {{ log.subject_label }}
                                            </Link>
                                            <span v-else-if="log.subject_label" class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border bg-slate-50 text-slate-500 border-slate-200">
                                                {{ log.subject_label }}
                                            </span>
                                            <span class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm border"
                                                  :class="log.actor_type === 'admin' ? 'bg-indigo-50 text-indigo-600 border-indigo-200' : 'bg-slate-50 text-slate-500 border-slate-200'">
                                                {{ log.actor_name || (log.actor_type === 'admin' ? 'Admin' : 'Sistem') }}
                                            </span>
                                            <span class="text-xs text-slate-400">{{ formatDateTime(log.created_at) }}</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="logs.data.length > 0" class="px-4 py-3 border-t border-slate-200">
                        <Pagination :links="logs.links" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
