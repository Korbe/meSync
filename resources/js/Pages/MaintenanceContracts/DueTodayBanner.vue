<script setup>
import { CalendarDaysIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import { formatMoney } from '@/utils/format';

/*
 * Big, impossible to miss: maintenance contracts whose yearly invoice is due today.
 * Disappears as soon as the invoice is created or the contract is marked as invoiced.
 */
defineProps({
    contracts: { type: Array, required: true },   // status "due_today", with customer
});
</script>

<template>
    <section v-if="contracts.length" class="relative mb-6 overflow-hidden rounded-3xl bg-accent/[0.1] p-6 ring-2 ring-accent/60 ring-inset shadow-[0_20px_60px_-25px_rgba(245,165,36,0.7)]">
        <div class="pointer-events-none absolute -top-24 -right-16 h-64 w-64 rounded-full bg-accent/30 blur-[90px]" aria-hidden="true"></div>

        <div class="relative">
            <div class="flex items-center gap-4">
                <div class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-accent text-gray-950 shadow-[0_0_30px_rgba(245,165,36,0.6)]">
                    <span class="absolute inset-0 animate-ping rounded-2xl bg-accent opacity-30"></span>
                    <CalendarDaysIcon class="relative h-7 w-7" />
                </div>
                <div class="min-w-0">
                    <div class="font-mono text-[11px] font-semibold uppercase tracking-[0.22em] text-accent">Heute fällig</div>
                    <h2 class="font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ contracts.length === 1 ? 'Jahresrechnung für einen Wartungsvertrag' : `Jahresrechnungen für ${contracts.length} Wartungsverträge` }}
                    </h2>
                </div>
            </div>

            <ul class="mt-5 space-y-3">
                <li v-for="contract in contracts" :key="contract.id"
                    class="flex flex-wrap items-center gap-4 rounded-2xl bg-white/80 p-4 ring-1 ring-accent/30 dark:bg-gray-950/60">
                    <div class="min-w-0 flex-1">
                        <Link :href="route('contracts.edit', contract.id)" class="font-display text-lg font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-300">{{ contract.name }}</Link>
                        <div class="text-sm text-gray-500">{{ contract.customer?.display_name }} · {{ contract.type_label }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-display text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ formatMoney(contract.annual_price) }}</div>
                        <div class="text-xs text-gray-500">netto für ein Jahr</div>
                    </div>
                    <Link :href="route('invoices.create', { contract: contract.id })"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-600 hover:shadow-[0_8px_24px_-8px_rgba(31,173,174,0.8)]">
                        <DocumentTextIcon class="h-5 w-5" />Rechnung erstellen
                    </Link>
                </li>
            </ul>
        </div>
    </section>
</template>
