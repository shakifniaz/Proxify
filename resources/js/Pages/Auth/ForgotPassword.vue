<script setup>
import { computed, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link } from '@inertiajs/vue3';
import { getApps, initializeApp } from 'firebase/app';
import { getAuth, sendPasswordResetEmail } from 'firebase/auth';
import { ArrowLeft, Mail, Send } from 'lucide-vue-next';

const props = defineProps({
    status: { type: String, default: null },
    firebaseConfig: { type: Object, default: () => ({}) },
});

const email = ref('');
const error = ref('');
const sent = ref(props.status || '');
const loading = ref(false);

const hasFirebaseConfig = computed(() =>
    ['apiKey', 'authDomain', 'projectId', 'appId'].every((key) => Boolean(props.firebaseConfig?.[key]))
);

async function loadFirebaseAuth() {
    const app = getApps().length ? getApps()[0] : initializeApp(props.firebaseConfig);

    return { auth: getAuth(app), sendPasswordResetEmail };
}

async function submit() {
    error.value = '';
    sent.value = '';

    if (!email.value) {
        error.value = 'Enter your email address.';
        return;
    }

    if (!hasFirebaseConfig.value) {
        error.value = 'Firebase is not configured yet. Add the Firebase keys to .env and clear Laravel config.';
        return;
    }

    loading.value = true;

    try {
        const { auth, sendPasswordResetEmail } = await loadFirebaseAuth();
        await sendPasswordResetEmail(auth, email.value);
        sent.value = 'Password reset email sent. Check your inbox.';
    } catch (firebaseError) {
        error.value = firebaseError?.code === 'auth/user-not-found'
            ? 'No Firebase account exists for this email.'
            : `Firebase could not send the reset email: ${firebaseError?.code || firebaseError?.message || 'Unknown error'}`;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mx-auto max-w-xl overflow-hidden rounded-2xl border border-[#8BED9A]/45 bg-white/90 shadow-2xl shadow-[#1e2924]/10 backdrop-blur">
            <div class="border-b border-[#8BED9A]/35 bg-[#8BED9A]/12 p-7">
                <Link href="/login" class="inline-flex items-center gap-2 text-sm font-bold text-[#1e2924] hover:text-[#09B884]">
                    <ArrowLeft class="h-4 w-4" />
                    Back to sign in
                </Link>
                <p class="mt-7 text-xs font-black uppercase tracking-[0.24em] text-[#09B884]">Firebase recovery</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-[#1e2924]">Reset your password</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Enter your account email and Firebase will send a secure password reset link.
                </p>
            </div>

            <form class="space-y-5 p-7" @submit.prevent="submit">
                <div v-if="sent" class="rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-3 py-2 text-sm font-semibold text-[#1e2924]">
                    {{ sent }}
                </div>

                <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                    {{ error }}
                </div>

                <div>
                    <label for="email" class="section-title">Email</label>
                    <div class="relative mt-1">
                        <Mail class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            class="field-control w-full pl-9"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="you@school.edu"
                        />
                    </div>
                    <InputError class="mt-1" :message="error" />
                </div>

                <button type="submit" class="btn-primary min-h-12 w-full text-base" :disabled="loading">
                    {{ loading ? 'Sending reset email...' : 'Send reset email' }}
                    <Send class="h-4 w-4" />
                </button>
            </form>
        </div>
    </GuestLayout>
</template>
