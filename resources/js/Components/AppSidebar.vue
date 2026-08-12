<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
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
    Menu,
    X,
} from 'lucide-vue-next';

defineProps({
    collapsed: { type: Boolean, default: false },
});

defineEmits(['toggle']);

const page = usePage();
const currentUrl = computed(() => page.url.split('?')[0]);
const navEl = ref(null);
const mobileMenuOpen = ref(false);
const sidebarScrollKey = 'scholarly_sidebar_scroll_top';

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
            { name: 'Class Coverage', href: '/proxy-manager', icon: Repeat },
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
            { name: 'Settings', href: '/settings', icon: Settings },
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
            { name: 'Settings', href: '/settings', icon: Settings },
        ],
    },
];

// Switch navs dynamically
const activeNavGroups = computed(() => {
    if (isAdmin.value) return adminNavGroups;
    if (isStudent.value) return studentNavGroups;
    return teacherNavGroups;
});

const activeNavItems = computed(() => activeNavGroups.value.flatMap((group) => group.items));
const mobilePrimaryItems = computed(() => activeNavItems.value.slice(0, 4));
const mobileSecondaryItems = computed(() => activeNavItems.value.slice(4));
const mobileMoreActive = computed(() => mobileSecondaryItems.value.some((item) => isActive(item.href)));

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
        class="sticky top-0 z-40 hidden h-screen flex-col overflow-visible bg-[#1e2924] shadow-[12px_0_36px_rgba(15,23,20,0.22)] transition-all duration-200 sm:flex"
        :class="collapsed ? 'w-[4.75rem]' : 'w-[17rem]'"
    >
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(160deg,#1e2924_0%,#173a2f_48%,#0f211c_100%)]"></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-[linear-gradient(180deg,rgba(139,237,154,0.16),transparent)]"></div>
        <div class="pointer-events-none absolute inset-y-0 right-0 w-px bg-gradient-to-b from-transparent via-[#8BED9A]/35 to-transparent"></div>

        <button
            type="button"
            class="absolute -right-2 top-5 z-50 grid h-4 w-4 place-items-center rounded-full bg-[#1e2924] text-gray-300 shadow-[0_5px_14px_rgba(0,0,0,0.2)] ring-1 ring-white/15 transition-colors hover:bg-[#263830] hover:text-white"
            :title="collapsed ? 'Expand menu' : 'Collapse menu'"
            @click="$emit('toggle')"
        >
            <ChevronsLeft v-if="!collapsed" class="h-2.5 w-2.5" />
            <ChevronsRight v-else class="h-2.5 w-2.5" />
        </button>

        <div class="relative flex items-center" :class="collapsed ? 'h-[clamp(4.5rem,9vh,5rem)] justify-center px-0' : 'h-20 justify-start px-5 pr-10'">
            <div class="flex min-w-0 items-center" :class="collapsed ? 'justify-center' : 'gap-2.5'">
                <span
                    class="grid shrink-0 place-items-center"
                    :class="collapsed ? 'h-11 w-11' : 'h-11 w-12'"
                >
                    <ApplicationLogo class="h-full w-full" />
                </span>
                <div v-if="!collapsed" class="min-w-0">
                    <span class="brand-wordmark block text-left text-[1.9rem] leading-none text-white">Scholarly</span>
                </div>
            </div>
        </div>

        <nav
            ref="navEl"
            class="relative flex-1"
            :class="collapsed ? (isAdmin ? 'flex flex-col justify-between overflow-hidden px-3 py-2' : 'flex flex-col gap-2 overflow-hidden px-3 py-2') : 'space-y-5 overflow-y-auto px-3 py-4'"
            @scroll="rememberSidebarScroll"
        >
            <div v-for="group in activeNavGroups" :key="group.label" :class="collapsed ? 'contents' : ''">
                <p
                    v-if="!collapsed"
                    class="px-3 text-[10px] font-black uppercase tracking-[0.18em] text-white/55"
                >
                    {{ group.label }}
                </p>
                <div :class="collapsed ? 'contents' : 'mt-2 space-y-1'">
                    <Link
                        v-for="item in group.items"
                        :key="item.name"
                        :href="item.href"
                        :title="collapsed ? item.name : undefined"
                        class="group relative flex items-center text-sm font-bold transition-all"
                        :class="[
                            collapsed ? 'h-[clamp(2.35rem,5.15vh,2.75rem)] justify-center rounded-xl px-0' : 'h-11 gap-3 rounded-2xl px-3',
                            isActive(item.href)
                                ? 'bg-white/[0.13] text-white shadow-[0_12px_30px_rgba(139,237,154,0.16),inset_0_1px_0_rgba(255,255,255,0.08)]'
                                : 'text-gray-300/75 hover:bg-white/[0.08] hover:text-white'
                        ]"
                    >
                        <span
                            class="grid shrink-0 place-items-center rounded-lg transition-colors"
                            :class="[
                                collapsed ? 'h-[clamp(2rem,4.5vh,2.25rem)] w-[clamp(2rem,4.5vh,2.25rem)]' : 'h-9 w-9',
                                isActive(item.href) ? 'text-white' : 'text-gray-300/75 group-hover:text-white'
                            ]"
                        >
                            <component :is="item.icon" :class="collapsed ? 'h-[1.15rem] w-[1.15rem]' : 'h-5 w-5'" class="stroke-[2.35]" />
                        </span>
                        <span v-if="!collapsed" class="flex-1 truncate">{{ item.name }}</span>
                    </Link>
                </div>
            </div>
        </nav>

        <div
            class="relative bg-white/[0.05]"
            :class="collapsed ? 'flex flex-col items-center gap-1 px-3 py-2' : 'm-3 flex items-center justify-between gap-2 rounded-2xl px-3 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_14px_28px_rgba(0,0,0,0.12)]'"
        >
            <div class="flex min-w-0 items-center" :class="collapsed ? 'justify-center' : 'gap-3'" :title="collapsed ? `${authUser.name} - ${authUser.role}` : undefined">
                <div
                    class="flex shrink-0 items-center justify-center bg-[#8BED9A]/16 text-xs font-black text-[#8BED9A]"
                    :class="collapsed ? 'h-9 w-9 rounded-lg' : 'h-9 w-9 rounded-xl'"
                >
                    {{ initials }}
                </div>
                <div v-if="!collapsed" class="min-w-0">
                    <p class="truncate text-xs font-bold text-white">{{ authUser.name }}</p>
                    <p class="truncate text-[11px] font-medium capitalize text-white/70">{{ authUser.role }}</p>
                </div>
            </div>

            <Link
                v-if="!collapsed"
                href="/logout"
                method="post"
                as="button"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white/65 transition-colors hover:bg-[#8BED9A]/16 hover:text-white"
                title="Log out session"
            >
                <LogOut class="h-4 w-4" />
            </Link>
        </div>
    </aside>

    <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-[#8BED9A]/35 bg-[#1e2924]/96 px-2 pb-[max(env(safe-area-inset-bottom),0.35rem)] pt-2 shadow-[0_-18px_36px_rgba(15,23,20,0.22)] backdrop-blur sm:hidden">
        <div class="mx-auto grid max-w-md grid-cols-5 gap-1">
            <Link
                v-for="item in mobilePrimaryItems"
                :key="item.name"
                :href="item.href"
                class="flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-black leading-none transition"
                :class="isActive(item.href) ? 'bg-white/[0.13] text-white' : 'text-gray-300/75 active:bg-white/[0.08]'"
            >
                <component :is="item.icon" class="h-5 w-5 shrink-0 stroke-[2.35]" />
                <span class="max-w-full truncate">{{ item.name.replace('My ', '') }}</span>
            </Link>

            <button
                type="button"
                class="flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-black leading-none transition"
                :class="mobileMoreActive || mobileMenuOpen ? 'bg-white/[0.13] text-white' : 'text-gray-300/75 active:bg-white/[0.08]'"
                aria-label="Open full menu"
                :aria-expanded="mobileMenuOpen"
                @click="mobileMenuOpen = true"
            >
                <Menu class="h-5 w-5 shrink-0 stroke-[2.35]" />
                <span>Menu</span>
            </button>
        </div>
    </nav>

    <Teleport to="body">
        <div v-if="mobileMenuOpen" class="fixed inset-0 z-[60] sm:hidden">
            <button
                type="button"
                class="absolute inset-0 bg-[#10211b]/55 backdrop-blur-[2px]"
                aria-label="Close menu"
                @click="mobileMenuOpen = false"
            ></button>

            <section class="absolute inset-x-0 bottom-0 max-h-[82vh] overflow-hidden rounded-t-3xl bg-[#1e2924] text-white shadow-2xl shadow-black/30">
                <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(160deg,#1e2924_0%,#173a2f_48%,#0f211c_100%)]"></div>
                <div class="relative flex items-center justify-between border-b border-white/10 px-4 py-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <ApplicationLogo class="h-9 w-10 shrink-0" />
                        <div class="min-w-0">
                            <p class="brand-wordmark truncate text-2xl leading-none">Scholarly</p>
                            <p class="mt-1 truncate text-xs font-bold capitalize text-white/55">{{ authUser.role }} menu</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 text-white/75 transition active:bg-white/15"
                        aria-label="Close menu"
                        @click="mobileMenuOpen = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="relative max-h-[calc(82vh-9rem)] overflow-y-auto px-3 py-3">
                    <div v-for="group in activeNavGroups" :key="group.label" class="py-2">
                        <p class="px-2 text-[10px] font-black uppercase tracking-[0.18em] text-white/45">{{ group.label }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <Link
                                v-for="item in group.items"
                                :key="item.name"
                                :href="item.href"
                                class="flex min-h-14 items-center gap-3 rounded-2xl px-3 text-sm font-black transition"
                                :class="isActive(item.href) ? 'bg-white/[0.14] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]' : 'bg-white/[0.06] text-gray-300 active:bg-white/[0.1]'"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/[0.07]">
                                    <component :is="item.icon" class="h-5 w-5 stroke-[2.35]" />
                                </span>
                                <span class="min-w-0 truncate">{{ item.name }}</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="relative flex items-center justify-between gap-3 border-t border-white/10 px-4 pb-[max(env(safe-area-inset-bottom),1rem)] pt-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#8BED9A]/16 text-xs font-black text-[#8BED9A]">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black">{{ authUser.name }}</p>
                            <p class="truncate text-xs font-bold capitalize text-white/50">{{ authUser.role }}</p>
                        </div>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        type="button"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 text-white/70 transition active:bg-white/15"
                        title="Log out session"
                    >
                        <LogOut class="h-5 w-5" />
                    </Link>
                </div>
            </section>
        </div>
    </Teleport>
</template>
