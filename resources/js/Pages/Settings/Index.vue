<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bell, Check, Database, KeyRound, School, ShieldAlert, Smartphone, UserRound } from 'lucide-vue-next';

const props = defineProps({
    role: { type: String, default: 'admin' },
    general: { type: Object, default: () => ({}) },
    profile: { type: Object, default: () => ({}) },
    notificationSettings: { type: Object, default: () => ({}) },
    cleanup: { type: Array, default: null },
    noticeVisibilityOptions: { type: Array, default: () => [] },
});

const isAdmin = computed(() => props.role === 'admin');
const tab = ref(isAdmin.value ? 'Administration' : 'Edit profile');
const tabs = computed(() => (isAdmin.value ? ['Administration', 'Edit profile', 'Notifications', 'Database'] : ['Edit profile', 'Notifications']));
const saved = ref('');
const cleanupTarget = ref(props.cleanup?.[0]?.key ?? '');
const cleanupConfirmation = ref('');
const cleanupProcessing = ref(false);
const cleanupError = ref('');

const generalForm = useForm({
    schoolName: props.general.schoolName ?? '',
    shortName: props.general.shortName ?? '',
    contactPhone: props.general.contactPhone ?? '',
    contactEmail: props.general.contactEmail ?? '',
    address: props.general.address ?? '',
    academicYear: props.general.academicYear ?? '',
    defaultNoticeVisibility: props.general.defaultNoticeVisibility ?? 'Teachers',
});

const profileForm = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    whatsappRoutineUpdates: props.notificationSettings.whatsappRoutineUpdates ?? true,
});

const selectedCleanup = computed(() => props.cleanup?.find((item) => item.key === cleanupTarget.value));

function markSaved(message) {
    saved.value = message;
    window.setTimeout(() => {
        if (saved.value === message) saved.value = '';
    }, 2200);
}

function tabIcon(item) {
    return {
        Administration: School,
        Database,
        Notifications: Bell,
        'Edit profile': UserRound,
    }[item] ?? UserRound;
}

function saveGeneral() {
    generalForm.put('/settings/general', {
        preserveScroll: true,
        onSuccess: () => markSaved('Administration settings saved.'),
    });
}

function saveProfile() {
    profileForm.patch('/profile', {
        preserveScroll: true,
        onSuccess: () => markSaved('Profile saved.'),
    });
}

function savePassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            markSaved('Password updated.');
        },
    });
}

function saveNotifications() {
    notificationForm.put('/settings/notifications', {
        preserveScroll: true,
        onSuccess: () => markSaved('Notification settings saved.'),
    });
}

function clearData() {
    cleanupError.value = '';
    cleanupProcessing.value = true;

    router.delete('/settings/data', {
        data: {
            target: cleanupTarget.value,
            confirmation: cleanupConfirmation.value,
        },
        preserveScroll: true,
        onSuccess: () => {
            cleanupConfirmation.value = '';
            markSaved(`${selectedCleanup.value?.label ?? 'Data'} cleared.`);
        },
        onError: (errors) => {
            cleanupError.value = errors.confirmation || errors.target || 'Cleanup could not be completed.';
        },
        onFinish: () => {
            cleanupProcessing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout title="Settings">
        <div class="mx-auto max-w-6xl space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="surface-card flex flex-wrap gap-1 p-1">
                <button
                    v-for="item in tabs"
                    :key="item"
                    type="button"
                    class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-black transition-colors"
                    :class="tab === item ? 'bg-[#1e2924] text-[#8BED9A] shadow-sm' : 'text-slate-500 hover:bg-[#8BED9A]/14 hover:text-[#1e2924]'"
                    @click="tab = item"
                >
                    <component :is="tabIcon(item)" class="h-4 w-4" />
                    {{ item }}
                </button>
                </div>

                <div v-if="saved" class="inline-flex items-center gap-2 rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-3 py-2 text-sm font-black text-[#1e2924]">
                    <Check class="h-4 w-4 text-[#09B884]" />
                    {{ saved }}
                </div>
            </div>

            <form v-if="tab === 'Administration'" class="grid gap-4 lg:grid-cols-[1.25fr_0.75fr]" @submit.prevent="saveGeneral">
                <section class="surface-card overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-stone-200 bg-stone-50 px-5 py-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#09B884]">
                            <School class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-[#1e2924]">Institution profile</h3>
                            <p class="text-xs font-semibold text-slate-500">Saved school identity used across the app.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <label class="sm:col-span-2">
                            <span class="section-title">School name</span>
                            <input v-model="generalForm.schoolName" type="text" class="field-control mt-1 w-full" />
                            <span v-if="generalForm.errors.schoolName" class="mt-1 block text-xs text-red-600">{{ generalForm.errors.schoolName }}</span>
                        </label>
                        <label>
                            <span class="section-title">Short name</span>
                            <input v-model="generalForm.shortName" type="text" class="field-control mt-1 w-full" />
                        </label>
                        <label>
                            <span class="section-title">Academic year</span>
                            <input v-model="generalForm.academicYear" type="text" class="field-control mt-1 w-full" placeholder="2026/27" />
                        </label>
                        <label>
                            <span class="section-title">Contact phone</span>
                            <input v-model="generalForm.contactPhone" type="text" class="field-control mt-1 w-full" />
                        </label>
                        <label>
                            <span class="section-title">Contact email</span>
                            <input v-model="generalForm.contactEmail" type="email" class="field-control mt-1 w-full" />
                            <span v-if="generalForm.errors.contactEmail" class="mt-1 block text-xs text-red-600">{{ generalForm.errors.contactEmail }}</span>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="section-title">Address</span>
                            <input v-model="generalForm.address" type="text" class="field-control mt-1 w-full" />
                        </label>
                    </div>
                </section>

                <section class="surface-card overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-stone-200 bg-stone-50 px-5 py-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#09B884]">
                            <Bell class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-[#1e2924]">Noticeboard default</h3>
                            <p class="text-xs font-semibold text-slate-500">Used when a new institutional notice has no visibility selected.</p>
                        </div>
                    </div>

                    <div class="p-5">
                        <label class="block">
                            <span class="section-title">Default visibility</span>
                            <select v-model="generalForm.defaultNoticeVisibility" class="field-control mt-1 w-full">
                                <option v-for="option in noticeVisibilityOptions" :key="option" :value="option">{{ option }}</option>
                            </select>
                        </label>

                        <button type="submit" class="btn-primary mt-5 w-full" :disabled="generalForm.processing">
                            Save administration
                        </button>
                    </div>
                </section>
            </form>

            <section v-if="tab === 'Edit profile'" class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                <form class="surface-card overflow-hidden" @submit.prevent="saveProfile">
                    <div class="flex items-center gap-3 border-b border-stone-200 bg-stone-50 px-5 py-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#09B884]">
                            <UserRound class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-[#1e2924]">Profile</h3>
                            <p class="text-xs font-semibold text-slate-500">Keep your account details accurate for this institution.</p>
                        </div>
                    </div>

                    <div class="grid gap-4 p-5">
                        <label>
                            <span class="section-title">Name</span>
                            <input v-model="profileForm.name" type="text" class="field-control mt-1 w-full" autocomplete="name" />
                            <span v-if="profileForm.errors.name" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.name }}</span>
                        </label>
                        <label>
                            <span class="section-title">Email</span>
                            <input v-model="profileForm.email" type="email" class="field-control mt-1 w-full" autocomplete="email" />
                            <span v-if="profileForm.errors.email" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.email }}</span>
                        </label>
                        <label>
                            <span class="section-title">Phone</span>
                            <input v-model="profileForm.phone" type="text" class="field-control mt-1 w-full" autocomplete="tel" />
                            <span v-if="profileForm.errors.phone" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.phone }}</span>
                        </label>

                        <button type="submit" class="btn-primary" :disabled="profileForm.processing">
                            Save profile
                        </button>
                    </div>
                </form>

                <form class="surface-card overflow-hidden" @submit.prevent="savePassword">
                    <div class="flex items-center gap-3 border-b border-stone-200 bg-stone-50 px-5 py-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#1e2924]/15 bg-[#1e2924] text-[#8BED9A]">
                            <KeyRound class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-[#1e2924]">Password</h3>
                            <p class="text-xs font-semibold text-slate-500">Change your password without leaving Settings.</p>
                        </div>
                    </div>

                    <div class="grid gap-4 p-5">
                        <label>
                            <span class="section-title">Current password</span>
                            <input v-model="passwordForm.current_password" type="password" class="field-control mt-1 w-full" autocomplete="current-password" />
                            <span v-if="passwordForm.errors.current_password" class="mt-1 block text-xs text-red-600">{{ passwordForm.errors.current_password }}</span>
                        </label>
                        <label>
                            <span class="section-title">New password</span>
                            <input v-model="passwordForm.password" type="password" class="field-control mt-1 w-full" autocomplete="new-password" />
                            <span v-if="passwordForm.errors.password" class="mt-1 block text-xs text-red-600">{{ passwordForm.errors.password }}</span>
                        </label>
                        <label>
                            <span class="section-title">Confirm password</span>
                            <input v-model="passwordForm.password_confirmation" type="password" class="field-control mt-1 w-full" autocomplete="new-password" />
                            <span v-if="passwordForm.errors.password_confirmation" class="mt-1 block text-xs text-red-600">{{ passwordForm.errors.password_confirmation }}</span>
                        </label>

                        <button type="submit" class="btn-primary" :disabled="passwordForm.processing">
                            Update password
                        </button>
                    </div>
                </form>
            </section>

            <form v-if="tab === 'Notifications'" class="surface-card max-w-3xl overflow-hidden" @submit.prevent="saveNotifications">
                <div class="flex items-center gap-3 border-b border-stone-200 bg-stone-50 px-5 py-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#09B884]">
                        <Bell class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-[#1e2924]">Notifications</h3>
                        <p class="text-xs font-semibold text-slate-500">Choose which active app messages should be sent to your saved contact details.</p>
                    </div>
                </div>

                <label class="m-5 flex cursor-pointer items-start justify-between gap-4 rounded-lg border border-stone-200 bg-stone-50 p-4 transition hover:border-[#8BED9A]/70 hover:bg-white">
                    <span class="flex min-w-0 gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#8BED9A]/60 bg-[#8BED9A]/15 text-[#09B884]">
                            <Smartphone class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-950">WhatsApp routine updates</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                When this is off, your WhatsApp number is ignored for proxy routine and schedule messages until you enable it again.
                            </span>
                        </span>
                    </span>
                    <span class="relative mt-1 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors" :class="notificationForm.whatsappRoutineUpdates ? 'bg-[#09B884]' : 'bg-slate-300'">
                        <input v-model="notificationForm.whatsappRoutineUpdates" type="checkbox" class="peer sr-only" />
                        <span class="absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform" :class="notificationForm.whatsappRoutineUpdates ? 'translate-x-5' : ''"></span>
                    </span>
                </label>
                <span v-if="notificationForm.errors.whatsappRoutineUpdates" class="-mt-3 mb-4 block px-5 text-xs text-red-600">{{ notificationForm.errors.whatsappRoutineUpdates }}</span>

                <div class="border-t border-stone-200 px-5 py-4">
                    <button type="submit" class="btn-primary" :disabled="notificationForm.processing">
                        Save notifications
                    </button>
                </div>
            </form>

            <section v-if="tab === 'Database' && cleanup" class="grid gap-4 lg:grid-cols-[1fr_0.8fr]">
                <div class="surface-card overflow-hidden">
                    <div class="border-b border-stone-200 bg-stone-50 p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-700">
                                <Database class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-slate-950">Database cleanup</h3>
                                <p class="text-sm text-slate-500">Clear one category at a time for this institution.</p>
                            </div>
                        </div>
                    </div>

                    <div class="divide-y divide-stone-200">
                        <label
                            v-for="item in cleanup"
                            :key="item.key"
                            class="flex cursor-pointer items-start gap-4 p-4 transition-colors hover:bg-stone-50"
                            :class="cleanupTarget === item.key ? 'bg-[#8BED9A]/10' : ''"
                        >
                            <input v-model="cleanupTarget" type="radio" name="cleanup-target" :value="item.key" class="mt-1 h-4 w-4 border-stone-300 text-[#09B884] focus:ring-[#09B884]" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-950">{{ item.label }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ item.count }}</span>
                                </span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">{{ item.description }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <form class="surface-card border-red-200 p-5" @submit.prevent="clearData">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-700">
                            <ShieldAlert class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-red-800">Confirm cleanup</h3>
                            <p class="text-sm text-slate-500">This cannot be undone from Settings.</p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                        You are about to clear <strong>{{ selectedCleanup?.label }}</strong>
                        <span v-if="selectedCleanup"> with {{ selectedCleanup.count }} saved record{{ selectedCleanup.count === 1 ? '' : 's' }}</span>.
                    </div>

                    <label class="mt-5 block">
                        <span class="section-title">Type CLEAR to confirm</span>
                        <input v-model="cleanupConfirmation" type="text" class="field-control mt-1 w-full" autocomplete="off" />
                    </label>

                    <p v-if="cleanupError" class="mt-2 text-sm text-red-600">{{ cleanupError }}</p>

                    <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40" :disabled="cleanupProcessing || cleanupConfirmation !== 'CLEAR' || !cleanupTarget">
                        <ShieldAlert class="h-4 w-4" />
                        Clear selected data
                    </button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
