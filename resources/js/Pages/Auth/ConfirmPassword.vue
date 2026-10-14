<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';

const form = useForm({
    password: '',
});

const passwordInput = ref(null);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();

            passwordInput.value.focus();
        },
    });
};
</script>

<template>
    <AuthLayout title="Geschützter Bereich">
        <div class="mb-4 text-sm text-gray-400">
            Das ist ein geschützter Bereich. Bitte bestätige dein Passwort, um fortzufahren.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <VInput id="password" ref="passwordInput" type="password" label="Passwort" v-model="form.password" :error="form.errors.password" autofocus autocomplete="current-password" />

            <div class="flex justify-end pt-2">
                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Bestätigen</VButton>
            </div>
        </form>
    </AuthLayout>
</template>
