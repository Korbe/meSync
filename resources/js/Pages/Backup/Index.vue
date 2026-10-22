<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, ArrowUpTrayIcon, CircleStackIcon, DocumentArrowUpIcon, InformationCircleIcon, PlusCircleIcon, ArrowPathIcon, CheckIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import { confirm } from '@/composables/useConfirm';

defineProps({
    counts: Object,
});

const form = useForm({
    file: null,
    mode: 'append',
});

const fileInput = ref(null);
const dragging = ref(false);

const modes = [
    { value: 'append', icon: PlusCircleIcon, title: 'Anhängen', text: 'Bestehende Daten bleiben erhalten, das Backup wird dazugefügt. Rechnungen mit bereits vorhandener Nummer werden übersprungen.' },
    { value: 'replace', icon: ArrowPathIcon, title: 'Alles ersetzen', text: 'Alle Kunden, Verträge, Rechnungen, Angebote, Katalogartikel, das Inventar, die Finanzen und die Firmendaten werden vorher gelöscht und durch das Backup ersetzt.' },
];

const select = (file) => {
    if (!file) return;
    form.file = file;
    form.clearErrors();
};

const onDrop = (event) => {
    dragging.value = false;
    select(event.dataTransfer.files[0]);
};

const formatSize = (bytes) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

const restore = async () => {
    const replace = form.mode === 'replace';

    if (!(await confirm(replace ? {
        title: 'Alle Daten ersetzen?',
        message: 'Alle aktuellen Kunden, Verträge, Rechnungen, Angebote, Katalogartikel, das Inventar, die Finanzen und die Firmendaten werden gelöscht und durch das Backup ersetzt. Das lässt sich nicht rückgängig machen – lade vorher am besten ein aktuelles Backup herunter.',
        confirmText: 'Löschen und wiederherstellen',
        variant: 'danger',
    } : {
        title: 'Backup anhängen?',
        message: `Die Daten aus „${form.file.name}“ werden zu deinen bestehenden Daten hinzugefügt.`,
        confirmText: 'Backup anhängen',
    }))) return;

    form.post(route('backup.restore'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file');
            if (fileInput.value) fileInput.value.value = null;
        },
    });
};
</script>

<template>
    <Layout title="Backup">
        <p class="-mt-4 mb-6 max-w-2xl text-gray-500">Sichere alle deine Daten mit einem Klick als Datei und spiele sie bei Bedarf wieder ein.</p>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <!-- Download -->
            <section class="card relative overflow-hidden p-6 xl:col-span-2">
                <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="card-title"><CircleStackIcon class="h-5 w-5 text-primary-500" />Backup herunterladen</h2>
                    <p class="mt-1 text-sm text-gray-500">Eine JSON-Datei mit allem, was du in meSync gespeichert hast.</p>

                    <dl class="mt-5 grid grid-cols-2 gap-3">
                        <div v-for="item in [
                            { label: 'Kunden', value: counts.customers },
                            { label: 'Verträge', value: counts.maintenance_contracts },
                            { label: 'Rechnungen', value: counts.invoices },
                            { label: 'Angebote', value: counts.offers },
                            { label: 'Katalogartikel', value: counts.catalog_items },
                            { label: 'Inventar', value: counts.inventory_items },
                            { label: 'Finanzen', value: counts.finance },
                        ]" :key="item.label" class="rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                            <dt class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">{{ item.label }}</dt>
                            <dd class="mt-1 font-display text-2xl font-semibold text-gray-900 dark:text-white">{{ item.value }}</dd>
                        </div>
                    </dl>

                    <a :href="route('backup.download')" class="btn mt-6 w-full cursor-pointer bg-primary-500 text-white hover:bg-primary-600 hover:shadow-[0_8px_24px_-8px_rgba(31,173,174,0.8)]">
                        <ArrowDownTrayIcon class="mr-2 h-5 w-5" />Backup herunterladen
                    </a>

                    <p class="mt-4 flex gap-2 text-xs text-gray-500">
                        <InformationCircleIcon class="h-4 w-4 shrink-0" />
                        Enthalten sind Kunden, Wartungsverträge samt Verrechnungen, Rechnungen mit Positionen und Zahlungen, Angebote, der Katalog, das Inventar samt Belegen, deine Finanzen (Verträge und Buchungen), deine Firmendaten und dein Logo. Dein Benutzerkonto und Passwort sind nicht enthalten.
                    </p>
                </div>
            </section>

            <!-- Restore -->
            <section class="card p-6 xl:col-span-3">
                <h2 class="card-title"><ArrowUpTrayIcon class="h-5 w-5 text-primary-500" />Backup einspielen</h2>
                <p class="mt-1 text-sm text-gray-500">Wähle eine meSync-Backup-Datei und wie sie eingespielt werden soll.</p>

                <form class="mt-5 space-y-5" @submit.prevent="restore">
                    <!-- File -->
                    <div>
                        <label
                            class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-8 text-center transition"
                            :class="dragging
                                ? 'border-primary-500 bg-primary-500/[0.06]'
                                : form.errors.file ? 'border-red-400/70' : 'border-gray-200 hover:border-primary-400 dark:border-white/10 dark:hover:border-primary-500/60'"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop"
                        >
                            <input ref="fileInput" type="file" accept=".json,application/json" class="sr-only" @change="select($event.target.files[0])" />
                            <DocumentArrowUpIcon class="h-9 w-9" :class="form.file ? 'text-primary-500' : 'text-gray-400'" />
                            <template v-if="form.file">
                                <span class="mt-2 font-medium text-gray-900 dark:text-gray-100">{{ form.file.name }}</span>
                                <span class="text-xs text-gray-500">{{ formatSize(form.file.size) }} · zum Ändern klicken</span>
                            </template>
                            <template v-else>
                                <span class="mt-2 font-medium text-gray-700 dark:text-gray-300">Backup-Datei hierher ziehen</span>
                                <span class="text-xs text-gray-500">oder klicken zum Auswählen (.json)</span>
                            </template>
                        </label>
                        <p v-if="form.errors.file" class="mt-1 text-xs text-red-500">{{ form.errors.file }}</p>
                    </div>

                    <!-- Mode -->
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2" role="radiogroup">
                        <button v-for="mode in modes" :key="mode.value" type="button" role="radio" :aria-checked="form.mode === mode.value"
                            class="relative flex cursor-pointer gap-3 rounded-2xl p-4 text-left ring-1 ring-inset transition"
                            :class="form.mode === mode.value
                                ? (mode.value === 'replace' ? 'bg-red-500/[0.06] ring-red-500/60' : 'bg-primary-500/[0.06] ring-primary-500/60')
                                : 'ring-gray-200 hover:ring-gray-300 dark:ring-white/10 dark:hover:ring-white/20'"
                            @click="form.mode = mode.value">
                            <component :is="mode.icon" class="h-6 w-6 shrink-0"
                                :class="form.mode === mode.value ? (mode.value === 'replace' ? 'text-red-500' : 'text-primary-500') : 'text-gray-400'" />
                            <span>
                                <span class="block font-semibold text-gray-900 dark:text-white">{{ mode.title }}</span>
                                <span class="mt-1 block text-sm text-gray-500">{{ mode.text }}</span>
                            </span>
                            <CheckIcon v-if="form.mode === mode.value" class="absolute top-3 right-3 h-4 w-4"
                                :class="mode.value === 'replace' ? 'text-red-500' : 'text-primary-500'" />
                        </button>
                    </div>
                    <p v-if="form.errors.mode" class="text-xs text-red-500">{{ form.errors.mode }}</p>

                    <div class="flex justify-end">
                        <VButton :variant="form.mode === 'replace' ? 'danger' : 'primary'" icon="ArrowUpTrayIcon"
                            :loading="form.processing" :disabled="!form.file || form.processing" @click="restore">
                            {{ form.mode === 'replace' ? 'Alles ersetzen' : 'Backup anhängen' }}
                        </VButton>
                    </div>
                </form>
            </section>
        </div>
    </Layout>
</template>
