<script setup>
import { computed, onMounted, ref } from 'vue';
import { ClockIcon, LockClosedIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline';
import VButton from '@/components/VButton.vue';
import VModal from '@/components/VModal.vue';
import { useSessionTimeout } from '@/composables/useSessionTimeout';

// Popup before and after the end of the session (see useSessionTimeout)
const { state, remaining, checking, enabled, start, extend, logout, signIn, savedForm, restoreSavedForm, discardSavedForm } = useSessionTimeout();

const countdown = computed(() => {
    const minutes = Math.floor(remaining.value / 60);
    const seconds = String(remaining.value % 60).padStart(2, '0');

    return `${minutes}:${seconds}`;
});

// Entries kept before signing in again: offered once on the same page
const restorable = ref(false);
const restoredCount = ref(null);

const restore = () => {
    restoredCount.value = restoreSavedForm();
    restorable.value = false;
    setTimeout(() => restoredCount.value = null, 4000);
};

const discard = () => {
    discardSavedForm();
    restorable.value = false;
};

onMounted(() => {
    if (enabled.value) start();
    // Wait for the page's own fields to render
    setTimeout(() => restorable.value = !!savedForm(), 300);
});
</script>

<template>
    <!-- Session ends soon -->
    <VModal :show="enabled && state === 'warning'" max-width="md" :closeable="false">
        <div class="relative overflow-hidden px-6 pt-6 pb-4">
            <div class="pointer-events-none absolute -top-16 -right-16 h-48 w-48 rounded-full bg-accent/20 blur-[70px]" aria-hidden="true"></div>
            <div class="relative flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent/15 ring-1 ring-accent/40">
                    <ClockIcon class="h-6 w-6 text-accent" />
                </span>
                <div class="min-w-0">
                    <div class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white">Deine Sitzung läuft bald ab</div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Du warst eine Weile nicht aktiv. Aus Sicherheitsgründen wirst du automatisch abgemeldet in</p>
                    <div class="mt-3 font-display text-4xl font-bold tabular-nums text-accent" aria-live="polite">{{ countdown }}</div>
                </div>
            </div>
        </div>
        <div class="flex flex-row justify-end gap-3 border-t border-gray-200 bg-gray-50/80 px-6 py-4 dark:border-white/[0.06] dark:bg-white/[0.02]">
            <VButton variant="secondary" @click="logout">Abmelden</VButton>
            <VButton :loading="checking" :disabled="checking" @click="extend">Angemeldet bleiben</VButton>
        </div>
    </VModal>

    <!-- Session ended -->
    <VModal :show="enabled && state === 'expired'" max-width="md" :closeable="false">
        <div class="px-6 pt-6 pb-4">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-500/15 ring-1 ring-primary-500/40">
                    <LockClosedIcon class="h-6 w-6 text-primary-500" />
                </span>
                <div class="min-w-0">
                    <div class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white">Sitzung abgelaufen</div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Du wurdest abgemeldet, weil du länger nicht aktiv warst. Melde dich neu an – danach geht es auf dieser Seite weiter.</p>
                    <p class="mt-2 text-xs text-gray-500">Ungespeicherte Eingaben auf dieser Seite werden gesichert und können nach der Anmeldung wiederhergestellt werden.</p>
                </div>
            </div>
        </div>
        <div class="flex flex-row justify-end gap-3 border-t border-gray-200 bg-gray-50/80 px-6 py-4 dark:border-white/[0.06] dark:bg-white/[0.02]">
            <VButton icon="ArrowRightEndOnRectangleIcon" @click="signIn">Neu anmelden</VButton>
        </div>
    </VModal>

    <!-- Entries kept from before signing in again -->
    <transition enter-active-class="transition duration-200" enter-from-class="translate-y-4 opacity-0" leave-active-class="transition duration-150" leave-to-class="translate-y-4 opacity-0">
        <div v-if="restorable || restoredCount !== null" class="fixed right-4 bottom-4 left-4 z-40 sm:left-auto sm:w-96">
            <div class="card flex items-start gap-3 p-4 shadow-2xl ring-1 ring-primary-500/30">
                <ArrowUturnLeftIcon class="mt-0.5 h-5 w-5 shrink-0 text-primary-500" />
                <div v-if="restoredCount !== null" class="text-sm text-gray-700 dark:text-gray-200">
                    {{ restoredCount ? `${restoredCount} Eingaben wiederhergestellt – bitte prüfen und speichern.` : 'Die Felder gibt es auf dieser Seite nicht mehr.' }}
                </div>
                <div v-else class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">Ungespeicherte Eingaben gefunden</div>
                    <p class="mt-0.5 text-xs text-gray-500">Vor dem Ablauf der Sitzung gesichert. Wiederherstellen?</p>
                    <div class="mt-3 flex gap-2">
                        <VButton class="py-1! text-xs!" @click="restore">Wiederherstellen</VButton>
                        <VButton class="py-1! text-xs!" variant="secondary" @click="discard">Verwerfen</VButton>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>
