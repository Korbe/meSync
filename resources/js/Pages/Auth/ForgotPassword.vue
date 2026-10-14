<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout title="Passwort vergessen">
        <div class="mb-4 text-sm text-gray-400">
            Passwort vergessen? Kein Problem. Gib deine E-Mail-Adresse ein, dann schicken wir dir einen Link, mit dem du ein neues Passwort festlegen kannst.
        </div>

        <div v-if="status" class="mb-4 font-medium text-sm text-primary-300">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <VInput id="email" type="email" label="E-Mail" v-model="form.email" :error="form.errors.email" autofocus autocomplete="username" />

            <div class="flex items-center justify-end gap-4 pt-2">
                <Link :href="route('login')" class="text-sm text-gray-400 underline hover:text-primary-300">Zurück zur Anmeldung</Link>

                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Link senden</VButton>
            </div>
        </form>
    </AuthLayout>
</template>
