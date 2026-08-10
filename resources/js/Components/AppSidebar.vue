<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    CalendarDays,
    Repeat,
    ClipboardList,
    CalendarOff,
    Megaphone,
    BookOpenCheck,
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
const navEl = ref(null);
const sidebarScrollKey = 'campulse_sidebar_scroll_top';

const authUser = computed(() => ({
    name: page.props.auth?.user?.name ?? 'User',
    role: page.props.auth?.user?.role ?? 'admin', // Defaults to admin if no role is found
}));

const isAdmin = computed(() => authUser.value.role.toLowerCase() === 'admin');
const isStudent = computed(() => authUser.value.role.toLowerCase() === 'student');

const initials = computed(() =>
    authUser.value.name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase()
);

// Admin Navigation
const adminNavGroups = [
    {
        label: 'Core',
        items: [
            { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
            { name: 'Routines', href: '/routines', icon: CalendarDays },
            { name: 'Proxy Manager', href: '/proxy-manager', icon: Repeat },
            { name: 'Exam Schedule', href: '/exam-schedule', icon: ClipboardList },
        ],
    },
    {
        label: 'Staff',
        items: [
            { name: 'Leave Requests', href: '/leave-requests', icon: CalendarOff },
            { name: 'Noticeboard', href: '/noticeboard', icon: Megaphone },
            { name: 'Staffroom', href: '/staffroom', icon: MessagesSquare },
            { name: 'Classroom', href: '/classroom', icon: BookOpenCheck },
        ],
    },
    {
        label: 'Academic',
        items: [
            { name: 'Analytics', href: '/analytics', icon: BarChart3 },
        ],
    },
    {
        label: 'Admin',
        items: [
            { name: 'Classes', href: '/classrooms', icon: School },
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
            { name: 'Routine', href: '/routines', icon: CalendarDays },
            { name: 'Exam Duties', href: '/exam-schedule', icon: ClipboardList },
        ],
    },
    {
        label: 'Staff',
        items: [
            { name: 'My Leave', href: '/leave-requests', icon: CalendarOff },
            { name: 'Noticeboard', href: '/noticeboard', icon: Megaphone },
            { name: 'Staffroom', href: '/staffroom', icon: MessagesSquare },
            { name: 'Classroom', href: '/classroom', icon: BookOpenCheck },
        ],
    },
    {
        label: 'Academic',
        items: [
            { name: 'My Classrooms', href: '/classrooms', icon: School },
        ],
    },
];

const studentNavGroups = [
    {
        label: 'Student',
        items: [
            { name: 'My Dashboard', href: '/dashboard', icon: LayoutDashboard },
            { name: 'My Routine', href: '/routines', icon: CalendarDays },
            { name: 'Exam Schedule', href: '/exam-schedule', icon: ClipboardList },
            { name: 'Noticeboard', href: '/noticeboard', icon: Megaphone },
            { name: 'Classroom', href: '/classroom', icon: BookOpenCheck },
        ],
    },
];

// Switch navs dynamically
const activeNavGroups = computed(() => {
    if (isAdmin.value) return adminNavGroups;
    if (isStudent.value) return studentNavGroups;
    return teacherNavGroups;
});

function isActive(href) {
    if (href === '/routines') {
        return currentUrl.value === '/routines' || (currentUrl.value !== '/routines/create' && /^\/routines\/[^/]+$/.test(currentUrl.value));
    }

    return currentUrl.value === href || currentUrl.value.startsWith(`${href}/`);
}

function rememberSidebarScroll() {
    if (!navEl.value) return;
    localStorage.setItem(sidebarScrollKey, String(navEl.value.scrollTop));
}

onMounted(() => {
    nextTick(() => {
        if (!navEl.value) return;
        navEl.value.scrollTop = Number(localStorage.getItem(sidebarScrollKey) || 0);
    });
});
</script>

<template>
    <aside
        class="sticky top-0 hidden h-screen flex-col border-r border-[#09B884]/25 bg-[#1e2924] shadow-[1px_0_0_rgba(30,41,36,0.24)] transition-all duration-200 sm:flex"
        :class="collapsed ? 'w-16 overflow-hidden' : 'w-64'"
    >
        <div class="flex h-16 items-center" :class="collapsed ? 'justify-center px-2' : 'justify-start px-6'">
            <span v-if="!collapsed" class="brand-wordmark block translate-y-1 pb-1 text-left text-[2.05rem] leading-tight text-white">Campulse</span>
            <span v-else class="brand-wordmark text-lg font-bold text-white">C</span>
        </div>

        <nav
            ref="navEl"
            class="flex-1"
            :class="collapsed ? 'space-y-2 overflow-hidden px-2 py-2' : 'space-y-5 overflow-y-auto px-3 py-4'"
            @scroll="rememberSidebarScroll"
        >
            <div v-for="group in activeNavGroups" :key="group.label">
                <p
                    v-if="!collapsed"
                    class="px-3 text-[10px] font-bold uppercase tracking-widest text-[#D8FFE0]/65"
                >
                    {{ group.label }}
                </p>
                <div :class="collapsed ? 'mt-0 space-y-2' : 'mt-2 space-y-1'">
                    <Link
                        v-for="item in group.items"
                        :key="item.name"
                        :href="item.href"
                        :title="collapsed ? item.name : undefined"
                        class="group relative flex items-center rounded-xl border text-sm font-semibold transition-all"
                        :class="[
                            collapsed ? 'h-10 justify-center px-0' : 'gap-3 px-3 py-2',
                            isActive(item.href)
                                ? 'border-[#8BED9A]/45 bg-white/14 text-[#BDF8C8] shadow-sm shadow-black/10 before:absolute before:left-0 before:top-2 before:h-6 before:w-1 before:rounded-r-full before:bg-[#8BED9A]'
                                : 'border-transparent text-white hover:border-[#8BED9A]/25 hover:bg-[#8BED9A]/14 hover:text-[#BDF8C8] hover:shadow-sm'
                        ]"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors"
                            :class="isActive(item.href) ? 'bg-[#8BED9A]/22 text-[#BDF8C8]' : 'bg-white/10 text-white group-hover:bg-[#8BED9A]/22 group-hover:text-[#BDF8C8]'"
                        >
                            <component :is="item.icon" class="h-[17px] w-[17px] stroke-[1.9]" />
                        </span>
                        <span v-if="!collapsed" class="flex-1 truncate">{{ item.name }}</span>
                    </Link>
                </div>
            </div>
        </nav>

        <button
            type="button"
            class="mx-3 mb-3 flex h-10 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/8 text-xs font-semibold text-[#E8FFF0]/75 shadow-sm transition-colors hover:border-[#8BED9A]/45 hover:bg-[#8BED9A]/16 hover:text-white"
            :title="collapsed ? 'Expand menu' : 'Collapse menu'"
            @click="$emit('toggle')"
        >
            <ChevronsLeft v-if="!collapsed" class="h-4 w-4" />
            <ChevronsRight v-else class="h-4 w-4" />
            <span v-if="!collapsed">Collapse Menu</span>
        </button>

        <div
            class="border-t border-white/10 bg-white/[0.04]"
            :class="collapsed ? 'flex flex-col items-center gap-2 px-2 py-3' : 'm-3 flex items-center justify-between gap-2 rounded-xl border border-white/10 px-3 py-3 shadow-sm shadow-black/10'"
        >
            <div class="flex min-w-0 items-center" :class="collapsed ? 'justify-center' : 'gap-3'" :title="collapsed ? `${authUser.name} - ${authUser.role}` : undefined">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#8BED9A]/35 bg-[#8BED9A]/18 text-xs font-bold text-[#8BED9A]"
                >
                    {{ initials }}
                </div>
                <div v-if="!collapsed" class="min-w-0">
                    <p class="truncate text-xs font-bold text-white">{{ authUser.name }}</p>
                    <p class="truncate text-[11px] font-medium capitalize text-[#D8FFE0]/70">{{ authUser.role }}</p>
                </div>
            </div>

            <Link
                v-if="!collapsed"
                href="/logout"
                method="post"
                as="button"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-transparent text-[#D8FFE0]/65 transition-colors hover:border-[#8BED9A]/30 hover:bg-[#8BED9A]/16 hover:text-white"
                title="Log out session"
            >
                <LogOut class="h-4 w-4" />
            </Link>
        </div>
    </aside>
</template>
