<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';
import VCheckbox from '@/components/VCheckbox.vue';
import VInput from '@/components/VInput.vue';

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    // Address of the last sign-in on this device
    lastEmail: String,
});

const form = useForm({
    email: props.lastEmail ?? '',
    password: '',
    remember: false,
});

const demoLoading = ref(false);

const demoLogin = () => {
    demoLoading.value = true;
    router.post(route('demo.login'), {}, { onFinish: () => demoLoading.value = false });
};

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
            <VInput id="email" type="email" label="E-Mail" v-model="form.email" :error="form.errors.email" :autofocus="!lastEmail" autocomplete="username" />

            <VInput id="password" type="password" label="Passwort" v-model="form.password" :error="form.errors.password" :autofocus="!!lastEmail" autocomplete="current-password" />

            <VCheckbox v-model="form.remember" name="remember" label="Angemeldet bleiben" />

            <div class="flex items-center justify-end gap-4 pt-2">
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-gray-400 underline hover:text-primary-300">
                    Passwort vergessen?
                </Link>

                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Anmelden</VButton>
            </div>
        </form>

        <div class="mt-6 border-t border-white/10 pt-5">
            <button type="button" :disabled="demoLoading"
                class="group flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-accent/50 bg-accent/[0.06] px-4 py-2.5 text-sm font-medium text-accent transition hover:border-accent hover:bg-accent/[0.12] disabled:cursor-wait disabled:opacity-60"
                @click="demoLogin">
                <svg v-if="demoLoading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="4" /><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round" /></svg>
                {{ demoLoading ? 'Demo wird vorbereitet …' : 'Mit Demo-Benutzer starten' }}
            </button>
            <p class="mt-2 text-center text-xs text-gray-500">Ohne Passwort ausprobieren – mit Beispieldaten.</p>
        </div>
    </AuthLayout>
</template>
