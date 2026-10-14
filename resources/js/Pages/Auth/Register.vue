<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';
import VCheckbox from '@/components/VCheckbox.vue';
import VInput from '@/components/VInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout title="Registrieren">
        <form @submit.prevent="submit" class="space-y-4">
            <VInput id="name" label="Name" v-model="form.name" :error="form.errors.name" autofocus autocomplete="name" />

            <VInput id="email" type="email" label="E-Mail" v-model="form.email" :error="form.errors.email" autocomplete="username" />

            <VInput id="password" type="password" label="Passwort" v-model="form.password" :error="form.errors.password" autocomplete="new-password" />

            <VInput id="password_confirmation" type="password" label="Passwort bestätigen" v-model="form.password_confirmation" :error="form.errors.password_confirmation" autocomplete="new-password" />

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature">
                <VCheckbox v-model="form.terms" name="terms">
                    Ich akzeptiere die
                    <a target="_blank" :href="route('terms.show')" class="underline hover:text-primary-300">Nutzungsbedingungen</a>
                    und die
                    <a target="_blank" :href="route('policy.show')" class="underline hover:text-primary-300">Datenschutzerklärung</a>
                </VCheckbox>
                <div v-if="form.errors.terms" class="text-xs mt-1 text-red-400">{{ form.errors.terms }}</div>
            </div>

            <div class="flex items-center justify-end gap-4 pt-2">
                <Link :href="route('login')" class="text-sm text-gray-400 underline hover:text-primary-300">Schon registriert?</Link>

                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Registrieren</VButton>
            </div>
        </form>
    </AuthLayout>
</template>
