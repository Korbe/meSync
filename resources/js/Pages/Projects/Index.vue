<script setup>
import { computed, ref } from 'vue';
import { FolderIcon, ArrowRightIcon, GlobeAltIcon, CommandLineIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import VSearchInput from '@/components/VSearchInput.vue';
import { formatMoney } from '@/utils/format';

const props = defineProps({
    projects: Array,
    phases: Object,   // key => label, in order
});

// Few enough projects to filter in the browser
const search = ref('');
const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    return term ? props.projects.filter(p => `${p.name} ${p.customer} ${p.url ?? ''}`.toLowerCase().includes(term)) : props.projects;
});

const columns = computed(() => Object.entries(props.phases).map(([key, label]) => ({
    key,
    label,
    projects: filtered.value.filter(p => p.phase === key),
})));

const columnTone = {
    request: 'bg-gray-400',
    offer: 'bg-accent',
    implementation: 'bg-primary-400',
    live: 'bg-primary-600',
};

const typeIcons = { Website: GlobeAltIcon, Webanwendung: CommandLineIcon };
</script>

<template>
    <Layout title="Projekte">
        <template v-slot:actions>
            <VSearchInput v-model="search" placeholder="Projekt oder Kunde …" />
            <VButton :href="route('projects.create')" icon="PlusIcon">Neues Projekt</VButton>
        </template>

        <!-- Empty state -->
        <div v-if="projects.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <FolderIcon class="h-7 w-7 text-primary-500" />
                </div>
                <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Noch keine Projekte</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                    Ein Projekt begleitet eine Website oder Web-App von der Anfrage bis zum Go-Live – mit Angeboten, Rechnungen und Wartungsvertrag.
                </p>
                <div class="mt-6">
                    <VButton :href="route('projects.create')" icon="PlusIcon">Neues Projekt</VButton>
                </div>
            </div>
        </div>

        <!-- Board: the phase follows from offers, invoices and the contract -->
        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section v-for="column in columns" :key="column.key" class="flex min-w-0 flex-col rounded-2xl bg-gray-100/70 p-3 dark:bg-white/[0.03]">
                <header class="mb-3 flex items-center gap-2 px-1">
                    <span class="h-2 w-2 rounded-full" :class="columnTone[column.key]"></span>
                    <h2 class="font-display text-sm font-semibold text-gray-900 dark:text-gray-100">{{ column.label }}</h2>
                    <span class="ml-auto rounded-full bg-white px-2 text-xs text-gray-500 dark:bg-white/10">{{ column.projects.length }}</span>
                </header>

                <div class="space-y-3">
                    <Link v-for="project in column.projects" :key="project.id" :href="route('projects.show', project.id)"
                        class="card block p-4 transition hover:-translate-y-0.5 hover:shadow-[0_12px_30px_-14px_rgba(31,173,174,0.6)]"
                        :class="project.lost && 'opacity-60'">
                        <div class="flex items-start gap-2">
                            <component :is="typeIcons[project.type_label]" class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ project.name }}</div>
                                <div class="truncate text-xs text-gray-500">{{ project.customer }}</div>
                            </div>
                        </div>

                        <div v-if="project.total || project.monthly" class="mt-3 flex items-baseline justify-between text-xs text-gray-500">
                            <span v-if="project.total" class="font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(project.total) }}</span>
                            <span v-if="project.monthly">{{ formatMoney(project.monthly) }}/Monat</span>
                        </div>

                        <!-- What to do next -->
                        <div class="mt-3 flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs"
                            :class="project.lost ? 'bg-gray-500/10 text-gray-500' : 'bg-primary-500/[0.08] text-primary-700 dark:text-primary-300'">
                            <ArrowRightIcon class="h-3.5 w-3.5 shrink-0" />
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ project.lost ? 'Abgelehnt / abgelaufen' : project.next.label }}</span>
                                <span class="block truncate opacity-80">{{ project.next.hint }}</span>
                            </span>
                        </div>
                    </Link>

                    <p v-if="column.projects.length === 0" class="px-1 py-4 text-center text-xs text-gray-400">Nichts in dieser Phase</p>
                </div>
            </section>
        </div>
    </Layout>
</template>
