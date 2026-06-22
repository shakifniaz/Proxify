<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    CalendarDays,
    Repeat,
    GraduationCap,
    CalendarOff,
    Megaphone,
    MessagesSquare,
    School,
    BarChart3,
    Users,
    CalendarPlus,
    Settings,
    ChevronsLeft,
    ChevronsRight,
    LogOut,
} from 'lucide-vue-next';

defineProps({
    collapsed: { type: Boolean, default: false },
});

defineEmits(['toggle']);

const page = usePage();
// Inertia exposes the current path as page.url (e.g. "/dashboard").
const currentUrl = computed(() => page.url.split('?')[0]);

const authUser = computed(() => ({
    name: page.props.auth?.user?.name ?? 'Admin User',
    role: page.props.auth?.user?.role ?? 'Super Admin',
}));

const initials = computed(() =>
    authUser.value.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
);

const badgeColors = {
    rose: 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
    amber: 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
};

const navGroups = [
    {
        label: 'Core',
        items: [
            { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
            { name: 'Routines', href: '/routines', icon: CalendarDays },
            { name: 'Proxy Manager', href: '/proxy-manager', icon: Repeat, badge: 3, color: 'rose' },
            { name: 'Exam Schedule', href: '/exam-schedule', icon: GraduationCap },
        ],
    },
    {
        label: 'Staff',
        items: [
            { name: 'Leave Requests', href: '/leave-requests', icon: CalendarOff, badge: 2, color: 'amber' },
            { name: 'Noticeboard', href: '/noticeboard', icon: Megaphone },
            { name: 'Staff Room', href: '/staff-room', icon: MessagesSquare, badge: 9, color: 'rose' },
        ],
    },
    {
        label: 'Academic',
        items: [
            { name: 'Classrooms', href: '/classrooms', icon: School },
            { name: 'Analytics', href: '/analytics', icon: BarChart3 },
        ],
    },
    {
        label: 'Admin',
        items: [
            { name: 'Teachers', href: '/teachers', icon: Users },
            { name: 'Create Routine', href: '/routines/create', icon: CalendarPlus },
            { name: 'Settings', href: '/settings', icon: Settings },
        ],
    },
];

function isActive(href) {
    return currentUrl.value === href;
}
</script>

<template>
    <aside
        class="sticky top-0 hidden h-screen flex-col border-r border-slate-800 bg-slate-900/60 transition-all duration-200 sm:flex"
        :class="collapsed ? 'w-20' : 'w-64'"
    >
        <!-- Brand -->
        <div class="flex h-16 items-center gap-3 border-b border-slate-800 px-5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-600 shadow-md shadow-teal-950/30">
                <span class="text-sm font-bold text-slate-950">P</span>
            </div>
            <span v-if="!collapsed" class="truncate text-base font-semibold text-white tracking-wide">Proxify</span>
        </div>

        <!-- Nav Links -->
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <div v-for="group in navGroups" :key="group.label">
                <p
                    v-if="!collapsed"
                    class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500"
                >
                    {{ group.label }}
                </p>
                <div class="mt-2 space-y-1">
                    <Link
                        v-for="item in group.items"
                        :key="item.name"
                        :href="item.href"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors border border-transparent"
                        :class="
                            isActive(item.href)
                                ? 'bg-teal-500/10 text-teal-400 border-teal-500/20'
                                : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100'
                        "
                    >
                        <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" :class="isActive(item.href) ? 'text-teal-400' : 'text-slate-500 group-hover:text-slate-300'" />
                        <span v-if="!collapsed" class="flex-1 truncate">{{ item.name }}</span>
                        <span
                            v-if="!collapsed && item.badge"
                            class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                            :class="badgeColors[item.color]"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Collapse toggle -->
        <button
            type="button"
            class="mx-3 mb-3 flex items-center justify-center gap-2 rounded-lg border border-slate-800 py-2 text-xs font-medium text-slate-500 hover:bg-slate-800/60 hover:text-slate-300 transition-colors"
            @click="$emit('toggle')"
        >
            <ChevronsLeft v-if="!collapsed" class="h-4 w-4" />
            <ChevronsRight v-else class="h-4 w-4" />
            <span v-if="!collapsed">Collapse Menu</span>
        </button>

        <!-- User profile & Logout Footer -->
        <div class="flex items-center justify-between border-t border-slate-800 px-4 py-4 gap-2 bg-slate-950/20">
            <div class="flex items-center gap-3 min-w-0">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-500/10 text-xs font-bold text-teal-400 border border-teal-500/20"
                >
                    {{ initials }}
                </div>
                <div v-if="!collapsed" class="min-w-0">
                    <p class="truncate text-xs font-bold text-slate-200">{{ authUser.name }}</p>
                    <p class="truncate text-[11px] text-slate-500 font-medium">{{ authUser.role }}</p>
                </div>
            </div>

            <!-- Operational Post Logout trigger -->
            <Link
                href="/logout"
                method="post"
                as="button"
                type="button"
                class="p-2 text-slate-500 hover:text-rose-400 rounded-lg hover:bg-slate-900/60 border border-transparent hover:border-slate-800 transition-colors shrink-0"
                title="Log out session"
            >
                <LogOut class="h-4 w-4" />
            </Link>
        </div>
    </aside>
</template>