<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowDownTrayIcon, ArrowUpOnSquareIcon, PlusCircleIcon } from '@heroicons/vue/24/outline';
import VModal from '@/components/VModal.vue';
import VButton from '@/components/VButton.vue';

/*
 * "Install as app" button. Chrome/Edge/Android offer a real install prompt; Safari on
 * iPhone/iPad has none, so it explains "Share → Add to Home Screen" instead.
 * Hidden when meSync already runs as installed app.
 */

const installable = ref(false);
const showIosHint = ref(false);

const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const update = () => installable.value = !standalone && !!window.__pwaInstallPrompt;
const installed = () => {
    window.__pwaInstallPrompt = null;
    installable.value = false;
};

onMounted(() => {
    update();
    window.addEventListener('pwa-installable', update);
    window.addEventListener('appinstalled', installed);
});

onBeforeUnmount(() => {
    window.removeEventListener('pwa-installable', update);
    window.removeEventListener('appinstalled', installed);
});

const install = async () => {
    const prompt = window.__pwaInstallPrompt;
    if (!prompt) return;

    prompt.prompt();
    const { outcome } = await prompt.userChoice;
    if (outcome === 'accepted') installed();
};
</script>

<template>
    <button v-if="installable" type="button" class="glass-button" title="meSync als App installieren" @click="install">
        <ArrowDownTrayIcon class="h-5 w-5" />
        <span class="sr-only">App installieren</span>
    </button>
    <button v-else-if="isIos && !standalone" type="button" class="glass-button" title="meSync zum Home-Bildschirm hinzufügen" @click="showIosHint = true">
        <ArrowDownTrayIcon class="h-5 w-5" />
        <span class="sr-only">App installieren</span>
    </button>

    <VModal :show="showIosHint" max-width="sm" @close="showIosHint = false">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <img src="/images/pwa/icon-192.png" alt="" class="h-12 w-12 rounded-xl">
                <div>
                    <h3 class="font-display text-lg font-semibold text-gray-900 dark:text-white">meSync als App</h3>
                    <p class="text-sm text-gray-500">Auf dem Home-Bildschirm, ohne Browserleiste.</p>
                </div>
            </div>
            <ol class="mt-5 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                <li class="flex items-center gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-300"><ArrowUpOnSquareIcon class="h-4 w-4" /></span>
                    In Safari unten auf <strong>Teilen</strong> tippen
                </li>
                <li class="flex items-center gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-300"><PlusCircleIcon class="h-4 w-4" /></span>
                    <span><strong>Zum Home-Bildschirm</strong> wählen und bestätigen</span>
                </li>
            </ol>
            <div class="mt-6 flex justify-end">
                <VButton @click="showIosHint = false">Verstanden</VButton>
            </div>
        </div>
    </VModal>
</template>
