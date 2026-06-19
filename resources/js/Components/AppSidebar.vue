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

// Badge color tokens kept as full literal class strings so Tailwind's
// content scanner can find them — never build class names dynamically.
const badgeColors = {
    rose: 'bg-rose-500 text-white',
    amber: 'bg-amber-500 text-slate-950',
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
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500">
                <span class="text-sm font-bold text-slate-950">P</span>
            </div>
            <span v-if="!collapsed" class="truncate text-base font-semibold text-white">Proxify</span>
        </div>

        <!-- Nav -->
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <div v-for="group in navGroups" :key="group.label">
                <p
                    v-if="!collapsed"
                    class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                >
                    {{ group.label }}
                </p>
                <div class="mt-2 space-y-1">
                    <Link
                        v-for="item in group.items"
                        :key="item.name"
                        :href="item.href"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            isActive(item.href)
                                ? 'bg-emerald-500/10 text-emerald-400'
                                : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-100'
                        "
                    >
                        <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" />
                        <span v-if="!collapsed" class="flex-1 truncate">{{ item.name }}</span>
                        <span
                            v-if="!collapsed && item.badge"
                            class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none"
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
            class="mx-3 mb-3 flex items-center justify-center gap-2 rounded-lg border border-slate-800 py-2 text-xs font-medium text-slate-500 hover:bg-slate-800/70 hover:text-slate-300"
            @click="$emit('toggle')"
        >
            <ChevronsLeft v-if="!collapsed" class="h-4 w-4" />
            <ChevronsRight v-else class="h-4 w-4" />
            <span v-if="!collapsed">Collapse</span>
        </button>

        <!-- User footer -->
        <div class="flex items-center gap-3 border-t border-slate-800 px-4 py-4">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-500/20 text-sm font-semibold text-sky-300"
            >
                {{ initials }}
            </div>
            <div v-if="!collapsed" class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-100">{{ authUser.name }}</p>
                <p class="truncate text-xs text-slate-500">{{ authUser.role }}</p>
            </div>
        </div>
    </aside>
</template>