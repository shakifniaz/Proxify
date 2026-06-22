<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mb-6 text-center">
            <h1 class="text-lg font-semibold text-white">Create your account</h1>
            <p class="mt-1 text-sm text-slate-500">Set up your Proxify teacher account</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Full Name" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-1" required autofocus autocomplete="name" />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="phone" value="Phone (WhatsApp)" />
                <TextInput
                    id="phone"
                    v-model="form.phone"
                    type="text"
                    placeholder="+8801XXXXXXXXX"
                    class="mt-1"
                    required
                    autocomplete="tel"
                />
                <InputError class="mt-1" :message="form.errors.phone" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1" required autocomplete="username" />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />
                <TextInput id="password" v-model="form.password" type="password" class="mt-1" required autocomplete="new-password" />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm Password" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-start gap-2 rounded-lg border border-slate-800 bg-slate-800/40 px-3 py-2.5 text-xs text-slate-400">
                <Info class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-500" />
                Your account starts with Teacher access. An admin can grant you Admin permissions later from the Teachers page.
            </div>

            <PrimaryButton class="w-full" :disabled="form.processing">
                Create account
            </PrimaryButton>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Already have an account?
            <Link href="/login" class="font-medium text-emerald-400 hover:text-emerald-300">Sign in</Link>
        </p>
    </GuestLayout>
</template>