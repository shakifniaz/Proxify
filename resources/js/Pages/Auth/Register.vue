<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
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
import InputError from '@/Components/InputError.vue';

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
});

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

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div
            class="grid min-h-0 overflow-hidden rounded-2xl border border-[#8BED9A]/45 bg-white/86 shadow-2xl shadow-[#1e2924]/10 backdrop-blur xl:grid-cols-[minmax(0,1fr)_31rem]"
            style="height: min(44rem, calc(100vh - 9.5rem));"
        >
            <section class="relative hidden h-full overflow-hidden bg-[#1e2924] p-8 text-white xl:block">
                <div class="pointer-events-none absolute -left-24 -top-20 h-72 w-72 rounded-full bg-[#8BED9A]/22 blur-3xl"></div>
                <div class="pointer-events-none absolute -right-16 bottom-12 h-80 w-80 rounded-full bg-[#09B884]/20 blur-3xl"></div>

                <div class="relative flex h-full flex-col justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-bold text-[#BDF8C8]">
                            <BookOpen class="h-3.5 w-3.5" />
                            Join the school workspace
                        </div>
                        <h1 class="mt-8 max-w-xl text-5xl font-black leading-tight tracking-tight">
                            Role-based access for the whole institute.
                        </h1>
                        <p class="mt-4 max-w-lg text-sm leading-6 text-[#D8FFE0]/75">
                            Admins create the institution, then teachers and students join through secure codes generated inside Campulse.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/8 p-5">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-xl border border-white/10 bg-white/8 p-4">
                                <Building2 class="h-5 w-5 text-[#8BED9A]" />
                                <p class="mt-6 text-sm font-bold">Admin</p>
                                <p class="mt-1 text-xs text-[#D8FFE0]/65">Institution setup</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-white/8 p-4">
                                <Users class="h-5 w-5 text-[#8BED9A]" />
                                <p class="mt-6 text-sm font-bold">Teacher</p>
                                <p class="mt-1 text-xs text-[#D8FFE0]/65">Academic tools</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-white/8 p-4">
                                <GraduationCap class="h-5 w-5 text-[#8BED9A]" />
                                <p class="mt-6 text-sm font-bold">Student</p>
                                <p class="mt-1 text-xs text-[#D8FFE0]/65">Student portal</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex min-h-0 flex-col overflow-hidden">
                <div class="min-h-0 flex-1 overflow-y-auto p-6 sm:p-8">
                    <div class="mb-7">
                        <p class="text-xs font-black uppercase tracking-[0.24em] text-[#09B884]">Create access</p>
                        <h2 class="mt-3 text-3xl font-black tracking-tight text-[#1e2924]">Register</h2>
                        <p class="mt-2 text-sm text-slate-500">Set up your institute account.</p>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="role in roles"
                            :key="role.key"
                            type="button"
                            class="rounded-xl border p-3 text-left transition-all"
                            :class="form.role === role.key ? 'border-[#09B884] bg-[#8BED9A]/20 shadow-sm shadow-[#8BED9A]/30' : 'border-stone-200 bg-white hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10'"
                            @click="form.role = role.key"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-lg border"
                                    :class="form.role === role.key ? 'border-[#8BED9A]/70 bg-white text-[#09B884]' : 'border-stone-200 bg-stone-50 text-slate-500'"
                                >
                                    <component :is="role.icon" class="h-4 w-4" />
                                </span>
                                <span class="text-sm font-black text-[#1e2924]">{{ role.label }}</span>
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">{{ role.line }}</p>
                        </button>
                    </div>
                    <InputError class="-mt-2" :message="form.errors.role" />

                    <div v-if="form.role === 'admin'" class="rounded-xl border border-[#8BED9A]/55 bg-[#8BED9A]/10 p-4">
                        <div class="flex items-center gap-2">
                            <Building2 class="h-4 w-4 text-[#09B884]" />
                            <p class="text-sm font-black text-[#1e2924]">Institution setup</p>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="section-title">Institution name</label>
                                <input v-model="form.institution_name" type="text" class="field-control mt-1 w-full" placeholder="Metropolitan School" />
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

                    <div v-if="form.role !== 'admin'" class="rounded-xl border border-stone-200 bg-stone-50/70 p-4">
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
                            <input id="name" v-model="form.name" type="text" class="field-control w-full pl-9" required autofocus autocomplete="name" placeholder="Your full name" />
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

                    <button type="submit" class="btn-primary min-h-12 w-full text-base" :disabled="form.processing">
                        Create account
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
