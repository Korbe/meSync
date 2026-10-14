<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout title="Passwort zurücksetzen">
        <form @submit.prevent="submit" class="space-y-4">
            <VInput id="email" type="email" label="E-Mail" v-model="form.email" :error="form.errors.email" autocomplete="username" />

            <VInput id="password" type="password" label="Neues Passwort" v-model="form.password" :error="form.errors.password" autofocus autocomplete="new-password" />

            <VInput id="password_confirmation" type="password" label="Passwort bestätigen" v-model="form.password_confirmation" :error="form.errors.password_confirmation" autocomplete="new-password" />

            <div class="flex justify-end pt-2">
                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Passwort speichern</VButton>
            </div>
        </form>
    </AuthLayout>
</template>
