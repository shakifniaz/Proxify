<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { getApps, initializeApp } from 'firebase/app';
import { getAuth, signInWithEmailAndPassword } from 'firebase/auth';
import {
    ArrowRight,
    CalendarDays,
    Eye,
    EyeOff,
    Lock,
    Mail,
    Megaphone,
    ShieldCheck,
} from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
    firebaseConfig: { type: Object, default: () => ({}) },
    legacyLoginEmails: { type: Array, default: () => [] },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
    id_token: '',
});

const showPassword = ref(false);
const firebaseError = ref('');
const firebaseLoading = ref(false);

const hasFirebaseConfig = computed(() =>
    ['apiKey', 'authDomain', 'projectId', 'appId'].every((key) => Boolean(props.firebaseConfig?.[key]))
);

async function loadFirebaseAuth() {
    const app = getApps().length ? getApps()[0] : initializeApp(props.firebaseConfig);

    return { auth: getAuth(app), signInWithEmailAndPassword };
}

async function submit() {
    firebaseError.value = '';
    form.clearErrors();
    form.id_token = '';

    const legacyUser = props.legacyLoginEmails.includes(form.email.trim().toLowerCase());

    if (legacyUser) {
        firebaseLoading.value = true;
        form.post('/login', {
            onFinish: () => {
                firebaseLoading.value = false;
                form.reset('password', 'id_token');
            },
        });
        return;
    }

    if (!hasFirebaseConfig.value) {
        firebaseLoading.value = true;
        form.post('/login', {
            onFinish: () => {
                firebaseLoading.value = false;
                form.reset('password', 'id_token');
            },
        });
        return;
    }

    firebaseLoading.value = true;

    try {
        const { auth, signInWithEmailAndPassword } = await loadFirebaseAuth();
        const credential = await signInWithEmailAndPassword(auth, form.email, form.password);
        form.id_token = await credential.user.getIdToken();
    } catch (error) {
        if (error?.code?.startsWith('auth/')) {
            form.id_token = '';
            form.post('/login', {
                onFinish: () => {
                    firebaseLoading.value = false;
                    form.reset('password', 'id_token');
                },
            });
            return;
        }

        firebaseError.value = error?.code === 'auth/invalid-credential'
            ? 'Email or password is incorrect.'
            : `Firebase sign-in failed: ${error?.code || error?.message || 'Unknown error'}`;
        firebaseLoading.value = false;
        return;
    }

    form.post('/login', {
        onFinish: () => {
            firebaseLoading.value = false;
            form.reset('password', 'id_token');
        },
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="grid w-full max-w-6xl items-center gap-5 sm:gap-8 lg:grid-cols-[minmax(0,1fr)_27rem] xl:gap-14">
            <section class="min-w-0 py-1 text-[#1e2924] sm:py-4">
                <div class="flex items-center gap-3 sm:gap-4">
                    <ApplicationLogo class="h-14 w-14 shrink-0 sm:h-24 sm:w-24" />
                    <div class="min-w-0">
                        <p class="brand-wordmark text-4xl leading-none text-[#1e2924] sm:text-6xl">Scholarly</p>
                        <p class="mt-1 text-[11px] font-black uppercase tracking-[0.18em] text-[#09B884] sm:mt-2 sm:text-sm sm:tracking-[0.22em]">School command workspace</p>
                    </div>
                </div>

                <h1 class="mt-5 max-w-2xl text-3xl font-black leading-tight tracking-tight text-[#10211b] sm:mt-9 sm:text-5xl">
                    Keep school operations<br class="hidden sm:block" />
                    in sync.
                </h1>
                <p class="mt-3 max-w-xl text-sm font-semibold leading-6 text-slate-600 sm:mt-4 sm:text-base sm:leading-7">
                    Plan schedules, handle substitution work, manage classroom updates, and keep the institution moving from one focused workspace.
                </p>

                <div class="mt-5 grid max-w-2xl grid-cols-3 gap-2 sm:mt-8 sm:gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#1e2924] text-[#8BED9A] sm:h-11 sm:w-11 sm:rounded-xl">
                            <CalendarDays class="h-4 w-4 sm:h-5 sm:w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-[#10211b]">Routines</p>
                            <p class="hidden text-xs font-semibold text-slate-500 sm:block">Daily schedules</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#1e2924] text-[#8BED9A] sm:h-11 sm:w-11 sm:rounded-xl">
                            <Megaphone class="h-4 w-4 sm:h-5 sm:w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-[#10211b]">Notices</p>
                            <p class="hidden text-xs font-semibold text-slate-500 sm:block">Clear updates</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#1e2924] text-[#8BED9A] sm:h-11 sm:w-11 sm:rounded-xl">
                            <ShieldCheck class="h-4 w-4 sm:h-5 sm:w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-[#10211b]">Access</p>
                            <p class="hidden text-xs font-semibold text-slate-500 sm:block">Role based</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="min-h-0 rounded-xl border border-stone-200 bg-white p-5 shadow-2xl shadow-[#1e2924]/12 sm:rounded-2xl sm:p-8">
                <div class="mb-6 sm:mb-8">
                    <p class="text-xs font-black uppercase tracking-[0.24em] text-[#09B884]">Welcome back</p>
                    <h2 class="mt-2 text-2xl font-black tracking-tight text-[#1e2924] sm:mt-3 sm:text-3xl">Sign in</h2>
                    <p class="mt-2 text-sm text-slate-500">Open your institute workspace.</p>
                </div>

                <div v-if="status" class="mb-4 rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-3 py-2 text-sm font-semibold text-[#1e2924]">
                    {{ status }}
                </div>
                <div v-if="firebaseError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                    {{ firebaseError }}
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="email" class="section-title">Email</label>
                        <div class="relative mt-1">
                            <Mail class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="field-control w-full pl-9"
                                required
                                autocomplete="username"
                                placeholder="you@school.edu"
                            />
                        </div>
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <div>
                        <label for="password" class="section-title">Password</label>
                        <div class="relative mt-1">
                            <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="field-control w-full pl-9 pr-10"
                                required
                                autocomplete="current-password"
                                placeholder="Enter password"
                            />
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-[#1e2924]"
                                @click="showPassword = !showPassword"
                            >
                                <component :is="showPassword ? EyeOff : Eye" class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <div class="flex flex-col gap-3 min-[390px]:flex-row min-[390px]:items-center min-[390px]:justify-between">
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-600">
                            <Checkbox v-model:checked="form.remember" />
                            Remember me
                        </label>

                        <Link
                            v-if="canResetPassword"
                            href="/forgot-password"
                            class="text-sm font-bold text-[#1e2924] hover:text-[#09B884]"
                        >
                            Forgot password?
                        </Link>
                    </div>

                    <button type="submit" class="btn-primary min-h-12 w-full text-base" :disabled="form.processing || firebaseLoading">
                        {{ firebaseLoading || form.processing ? 'Signing in...' : 'Sign in' }}
                        <ArrowRight class="h-4 w-4" />
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    New to Scholarly?
                    <Link href="/register" class="font-bold text-[#1e2924] hover:text-[#09B884]">Create account</Link>
                </p>
            </section>
        </div>
    </GuestLayout>
</template>
