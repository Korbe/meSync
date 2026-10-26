<script setup>
import { BellAlertIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import { formatDate } from '@/utils/format';

defineProps({
    newsletters: Array,
});
</script>

<template>
    <Layout title="E-Mail-Benachrichtigungen">
        <template v-slot:actions>
            <VButton :href="route('newsletters.create')" icon="PlusIcon">Neue Benachrichtigung</VButton>
        </template>

        <p class="-mt-4 mb-6 max-w-2xl text-gray-500">Eine E-Mail an alle Kunden, an Kunden mit bestimmten Interessen oder an einzelne Kunden – jeder bekommt sie mit persönlicher Anrede.</p>

        <section class="card overflow-hidden">
            <div v-if="newsletters.length === 0" class="flex flex-col items-center px-6 py-14 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <BellAlertIcon class="h-6 w-6 text-primary-500" />
                </span>
                <p class="mt-4 font-medium text-gray-900 dark:text-white">Noch keine Benachrichtigung</p>
                <p class="mt-1 text-sm text-gray-500">Neues Angebot, Preisänderung, Urlaub? Schreib deinen Kunden.</p>
                <VButton class="mt-5" :href="route('newsletters.create')" icon="PlusIcon">Neue Benachrichtigung</VButton>
            </div>

            <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                <li v-for="newsletter in newsletters" :key="newsletter.id">
                    <Link :href="route('newsletters.edit', newsletter.id)" class="flex flex-wrap items-center gap-3 px-6 py-4 transition hover:bg-primary-500/[0.04]">
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium text-gray-900 dark:text-gray-100">{{ newsletter.subject }}</div>
                            <div class="truncate text-xs text-gray-500">{{ newsletter.audience_label }}</div>
                        </div>
                        <div class="text-right text-xs">
                            <template v-if="newsletter.sent_at">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/10 px-2.5 py-0.5 font-medium text-primary-700 ring-1 ring-primary-500/30 ring-inset dark:text-primary-300">Verschickt</span>
                                <div class="mt-1 text-gray-500">
                                    {{ formatDate(newsletter.sent_at) }} · {{ newsletter.sent_count }}/{{ newsletter.recipients_count }} zugestellt
                                    <span v-if="newsletter.failed_count" class="text-red-500"> · {{ newsletter.failed_count }} Fehler</span>
                                </div>
                            </template>
                            <template v-else>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-500/10 px-2.5 py-0.5 font-medium text-gray-700 ring-1 ring-gray-500/25 ring-inset dark:text-gray-300">Entwurf</span>
                                <div class="mt-1 text-gray-500">geändert {{ formatDate(newsletter.updated_at) }}</div>
                            </template>
                        </div>
                    </Link>
                </li>
            </ul>
        </section>
    </Layout>
</template>
