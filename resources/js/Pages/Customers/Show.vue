<script setup>
import { computed } from 'vue';
import { EnvelopeIcon, PhoneIcon, MapPinIcon, ReceiptPercentIcon, BanknotesIcon, ClockIcon, WrenchScrewdriverIcon, DocumentTextIcon, ChatBubbleBottomCenterTextIcon, GlobeAltIcon, CommandLineIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import StatusBadge from '@/Pages/MaintenanceContracts/StatusBadge.vue';
import PaymentStatusBadge from '@/Pages/Invoices/PaymentStatusBadge.vue';
import { formatDate, formatMoney, relativeDays } from '@/utils/format';

const props = defineProps({
    customer: Object,
    contracts: Array,
    invoices: Array,
    interests: Array,
    stats: Object,
});

const initials = computed(() => {
    const source = props.customer.last_name
        ? [props.customer.first_name, props.customer.last_name]
        : [props.customer.company_name];
    return source.filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase();
});

// Opens the contact card: the phone offers "Add to contacts"
const saveToPhone = () => window.location.href = route('customers.vcard', props.customer.id);

const interestLabel = (value) => props.interests.find(i => i.value === value)?.label ?? value;
const address = computed(() => [props.customer.street, [props.customer.zip, props.customer.city].filter(Boolean).join(' '), props.customer.country].filter(Boolean).join(', '));
const activeContracts = computed(() => props.contracts.filter(c => c.status !== 'ended'));
const typeIcons = { website: GlobeAltIcon, webapp: CommandLineIcon };
</script>

<template>
    <Layout :title="customer.display_name" :backTo="route('customers.index')">
        <template v-slot:actions>
            <VButton :href="route('invoices.create', { customer: customer.id })" icon="DocumentTextIcon">Rechnung</VButton>
            <VButton :href="route('contracts.create', { customer: customer.id })" variant="secondary" icon="WrenchScrewdriverIcon">Wartungsvertrag</VButton>
            <VButton variant="secondary" icon="DevicePhoneMobileIcon" title="Als Kontakt ins Handy übernehmen (vCard)" @click="saveToPhone">Ins Handy</VButton>
            <VButton :href="route('customers.edit', customer.id)" variant="secondary" icon="PencilSquareIcon">Bearbeiten</VButton>
        </template>

        <div class="space-y-6">
            <!-- Contact card -->
            <section class="card relative overflow-hidden p-6">
                <div class="pointer-events-none absolute -top-24 -left-16 h-64 w-64 rounded-full bg-primary-500/15 blur-[90px]" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start">
                    <div class="flex items-center gap-4 lg:w-1/3">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary-500/10 font-display text-2xl font-bold text-primary-600 ring-1 ring-primary-500/25 dark:text-primary-300">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate font-display text-xl font-bold text-gray-900 dark:text-white">{{ customer.full_name || customer.company }}</div>
                            <div v-if="customer.full_name && customer.company" class="truncate text-sm text-gray-500">{{ customer.company }}</div>
                            <div v-if="customer.interests?.length" class="mt-2 flex flex-wrap gap-1">
                                <span v-for="value in customer.interests" :key="value" class="rounded-full bg-primary-500/10 px-2 py-0.5 text-[11px] font-medium text-primary-700 ring-1 ring-inset ring-primary-500/25 dark:text-primary-300">{{ interestLabel(value) }}</span>
                            </div>
                        </div>
                    </div>

                    <dl class="grid flex-1 grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div v-if="customer.email" class="flex items-center gap-2">
                            <EnvelopeIcon class="h-4 w-4 shrink-0 text-gray-400" />
                            <a :href="`mailto:${customer.email}`" class="truncate text-gray-700 hover:text-primary-500 dark:text-gray-300">{{ customer.email }}</a>
                        </div>
                        <div v-if="customer.phone" class="flex items-center gap-2">
                            <PhoneIcon class="h-4 w-4 shrink-0 text-gray-400" />
                            <a :href="`tel:${customer.phone}`" class="text-gray-700 hover:text-primary-500 dark:text-gray-300">{{ customer.phone }}</a>
                        </div>
                        <div v-if="address" class="flex items-center gap-2 sm:col-span-2">
                            <MapPinIcon class="h-4 w-4 shrink-0 text-gray-400" />
                            <a :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`" target="_blank" rel="noopener"
                                class="text-gray-700 hover:text-primary-500 dark:text-gray-300" title="In Google Maps öffnen">{{ address }}</a>
                        </div>
                        <div v-if="customer.uid_number" class="flex items-center gap-2">
                            <ReceiptPercentIcon class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="font-mono text-gray-700 dark:text-gray-300">{{ customer.uid_number }}</span>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="card p-5">
                    <div class="flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500"><BanknotesIcon class="h-4 w-4" />Umsatz netto gesamt</div>
                    <div class="mt-2 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ formatMoney(stats.revenue) }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ invoices.length }} Rechnungen</div>
                </div>
                <div class="card p-5" :class="stats.outstanding > 0 && 'ring-1 ring-accent/40'">
                    <div class="flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500"><ClockIcon class="h-4 w-4" />Offen</div>
                    <div class="mt-2 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ formatMoney(stats.outstanding) }}</div>
                    <div class="mt-1 text-xs" :class="stats.overdue ? 'text-red-600 dark:text-red-400' : 'text-gray-500'">
                        {{ stats.overdue ? `${stats.overdue} überfällig` : 'nichts überfällig' }}
                    </div>
                </div>
                <div class="card p-5">
                    <div class="flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500"><WrenchScrewdriverIcon class="h-4 w-4" />Wartungsverträge</div>
                    <div class="mt-2 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ activeContracts.length }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ formatMoney(activeContracts.reduce((s, c) => s + Number(c.annual_price), 0)) }} / Jahr</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
                <!-- Invoices -->
                <section class="card overflow-hidden xl:col-span-3">
                    <div class="flex items-center justify-between p-6 pb-3">
                        <h2 class="card-title"><DocumentTextIcon class="h-5 w-5 text-primary-500" />Rechnungen</h2>
                    </div>
                    <p v-if="invoices.length === 0" class="px-6 pb-6 text-sm text-gray-500">Noch keine Rechnungen.</p>
                    <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <li v-for="invoice in invoices" :key="invoice.id">
                            <Link :href="route('invoices.edit', invoice.id)" class="flex items-center gap-3 px-6 py-3 transition hover:bg-primary-500/[0.04]" :class="invoice.cancellation && 'opacity-55'">
                                <div class="min-w-0 flex-1">
                                    <div class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">{{ invoice.number }}</div>
                                    <div class="text-xs text-gray-500">{{ formatDate(invoice.invoice_date) }}<template v-if="invoice.cancels"> · Storno zu {{ invoice.cancels.number }}</template></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100" :class="invoice.cancellation && 'line-through'">{{ formatMoney(invoice.total) }}</div>
                                    <PaymentStatusBadge :status="invoice.payment_status" class="mt-1" />
                                </div>
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Contracts -->
                <section class="card overflow-hidden xl:col-span-2">
                    <div class="p-6 pb-3">
                        <h2 class="card-title"><WrenchScrewdriverIcon class="h-5 w-5 text-primary-500" />Wartungsverträge</h2>
                    </div>
                    <p v-if="contracts.length === 0" class="px-6 pb-6 text-sm text-gray-500">Kein Wartungsvertrag.</p>
                    <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <li v-for="contract in contracts" :key="contract.id">
                            <Link :href="route('contracts.edit', contract.id)" class="flex items-center gap-3 px-6 py-3 transition hover:bg-primary-500/[0.04]">
                                <component :is="typeIcons[contract.type]" class="h-5 w-5 shrink-0 text-primary-500" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ contract.name }}</div>
                                    <div class="text-xs text-gray-500">
                                        <template v-if="contract.status !== 'ended'">nächste Rechnung {{ formatDate(contract.next_invoice_date) }} ({{ relativeDays(contract.next_invoice_date) }})</template>
                                        <template v-else>beendet {{ formatDate(contract.end_date) }}</template>
                                    </div>
                                </div>
                                <StatusBadge :status="contract.status" />
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Notes -->
            <section v-if="customer.notes" class="card p-6">
                <h2 class="card-title"><ChatBubbleBottomCenterTextIcon class="h-5 w-5 text-primary-500" />Bemerkung</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ customer.notes }}</p>
            </section>
        </div>
    </Layout>
</template>
