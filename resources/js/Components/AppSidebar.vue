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
const currentUrl = computed(() => page.url.split('?')[0]);

const authUser = computed(() => ({
    name: page.props.auth?.user?.name ?? 'Test User',
    role: page.props.auth?.user?.role ?? 'admin', // Defaults to admin if no role is found
}));

const isAdmin = computed(() => authUser.value.role.toLowerCase() === 'admin');

const initials = computed(() =>
    authUser.value.name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase()
);

const badgeColors = {
    rose: 'border border-red-200 bg-red-50 text-red-700',
    amber: 'border border-amber-200 bg-amber-50 text-amber-700',
};

// Admin Navigation
const adminNavGroups = [
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

// Teacher Navigation
const teacherNavGroups = [
    {
        label: 'Core',
        items: [
            { name: 'My Dashboard', href: '/dashboard', icon: LayoutDashboard },
            { name: 'Routines', href: '/routines', icon: CalendarDays },
            { name: 'Exam Duties', href: '/exam-schedule', icon: GraduationCap },
        ],
    },
    {
        label: 'Staff',
        items: [
            { name: 'My Leave', href: '/leave-requests', icon: CalendarOff },
            { name: 'Noticeboard', href: '/noticeboard', icon: Megaphone, badge: 1, color: 'rose' },
            { name: 'Staff Room', href: '/staff-room', icon: MessagesSquare },
        ],
    },
    {
        label: 'Academic',
        items: [
            { name: 'My Classrooms', href: '/classrooms', icon: School },
        ],
    },
];

// Switch navs dynamically
const activeNavGroups = computed(() => isAdmin.value ? adminNavGroups : teacherNavGroups);

function isActive(href) {
    return currentUrl.value === href;
}
</script>

<template>
    <aside
        class="sticky top-0 hidden h-screen flex-col border-r border-stone-200 bg-stone-50/95 shadow-[1px_0_0_rgba(15,23,42,0.02)] transition-all duration-200 sm:flex"
        :class="collapsed ? 'w-16' : 'w-64'"
    >
        <div class="flex h-16 items-center border-b border-stone-200" :class="collapsed ? 'justify-center px-2' : 'gap-3 px-5'">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-blue-100 bg-blue-700 shadow-sm">
                <span class="text-sm font-bold text-white">P</span>
            </div>
            <div v-if="!collapsed" class="min-w-0">
                <span class="block truncate text-base font-semibold tracking-wide text-slate-950">Proxify</span>
                <span class="block truncate text-[11px] font-medium text-slate-500">Routine operations</span>
            </div>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto py-5" :class="collapsed ? 'px-2' : 'px-3'">
            <div v-for="group in activeNavGroups" :key="group.label">
                <p
                    v-if="!collapsed"
                    class="px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400"
                >
                    {{ group.label }}
                </p>
                <div class="space-y-1" :class="collapsed ? 'mt-0' : 'mt-2'">
                    <Link
                        v-for="item in group.items"
                        :key="item.name"
                        :href="item.href"
                        :title="collapsed ? item.name : undefined"
                        class="group relative flex items-center rounded-lg border border-transparent text-sm font-medium transition-colors"
                        :class="[
                            collapsed ? 'h-10 justify-center px-0' : 'gap-3 px-3 py-2',
                            isActive(item.href)
                                ? 'border-blue-100 bg-blue-50 text-blue-800 shadow-sm'
                                : 'text-slate-600 hover:bg-white hover:text-slate-950 hover:shadow-sm'
                        ]"
                    >
                        <component :is="item.icon" class="h-[18px] w-[18px] shrink-0 stroke-[1.9]" :class="isActive(item.href) ? 'text-blue-700' : 'text-slate-500 group-hover:text-slate-800'" />
                        <span v-if="!collapsed" class="flex-1 truncate">{{ item.name }}</span>
                        <span
                            v-if="!collapsed && item.badge"
                            class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                            :class="badgeColors[item.color]"
                        >
                            {{ item.badge }}
                        </span>
                        <span
                            v-else-if="collapsed && item.badge"
                            class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full"
                            :class="item.color === 'amber' ? 'bg-amber-500' : 'bg-red-500'"
                        />
                    </Link>
                </div>
            </div>
        </nav>

        <button
            type="button"
            class="mx-2 mb-3 flex h-10 items-center justify-center gap-2 rounded-lg border border-stone-200 bg-white text-xs font-medium text-slate-500 transition-colors hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
            :title="collapsed ? 'Expand menu' : 'Collapse menu'"
            @click="$emit('toggle')"
        >
            <ChevronsLeft v-if="!collapsed" class="h-4 w-4" />
            <ChevronsRight v-else class="h-4 w-4" />
            <span v-if="!collapsed">Collapse Menu</span>
        </button>

        <div
            class="border-t border-stone-200 bg-white/70"
            :class="collapsed ? 'flex flex-col items-center gap-2 px-2 py-3' : 'flex items-center justify-between gap-2 px-4 py-4'"
        >
            <div class="flex min-w-0 items-center" :class="collapsed ? 'justify-center' : 'gap-3'" :title="collapsed ? `${authUser.name} - ${authUser.role}` : undefined">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-blue-100 bg-blue-50 text-xs font-bold text-blue-700"
                >
                    {{ initials }}
                </div>
                <div v-if="!collapsed" class="min-w-0">
                    <p class="truncate text-xs font-bold text-slate-900">{{ authUser.name }}</p>
                    <p class="truncate text-[11px] font-medium capitalize text-slate-500">{{ authUser.role }}</p>
                </div>
            </div>

            <Link
                href="/logout"
                method="post"
                as="button"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-transparent text-slate-500 transition-colors hover:border-red-100 hover:bg-red-50 hover:text-red-700"
                title="Log out session"
            >
                <LogOut class="h-4 w-4" />
            </Link>
        </div>
    </aside>
</template>
