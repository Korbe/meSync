<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <AuthLayout title="E-Mail bestätigen">
        <div class="mb-4 text-sm text-gray-400">
            Bevor es losgeht: Bitte bestätige deine E-Mail-Adresse über den Link, den wir dir gerade geschickt haben. Keine Mail bekommen? Dann schicken wir dir gerne eine neue.
        </div>

        <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-primary-300">
            Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between gap-4">
                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Erneut senden</VButton>

                <div class="flex gap-3">
                    <Link :href="route('profile.show')" class="text-sm text-gray-400 underline hover:text-primary-300">Profil</Link>

                    <Link :href="route('logout')" method="post" as="button" class="cursor-pointer text-sm text-gray-400 underline hover:text-primary-300">Abmelden</Link>
                </div>
            </div>
        </form>
    </AuthLayout>
</template>
