<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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

        <div class="mb-6 text-center">
            <h1 class="text-lg font-semibold text-slate-950">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-500">Sign in to your Proxify account</p>
        </div>

        <div v-if="status" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
            {{ status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="pr-10"
                        required
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-800"
                        @click="showPassword = !showPassword"
                    >
                        <component :is="showPassword ? EyeOff : Eye" class="h-4 w-4" />
                    </button>
                </div>
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <Checkbox v-model:checked="form.remember" />
                    Remember me
                </label>

                <Link
                    v-if="canResetPassword"
                    href="/forgot-password"
                    class="text-sm font-medium text-blue-700 hover:text-blue-900"
                >
                    Forgot your password?
                </Link>
            </div>

            <PrimaryButton class="w-full" :disabled="form.processing">
                Sign in
            </PrimaryButton>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Don't have an account?
            <Link href="/register" class="font-medium text-blue-700 hover:text-blue-900">Register</Link>
        </p>
    </GuestLayout>
</template>
