// Mapping ActivityLog `type` -> { icon, class } untuk badge ikon.
// Dipakai bersama oleh Admin/Profile RecentActivity.vue & Admin/ActivityLog/Index.vue.
export function activityMeta(type) {
    if (type?.startsWith('order.')) {
        if (type === 'order.cancelled') return { icon: 'cancel', class: 'bg-red-50 text-red-600' };
        if (type === 'order.completed') return { icon: 'check', class: 'bg-emerald-50 text-emerald-600' };
        return { icon: 'order', class: 'bg-blue-50 text-blue-600' };
    }
    if (type?.startsWith('return.')) {
        if (type === 'return.rejected') return { icon: 'cancel', class: 'bg-red-50 text-red-600' };
        if (type === 'return.refund_processed' || type === 'return.completed') return { icon: 'check', class: 'bg-emerald-50 text-emerald-600' };
        return { icon: 'return', class: 'bg-amber-50 text-amber-600' };
    }
    return { icon: 'default', class: 'bg-slate-100 text-slate-500' };
}
