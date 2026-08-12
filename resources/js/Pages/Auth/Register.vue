<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { getApps, initializeApp } from 'firebase/app';
import {
    createUserWithEmailAndPassword,
    deleteUser,
    getAuth,
    updateProfile,
} from 'firebase/auth';
import {
    ArrowRight,
    Building2,
    KeyRound,
    GraduationCap,
    Lock,
    Mail,
    Phone,
    User,
    Users,
} from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    firebaseConfig: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: '',
    phone: '',
    email: '',
    role: 'admin',
    institution_name: '',
    institution_short_name: '',
    institution_phone: '',
    institution_email: '',
    institution_address: '',
    academic_year: '2026',
    teacher_code: '',
    class_code: '',
    password: '',
    password_confirmation: '',
    id_token: '',
});

const firebaseError = ref('');
const firebaseLoading = ref(false);
const registerCardScroller = ref(null);

const roles = [
    {
        key: 'admin',
        label: 'Admin',
        icon: Building2,
        line: 'Create an institution and manage the full school system.',
    },
    {
        key: 'teacher',
        label: 'Teacher',
        icon: Users,
        line: 'Access teaching, notices, leave, and classroom tools.',
    },
    {
        key: 'student',
        label: 'Student',
        icon: GraduationCap,
        line: 'Access routines, notices, classroom updates, and study tools.',
    },
];

const hasFirebaseConfig = computed(() =>
    ['apiKey', 'authDomain', 'projectId', 'appId'].every((key) => Boolean(props.firebaseConfig?.[key]))
);

async function loadFirebaseAuth() {
    const app = getApps().length ? getApps()[0] : initializeApp(props.firebaseConfig);

    return {
        auth: getAuth(app),
        createUserWithEmailAndPassword,
        deleteUser,
        updateProfile,
    };
}

async function submit() {
    firebaseError.value = '';
    form.clearErrors();

    if (!hasFirebaseConfig.value) {
        firebaseError.value = 'Firebase is not configured yet. Add the Firebase keys to .env and clear Laravel config.';
        return;
    }

    if (form.password !== form.password_confirmation) {
        form.setError('password_confirmation', 'The password confirmation does not match.');
        return;
    }

    firebaseLoading.value = true;
    let createdFirebaseUser = null;
    let deleteFirebaseUser = null;

    try {
        const firebase = await loadFirebaseAuth();
        deleteFirebaseUser = firebase.deleteUser;
        const credential = await firebase.createUserWithEmailAndPassword(firebase.auth, form.email, form.password);
        createdFirebaseUser = credential.user;
        await firebase.updateProfile(createdFirebaseUser, { displayName: form.name });
        form.id_token = await createdFirebaseUser.getIdToken();
    } catch (error) {
        firebaseError.value = error?.code === 'auth/email-already-in-use'
            ? 'This email already has a Firebase account. Please sign in instead.'
            : `Firebase registration failed: ${error?.code || error?.message || 'Unknown error'}`;
        firebaseLoading.value = false;
        return;
    }

    form.post('/register', {
        onError: async () => {
            if (createdFirebaseUser && deleteFirebaseUser) {
                try {
                    await deleteFirebaseUser(createdFirebaseUser);
                } catch {
                    firebaseError.value = 'The local account could not be created. A Firebase account may already exist for this email, so delete it from Firebase Authentication or use another email.';
                }
            }
        },
        onFinish: () => {
            firebaseLoading.value = false;
            form.reset('password', 'password_confirmation', 'id_token');
        },
    });
}

onMounted(() => {
    nextTick(() => {
        window.scrollTo(0, 0);
        if (registerCardScroller.value) {
            registerCardScroller.value.scrollTop = 0;
        }

        requestAnimationFrame(() => {
            window.scrollTo(0, 0);
        });
    });
});
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="grid w-full max-w-6xl items-center gap-5 sm:gap-8 lg:grid-cols-[minmax(0,1fr)_31rem] xl:gap-14">
            <section class="min-w-0 py-1 text-left text-[#1e2924] sm:py-4">
                <div class="flex items-center gap-3 sm:gap-4">
                    <ApplicationLogo class="h-14 w-14 shrink-0 sm:h-24 sm:w-24" />
                    <div class="min-w-0">
                        <p class="brand-wordmark text-4xl leading-none text-[#1e2924] sm:text-6xl">Scholarly</p>
                        <p class="mt-1 text-[11px] font-black uppercase tracking-[0.18em] text-[#09B884] sm:mt-2 sm:text-sm sm:tracking-[0.22em]">Join the workspace</p>
                    </div>
                </div>

                <h1 class="mt-5 max-w-2xl text-3xl font-black leading-tight tracking-tight text-[#10211b] sm:mt-9 sm:text-5xl">
                    Bring the whole school<br class="hidden sm:block" />
                    into one portal.
                </h1>
                <p class="mt-3 max-w-xl text-sm font-semibold leading-6 text-slate-600 sm:mt-4 sm:text-base sm:leading-7">
                    Admins create the institution. Teachers and students join through secure codes, then get the tools meant for their day.
                </p>

                <div class="mt-8 hidden max-w-2xl gap-3 text-left sm:grid sm:grid-cols-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#1e2924] text-[#8BED9A]">
                            <Building2 class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-black text-[#10211b]">Admin</p>
                            <p class="text-xs font-semibold text-slate-500">Setup and control</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#1e2924] text-[#8BED9A]">
                            <Users class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-black text-[#10211b]">Teacher</p>
                            <p class="text-xs font-semibold text-slate-500">Classes and staff</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#1e2924] text-[#8BED9A]">
                            <GraduationCap class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-black text-[#10211b]">Student</p>
                            <p class="text-xs font-semibold text-slate-500">Routine and work</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex min-h-0 w-full self-center overflow-hidden rounded-xl border border-stone-200 bg-white shadow-2xl shadow-[#1e2924]/12 sm:rounded-2xl lg:max-h-[min(43rem,calc(100vh-5rem))]">
                <div ref="registerCardScroller" class="min-h-0 flex-1 p-5 sm:p-8 lg:overflow-y-auto">
                    <div class="mb-6 sm:mb-7">
                        <p class="text-xs font-black uppercase tracking-[0.24em] text-[#09B884]">Create access</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-[#1e2924] sm:mt-3 sm:text-3xl">Register</h2>
                        <p class="mt-2 text-sm text-slate-500">Set up your institute account.</p>
                    </div>

                    <div v-if="firebaseError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                        {{ firebaseError }}
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="role in roles"
                            :key="role.key"
                            type="button"
                            class="rounded-xl border p-2 text-center transition-all sm:p-3 sm:text-left"
                            :class="form.role === role.key ? 'border-[#09B884] bg-[#8BED9A]/20 shadow-sm shadow-[#8BED9A]/30' : 'border-stone-200 bg-white hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10'"
                            @click="form.role = role.key"
                        >
                            <div class="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border sm:h-9 sm:w-9"
                                    :class="form.role === role.key ? 'border-[#8BED9A]/70 bg-white text-[#09B884]' : 'border-stone-200 bg-stone-50 text-slate-500'"
                                >
                                    <component :is="role.icon" class="h-4 w-4" />
                                </span>
                                <span class="text-xs font-black text-[#1e2924] sm:text-sm">{{ role.label }}</span>
                            </div>
                            <p class="mt-3 hidden text-xs leading-relaxed text-slate-500 sm:block">{{ role.line }}</p>
                        </button>
                    </div>
                    <InputError class="-mt-2" :message="form.errors.role" />

                    <div v-if="form.role === 'admin'" class="rounded-xl border border-[#8BED9A]/55 bg-[#8BED9A]/10 p-3 sm:p-4">
                        <div class="flex items-center gap-2">
                            <Building2 class="h-4 w-4 text-[#09B884]" />
                            <p class="text-sm font-black text-[#1e2924]">Institution setup</p>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="section-title">Institution name</label>
                                <input v-model="form.institution_name" type="text" class="field-control mt-1 w-full" placeholder="Institution name" />
                                <InputError class="mt-1" :message="form.errors.institution_name" />
                            </div>
                            <div>
                                <label class="section-title">Short name</label>
                                <input v-model="form.institution_short_name" type="text" class="field-control mt-1 w-full" placeholder="MS" />
                            </div>
                            <div>
                                <label class="section-title">Academic year</label>
                                <input v-model="form.academic_year" type="text" class="field-control mt-1 w-full" placeholder="2026" />
                            </div>
                            <div>
                                <label class="section-title">Institution phone</label>
                                <input v-model="form.institution_phone" type="text" class="field-control mt-1 w-full" placeholder="+8801XXXXXXXXX" />
                            </div>
                            <div>
                                <label class="section-title">Institution email</label>
                                <input v-model="form.institution_email" type="email" class="field-control mt-1 w-full" placeholder="admin@school.edu" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="section-title">Address</label>
                                <input v-model="form.institution_address" type="text" class="field-control mt-1 w-full" placeholder="Campus address" />
                            </div>
                        </div>
                    </div>

                    <div v-if="form.role !== 'admin'" class="rounded-xl border border-stone-200 bg-stone-50/70 p-3 sm:p-4">
                        <div class="flex items-center gap-2">
                            <KeyRound class="h-4 w-4 text-[#09B884]" />
                            <p class="text-sm font-black text-[#1e2924]">{{ form.role === 'teacher' ? 'Teacher join code' : 'Class join code' }}</p>
                        </div>
                        <input
                            v-if="form.role === 'teacher'"
                            v-model="form.teacher_code"
                            type="text"
                            class="field-control mt-3 w-full uppercase"
                            placeholder="TCH-XXXXXX"
                        />
                        <input
                            v-else
                            v-model="form.class_code"
                            type="text"
                            class="field-control mt-3 w-full uppercase"
                            placeholder="CLS-XXXXXX"
                        />
                        <InputError class="mt-1" :message="form.role === 'teacher' ? form.errors.teacher_code : form.errors.class_code" />
                    </div>

                    <div>
                        <label for="name" class="section-title">Full name</label>
                        <div class="relative mt-1">
                            <User class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="name" v-model="form.name" type="text" class="field-control w-full pl-9" required autocomplete="name" placeholder="Your full name" />
                        </div>
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div>
                        <label for="phone" class="section-title">Phone</label>
                        <div class="relative mt-1">
                            <Phone class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="phone" v-model="form.phone" type="text" class="field-control w-full pl-9" placeholder="+8801XXXXXXXXX" autocomplete="tel" />
                        </div>
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>

                    <div>
                        <label for="email" class="section-title">Email</label>
                        <div class="relative mt-1">
                            <Mail class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="email" v-model="form.email" type="email" class="field-control w-full pl-9" required autocomplete="username" placeholder="you@school.edu" />
                        </div>
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="section-title">Password</label>
                            <div class="relative mt-1">
                                <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input id="password" v-model="form.password" type="password" class="field-control w-full pl-9" required autocomplete="new-password" placeholder="Password" />
                            </div>
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <div>
                            <label for="password_confirmation" class="section-title">Confirm</label>
                            <div class="relative mt-1">
                                <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="field-control w-full pl-9" required autocomplete="new-password" placeholder="Confirm" />
                            </div>
                            <InputError class="mt-1" :message="form.errors.password_confirmation" />
                        </div>
                    </div>

                    <button type="submit" class="btn-primary min-h-12 w-full text-base" :disabled="form.processing || firebaseLoading">
                        {{ firebaseLoading || form.processing ? 'Creating account...' : 'Create account' }}
                        <ArrowRight class="h-4 w-4" />
                    </button>
                    </form>

                    <p class="mt-6 pb-2 text-center text-sm text-slate-500">
                        Already have an account?
                        <Link href="/login" class="font-bold text-[#1e2924] hover:text-[#09B884]">Sign in</Link>
                    </p>
                </div>
            </section>
        </div>
    </GuestLayout>
</template>
