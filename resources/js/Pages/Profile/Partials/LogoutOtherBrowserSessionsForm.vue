<script setup>
import { ComputerDesktopIcon, DevicePhoneMobileIcon, GlobeEuropeAfricaIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import VButton from '@/components/VButton.vue';
import VActionMessage from '@/components/VActionMessage.vue';
import VActionSection from '@/components/VActionSection.vue';
import VModal from '@/components/VModal.vue';
import VInput from '@/components/VInput.vue';

defineProps({
    sessions: Array,
});

const confirmingLogout = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmLogout = () => {
    confirmingLogout.value = true;

    setTimeout(() => passwordInput.value.focus(), 250);
};

const logoutOtherBrowserSessions = () => {
    form.delete(route('other-browser-sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingLogout.value = false;

    form.reset();
};
</script>

<template>
    <VActionSection>
        <template #header>
            <h2 class="card-title"><GlobeEuropeAfricaIcon class="h-5 w-5 text-primary-500" />Browser-Sitzungen</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Verwalte deine aktiven Sitzungen auf anderen Browsern und Geräten.</p>
        </template>

        <template #content>
            <p class="mt-4 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                Bei Bedarf kannst du dich auf allen anderen Geräten abmelden. Falls du glaubst, dass jemand Zugriff auf dein Konto hat, ändere zusätzlich dein Passwort.
            </p>

            <ul v-if="sessions.length > 0" class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-white/[0.06] dark:border-white/10">
                <li v-for="(session, i) in sessions" :key="i" class="flex items-center gap-3 px-4 py-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1"
                        :class="session.is_current_device ? 'bg-primary-500/10 text-primary-500 ring-primary-500/30' : 'bg-gray-500/10 text-gray-500 ring-gray-500/20'">
                        <ComputerDesktopIcon v-if="session.agent.is_desktop" class="h-5 w-5" />
                        <DevicePhoneMobileIcon v-else class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ session.agent.platform || 'Unbekannt' }} · {{ session.agent.browser || 'Unbekannt' }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ session.ip_address }} ·
                            <span v-if="session.is_current_device" class="font-semibold text-primary-600 dark:text-primary-300">Dieses Gerät</span>
                            <span v-else>Zuletzt aktiv {{ session.last_active }}</span>
                        </div>
                    </div>
                </li>
            </ul>

            <div class="mt-5 flex items-center gap-3">
                <VButton variant="secondary" @click="confirmLogout">Andere Sitzungen abmelden</VButton>

                <VActionMessage :on="form.recentlySuccessful">Erledigt.</VActionMessage>
            </div>

            <VModal :show="confirmingLogout" @close="closeModal">
                <template #title>Andere Sitzungen abmelden</template>

                <template #content>
                    Bitte gib dein Passwort ein, um dich auf allen anderen Browsern und Geräten abzumelden.

                    <div class="mt-4">
                        <VInput class="mt-1 sm:w-3/4" ref="passwordInput" v-model="form.password" type="password" placeholder="Passwort" :error="form.errors.password" />
                    </div>
                </template>

                <template #footer>
                    <VButton variant="secondary" @click="closeModal">Abbrechen</VButton>

                    <VButton :disabled="form.processing" @click="logoutOtherBrowserSessions">Abmelden</VButton>
                </template>
            </VModal>
        </template>
    </VActionSection>
</template>
