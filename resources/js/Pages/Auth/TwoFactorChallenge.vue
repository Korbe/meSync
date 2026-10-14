<script setup>
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import VButton from '@/components/VButton.vue';
import VInput from '@/components/VInput.vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post(route('two-factor.login'));
};
</script>

<template>
    <AuthLayout title="Zwei-Faktor-Bestätigung">
        <div class="mb-4 text-sm text-gray-400">
            <template v-if="! recovery">
                Bitte gib den Code aus deiner Authenticator-App ein.
            </template>

            <template v-else>
                Bitte gib einen deiner Wiederherstellungscodes ein.
            </template>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <VInput v-if="! recovery" id="code" ref="codeInput" label="Code" v-model="form.code" :error="form.errors.code" inputmode="numeric" autofocus autocomplete="one-time-code" />

            <VInput v-else id="recovery_code" ref="recoveryCodeInput" label="Wiederherstellungscode" v-model="form.recovery_code" :error="form.errors.recovery_code" autocomplete="one-time-code" />

            <div class="flex items-center justify-end gap-4 pt-2">
                <button type="button" class="cursor-pointer text-sm text-gray-400 underline hover:text-primary-300" @click.prevent="toggleRecovery">
                    {{ recovery ? 'Code aus der App verwenden' : 'Wiederherstellungscode verwenden' }}
                </button>

                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Anmelden</VButton>
            </div>
        </form>
    </AuthLayout>
</template>
