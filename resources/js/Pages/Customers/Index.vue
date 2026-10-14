<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, PencilSquareIcon, TrashIcon, EnvelopeIcon, PhoneIcon, MapPinIcon, UsersIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import PaginationNumeric from '@/components/pagination/PaginationNumeric.vue';

const props = defineProps({
    customers: Object,
    search: String,
});

const search = ref(props.search ?? '');

// Debounced server-side search
let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('customers.index'), value ? { search: value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

const initials = (customer) => {
    const source = customer.last_name
        ? [customer.first_name, customer.last_name]
        : [customer.company_name];

    return source.filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase();
};

const location = (customer) => [customer.zip, customer.city].filter(Boolean).join(' ');

const edit = (customer) => router.visit(route('customers.edit', customer.id));
</script>

<template>
    <Layout title="Kunden">
        <template v-slot:actions>
            <!-- Search -->
            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Kunden suchen …"
                    class="form-input w-full pl-9 sm:w-64"
                />
            </div>
            <VButton :href="route('customers.create')" icon="PlusIcon">Neuer Kunde</VButton>
        </template>

        <!-- Count -->
        <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white/60 px-3 py-1 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
            <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
            {{ customers.total }} {{ customers.total === 1 ? 'Kunde' : 'Kunden' }}
        </p>

        <!-- Empty state -->
        <div v-if="customers.data.length === 0" class="card relative overflow-hidden px-6 py-16 text-center">
            <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-96 -translate-x-1/2 rounded-full bg-primary-500/20 blur-[90px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10 ring-1 ring-primary-500/30">
                    <UsersIcon class="h-7 w-7 text-primary-500" />
                </div>
                <template v-if="search">
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Keine Treffer</h2>
                    <p class="mt-2 text-sm text-gray-500">Für „{{ search }}“ wurde kein Kunde gefunden.</p>
                </template>
                <template v-else>
                    <h2 class="mt-5 font-display text-xl font-semibold text-gray-900 dark:text-white">Noch keine Kunden</h2>
                    <p class="mt-2 text-sm text-gray-500">Leg deinen ersten Kunden an, um loszulegen.</p>
                    <div class="mt-6">
                        <VButton :href="route('customers.create')" icon="PlusIcon">Neuer Kunde</VButton>
                    </div>
                </template>
            </div>
        </div>

        <template v-else>
            <!-- Desktop: table -->
            <div class="card hidden overflow-hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500 dark:border-white/[0.08]">
                        <tr>
                            <th class="px-5 py-3 font-medium">Kunde</th>
                            <th class="px-5 py-3 font-medium">Kontakt</th>
                            <th class="px-5 py-3 font-medium">Ort</th>
                            <th class="px-5 py-3 font-medium">UID</th>
                            <th class="px-5 py-3"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                        <tr v-for="customer in customers.data" :key="customer.id"
                            class="group cursor-pointer transition hover:bg-primary-500/[0.04]"
                            @click="edit(customer)">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-500/10 font-display text-sm font-bold text-primary-600 ring-1 ring-primary-500/25 transition group-hover:shadow-[0_0_18px_-4px_rgba(31,173,174,0.7)] dark:text-primary-300">
                                        {{ initials(customer) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-gray-900 dark:text-gray-100">{{ customer.display_name }}</div>
                                        <div v-if="customer.full_name && customer.company" class="truncate text-xs text-gray-500">{{ customer.company }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div v-if="customer.email" class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300">
                                    <EnvelopeIcon class="h-4 w-4 shrink-0 text-gray-400" />
                                    <a :href="`mailto:${customer.email}`" class="truncate hover:text-primary-500" @click.stop>{{ customer.email }}</a>
                                </div>
                                <div v-if="customer.phone" class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500">
                                    <PhoneIcon class="h-3.5 w-3.5 shrink-0" />
                                    <a :href="`tel:${customer.phone}`" class="hover:text-primary-500" @click.stop>{{ customer.phone }}</a>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">{{ location(customer) }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-600 dark:text-gray-400">{{ customer.uid_number }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-1 opacity-60 transition group-hover:opacity-100">
                                    <Link :href="route('customers.edit', customer.id)" class="rounded-lg p-2 text-gray-500 hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-300" title="Bearbeiten" @click.stop>
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                    <Link :href="route('customers.delete', customer.id)" class="rounded-lg p-2 text-gray-500 hover:bg-red-500/10 hover:text-red-500" title="Löschen" @click.stop>
                                        <TrashIcon class="h-4 w-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: cards -->
            <div class="space-y-3 md:hidden">
                <Link v-for="customer in customers.data" :key="customer.id" :href="route('customers.edit', customer.id)" class="card flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-500/10 font-display text-sm font-bold text-primary-600 ring-1 ring-primary-500/25 dark:text-primary-300">
                        {{ initials(customer) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-gray-900 dark:text-gray-100">{{ customer.display_name }}</div>
                        <div class="truncate text-xs text-gray-500">{{ customer.full_name && customer.company ? customer.company : customer.email }}</div>
                        <div v-if="location(customer)" class="mt-1 flex items-center gap-1 text-xs text-gray-500">
                            <MapPinIcon class="h-3.5 w-3.5" />{{ location(customer) }}
                        </div>
                    </div>
                </Link>
            </div>

            <PaginationNumeric v-if="customers.last_page > 1" :pagination="customers" />
        </template>
    </Layout>
</template>
