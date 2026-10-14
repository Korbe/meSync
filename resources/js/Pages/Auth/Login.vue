<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';
import VCheckbox from '@/components/VCheckbox.vue';
import VInput from '@/components/VInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout title="Anmelden">
        <div v-if="status" class="mb-4 font-medium text-sm text-primary-300">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <VInput id="email" type="email" label="E-Mail" v-model="form.email" :error="form.errors.email" autofocus autocomplete="username" />

            <VInput id="password" type="password" label="Passwort" v-model="form.password" :error="form.errors.password" autocomplete="current-password" />

            <VCheckbox v-model="form.remember" name="remember" label="Angemeldet bleiben" />

            <div class="flex items-center justify-end gap-4 pt-2">
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-gray-400 underline hover:text-primary-300">
                    Passwort vergessen?
                </Link>

                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Anmelden</VButton>
            </div>
        </form>
    </AuthLayout>
</template>
