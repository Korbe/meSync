<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import VInput from '@/components/VInput.vue';
import VButton from '@/components/VButton.vue';
import VModal from '@/components/VModal.vue';
import VAddressSearch from '@/components/VAddressSearch.vue';
import { canPickContact, pickContact } from '@/utils/contactPicker';

/*
 * Create a customer without leaving the invoice. The page reloads its customer list
 * (form state is kept), and the new customer is handed back to be selected.
 */

const props = defineProps({
    show: { type: Boolean, default: false },
    customers: { type: Array, required: true },
});

const emit = defineEmits(['close', 'created']);

const blank = () => ({
    title_prefix: '', first_name: '', last_name: '', company_name: '', company_suffix: '',
    street: '', zip: '', city: '', country: 'Österreich', email: '', phone: '', uid_number: '',
    interests: [],
    inline: true,
});

const interestOptions = computed(() => usePage().props.customerInterests ?? []);
const toggleInterest = (value) => {
    form.interests = form.interests.includes(value) ? form.interests.filter(interest => interest !== value) : [...form.interests, value];
};

const form = useForm(blank());

const fillAddress = (address) => {
    for (const field of ['street', 'zip', 'city', 'country']) {
        if (address[field]) form[field] = address[field];
    }
};

const fromPhone = async () => {
    try {
        const contact = await pickContact();
        for (const [field, value] of Object.entries(contact ?? {})) {
            if (value) form[field] = value;
        }
    } catch {
        // Picker closed or not allowed: nothing to fill
    }
};

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const save = () => {
    const known = new Set(props.customers.map(customer => customer.value));

    form.post(route('customers.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const created = page.props.customers.find(customer => !known.has(customer.value));
            form.reset();
            emit('created', created?.value ?? null);
        },
    });
};
</script>

<template>
    <VModal :show="show" max-width="2xl" @close="close">
        <template #title>Neuer Kunde</template>

        <template #content>
            <VButton v-if="canPickContact" class="mb-4 w-full justify-center" variant="secondary" icon="DevicePhoneMobileIcon" @click="fromPhone">Aus Handy-Kontakten übernehmen</VButton>
            <!-- Inside the invoice <form>: Enter saves the customer, not the invoice -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-12" @keydown.enter.prevent="save">
                <VInput id="quick_title_prefix" class="sm:col-span-3" label="Titel" placeholder="Dr." v-model="form.title_prefix" :error="form.errors.title_prefix" />
                <VInput id="quick_first_name" class="sm:col-span-4" label="Vorname" v-model="form.first_name" :error="form.errors.first_name" />
                <VInput id="quick_last_name" class="sm:col-span-5" label="Nachname" v-model="form.last_name" :error="form.errors.last_name" />
                <VInput id="quick_company_name" class="sm:col-span-8" label="Firmenname" v-model="form.company_name" :error="form.errors.company_name" />
                <VInput id="quick_company_suffix" class="sm:col-span-4" label="Rechtsform" placeholder="GmbH" v-model="form.company_suffix" :error="form.errors.company_suffix" />
                <VAddressSearch id="quick_address_search" class="sm:col-span-12" @select="fillAddress" />
                <VInput id="quick_street" class="sm:col-span-12" label="Straße & Hausnummer" v-model="form.street" :error="form.errors.street" />
                <VInput id="quick_zip" class="sm:col-span-3" label="PLZ" v-model="form.zip" :error="form.errors.zip" />
                <VInput id="quick_city" class="sm:col-span-5" label="Ort" v-model="form.city" :error="form.errors.city" />
                <VInput id="quick_country" class="sm:col-span-4" label="Land" v-model="form.country" :error="form.errors.country" />
                <VInput id="quick_email" class="sm:col-span-7" type="email" label="E-Mail" placeholder="name@firma.at" v-model="form.email" :error="form.errors.email" />
                <VInput id="quick_phone" class="sm:col-span-5" type="tel" label="Telefon" v-model="form.phone" :error="form.errors.phone" />
                <VInput id="quick_uid_number" class="sm:col-span-6" label="UID-Nummer" placeholder="ATU12345678" v-model="form.uid_number" :error="form.errors.uid_number" />
            </div>
            <div class="mt-4">
                <span class="mb-1.5 block text-sm font-medium">Interessiert an <span class="text-red-500">*</span></span>
                <div class="flex flex-wrap gap-2">
                    <button v-for="interest in interestOptions" :key="interest.value" type="button"
                        class="cursor-pointer rounded-full px-3 py-1 text-sm font-medium ring-1 transition"
                        :class="form.interests.includes(interest.value) ? 'bg-primary-500/15 text-primary-700 ring-primary-500 dark:text-primary-300' : 'text-gray-600 ring-gray-200 hover:ring-gray-300 dark:text-gray-400 dark:ring-white/10'"
                        @click="toggleInterest(interest.value)">{{ interest.label }}</button>
                </div>
                <div v-if="form.errors.interests" class="mt-1 text-xs text-red-500">{{ form.errors.interests }}</div>
            </div>
            <p class="mt-3 text-xs text-gray-500">Nachname oder Firmenname genügt. Weitere Angaben (Notizen) später in der Kundenakte.</p>
        </template>

        <template #footer>
            <VButton variant="secondary" @click="close">Abbrechen</VButton>
            <VButton :loading="form.processing" :disabled="form.processing" @click="save">Kunde anlegen</VButton>
        </template>
    </VModal>
</template>
