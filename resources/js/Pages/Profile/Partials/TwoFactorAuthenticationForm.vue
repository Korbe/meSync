<script setup>
import { ShieldCheckIcon } from '@heroicons/vue/24/outline';
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import VButton from '@/components/VButton.vue';
import VActionSection from '@/components/VActionSection.vue';
import VConfirmsPassword from '@/components/VConfirmsPassword.vue';
import VInput from '@/components/VInput.vue';

const props = defineProps({
    requiresConfirmation: Boolean,
});

const page = usePage();
const enabling = ref(false);
const confirming = ref(false);
const disabling = ref(false);
const qrCode = ref(null);
const setupKey = ref(null);
const recoveryCodes = ref([]);

const confirmationForm = useForm({
    code: '',
});

const twoFactorEnabled = computed(
    () => ! enabling.value && page.props.auth.user?.two_factor_enabled,
);

watch(twoFactorEnabled, () => {
    if (! twoFactorEnabled.value) {
        confirmationForm.reset();
        confirmationForm.clearErrors();
    }
});

const enableTwoFactorAuthentication = () => {
    enabling.value = true;

    router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: () => Promise.all([
            showQrCode(),
            showSetupKey(),
            showRecoveryCodes(),
        ]),
        onFinish: () => {
            enabling.value = false;
            confirming.value = props.requiresConfirmation;
        },
    });
};

const showQrCode = () => {
    return axios.get(route('two-factor.qr-code')).then(response => {
        qrCode.value = response.data.svg;
    });
};

const showSetupKey = () => {
    return axios.get(route('two-factor.secret-key')).then(response => {
        setupKey.value = response.data.secretKey;
    });
}

const showRecoveryCodes = () => {
    return axios.get(route('two-factor.recovery-codes')).then(response => {
        recoveryCodes.value = response.data;
    });
};

const confirmTwoFactorAuthentication = () => {
    confirmationForm.post(route('two-factor.confirm'), {
        errorBag: "confirmTwoFactorAuthentication",
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirming.value = false;
            qrCode.value = null;
            setupKey.value = null;
        },
    });
};

const regenerateRecoveryCodes = () => {
    axios
        .post(route('two-factor.recovery-codes'))
        .then(() => showRecoveryCodes());
};

const disableTwoFactorAuthentication = () => {
    disabling.value = true;

    router.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            disabling.value = false;
            confirming.value = false;
        },
    });
};
</script>

<template>
    <VActionSection>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="card-title"><ShieldCheckIcon class="h-5 w-5 text-primary-500" />Zwei-Faktor-Authentifizierung</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Zusätzliche Sicherheit mit einem Code aus deiner Authenticator-App.</p>
                </div>

                <span v-if="twoFactorEnabled && ! confirming" class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/10 px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-500/30 dark:text-primary-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>Aktiv
                </span>
                <span v-else-if="twoFactorEnabled && confirming" class="inline-flex items-center gap-1.5 rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-accent/40 dark:text-accent">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>Einrichtung läuft
                </span>
                <span v-else class="inline-flex items-center gap-1.5 rounded-full bg-gray-500/10 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/25 dark:text-gray-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>Nicht aktiv
                </span>
            </div>
        </template>

        <template #content>
            <p class="mt-4 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                Wenn die Zwei-Faktor-Authentifizierung aktiv ist, wirst du beim Anmelden zusätzlich nach einem Code gefragt. Den Code bekommst du aus einer Authenticator-App wie Google Authenticator oder 1Password.
            </p>

            <div v-if="twoFactorEnabled">
                <div v-if="qrCode" class="mt-5 flex flex-col gap-5 rounded-xl border border-gray-200 bg-gray-50/60 p-5 sm:flex-row dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="shrink-0 self-start rounded-xl bg-white p-3 shadow-[0_0_30px_-8px_rgba(31,173,174,0.6)]" v-html="qrCode" />

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <p v-if="confirming" class="font-medium text-gray-900 dark:text-gray-100">
                            Scanne den QR-Code mit deiner Authenticator-App oder gib den Schlüssel manuell ein und bestätige mit dem erzeugten Code.
                        </p>
                        <p v-else>
                            Die Zwei-Faktor-Authentifizierung ist aktiv. Scanne den QR-Code mit deiner Authenticator-App oder gib den Schlüssel manuell ein.
                        </p>

                        <p v-if="setupKey" class="mt-3">
                            Schlüssel: <span class="font-mono text-gray-900 dark:text-gray-100" v-html="setupKey"></span>
                        </p>

                        <div v-if="confirming" class="mt-4 max-w-xs">
                            <VInput id="code" label="Code" v-model="confirmationForm.code" :error="confirmationForm.errors.code" />
                        </div>
                    </div>
                </div>

                <div v-if="recoveryCodes.length > 0 && ! confirming" class="mt-5">
                    <p class="max-w-2xl text-sm font-medium text-gray-900 dark:text-gray-100">
                        Bewahre diese Wiederherstellungscodes sicher auf, z. B. in einem Passwort-Manager. Damit kommst du in dein Konto, falls du dein Handy verlierst.
                    </p>

                    <div class="mt-3 grid max-w-2xl grid-cols-2 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 font-mono text-sm text-gray-800 dark:border-white/10 dark:bg-gray-950/60 dark:text-gray-200">
                        <div v-for="code in recoveryCodes" :key="code">{{ code }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <template v-if="! twoFactorEnabled">
                    <VConfirmsPassword @confirmed="enableTwoFactorAuthentication">
                        <VButton type="button" :disabled="enabling">Aktivieren</VButton>
                    </VConfirmsPassword>
                </template>

                <template v-else>
                    <VConfirmsPassword @confirmed="confirmTwoFactorAuthentication">
                        <VButton v-if="confirming" type="button" :disabled="enabling">Bestätigen</VButton>
                    </VConfirmsPassword>

                    <VConfirmsPassword @confirmed="regenerateRecoveryCodes">
                        <VButton variant="secondary" v-if="recoveryCodes.length > 0 && ! confirming">Neue Wiederherstellungscodes</VButton>
                    </VConfirmsPassword>

                    <VConfirmsPassword @confirmed="showRecoveryCodes">
                        <VButton variant="secondary" v-if="recoveryCodes.length === 0 && ! confirming">Wiederherstellungscodes anzeigen</VButton>
                    </VConfirmsPassword>

                    <VConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <VButton variant="secondary" v-if="confirming" :disabled="disabling">Abbrechen</VButton>
                    </VConfirmsPassword>

                    <VConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <VButton variant="danger" v-if="! confirming" :disabled="disabling">Deaktivieren</VButton>
                    </VConfirmsPassword>
                </template>
            </div>
        </template>
    </VActionSection>
</template>
