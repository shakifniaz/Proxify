<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div
            class="grid min-h-0 overflow-hidden rounded-2xl border border-[#8BED9A]/45 bg-white/86 shadow-2xl shadow-[#1e2924]/10 backdrop-blur xl:grid-cols-[minmax(0,1fr)_29rem]"
            style="height: min(38rem, calc(100vh - 9.5rem));"
        >
            <section class="relative hidden h-full overflow-hidden bg-[#1e2924] p-8 text-white xl:block">
                <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-[#8BED9A]/25 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-10 left-10 h-52 w-52 rounded-full bg-[#09B884]/20 blur-3xl"></div>

                <div class="relative flex h-full flex-col justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-bold text-[#BDF8C8]">
                            <ShieldCheck class="h-3.5 w-3.5" />
                            Institute command center
                        </div>
                        <h1 class="mt-8 max-w-xl text-5xl font-black leading-tight tracking-tight">
                            One workspace for academic operations.
                        </h1>
                        <p class="mt-4 max-w-lg text-sm leading-6 text-[#D8FFE0]/75">
                            Sign in to manage schedules, leave, notices, classrooms, and daily coordination from a single polished system.
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-white/10 bg-white/8 p-4">
                            <CalendarDays class="h-5 w-5 text-[#8BED9A]" />
                            <p class="mt-5 text-sm font-bold">Scheduling</p>
                            <p class="mt-1 text-xs text-[#D8FFE0]/65">Routine planning</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/8 p-4">
                            <Megaphone class="h-5 w-5 text-[#8BED9A]" />
                            <p class="mt-5 text-sm font-bold">Notices</p>
                            <p class="mt-1 text-xs text-[#D8FFE0]/65">Institution wide</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/8 p-4">
                            <ShieldCheck class="h-5 w-5 text-[#8BED9A]" />
                            <p class="mt-5 text-sm font-bold">People</p>
                            <p class="mt-1 text-xs text-[#D8FFE0]/65">Role based access</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex min-h-0 flex-col justify-center overflow-hidden p-6 sm:p-8">
                <div class="mb-8">
                    <p class="text-xs font-black uppercase tracking-[0.24em] text-[#09B884]">Welcome back</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-[#1e2924]">Sign in</h2>
                    <p class="mt-2 text-sm text-slate-500">Open your institute workspace.</p>
                </div>

                <div v-if="status" class="mb-4 rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-3 py-2 text-sm font-semibold text-[#1e2924]">
                    {{ status }}
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
                                autofocus
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

                    <div class="flex items-center justify-between gap-3">
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

                    <button type="submit" class="btn-primary min-h-12 w-full text-base" :disabled="form.processing">
                        Sign in
                        <ArrowRight class="h-4 w-4" />
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    New to Campulse?
                    <Link href="/register" class="font-bold text-[#1e2924] hover:text-[#09B884]">Create account</Link>
                </p>
            </section>
        </div>
    </GuestLayout>
</template>
