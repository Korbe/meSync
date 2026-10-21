<script setup>
import { useForm } from '@inertiajs/vue3';
import { UserIcon, BuildingOffice2Icon, MapPinIcon, PhoneIcon, ChatBubbleBottomCenterTextIcon, SparklesIcon, LifebuoyIcon, GlobeAltIcon, CommandLineIcon, CpuChipIcon, CheckIcon } from '@heroicons/vue/24/outline';
import VInput from '@/components/VInput.vue';
import VTextarea from '@/components/VTextarea.vue';
import VButton from '@/components/VButton.vue';
import VAddressSearch from '@/components/VAddressSearch.vue';
import { canPickContact, pickContact } from '@/utils/contactPicker';

const props = defineProps({
    customer: { type: Object, default: null },
    interests: { type: Array, default: () => [] },
});

const interestIcons = { pc_help: LifebuoyIcon, website: GlobeAltIcon, webapp: CommandLineIcon, pc_build: CpuChipIcon };

const fields = [
    'title_prefix', 'first_name', 'last_name', 'title_suffix',
    'company_name', 'company_suffix',
    'street', 'zip', 'city', 'country',
    'email', 'phone', 'uid_number', 'notes',
];

// Inputs expect strings, so map null values from the database to ''
const form = useForm({
    ...Object.fromEntries(
        fields.map(field => [field, props.customer?.[field] ?? (field === 'country' ? 'Österreich' : '')])
    ),
    interests: [...(props.customer?.interests ?? [])],
});

const toggleInterest = (value) => {
    form.interests = form.interests.includes(value)
        ? form.interests.filter(interest => interest !== value)
        : [...form.interests, value];
};

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

const submit = () => {
    if (props.customer)
        form.put(route('customers.update', props.customer.id));
    else
        form.post(route('customers.store'));
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">

            <!-- Person -->
            <section class="card p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="card-title"><UserIcon class="h-5 w-5 text-primary-500" />Person</h2>
                    <VButton v-if="canPickContact" variant="secondary" icon="DevicePhoneMobileIcon" @click="fromPhone">Aus Kontakten</VButton>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-12">
                    <VInput id="title_prefix" class="sm:col-span-3" label="Titel (vorangestellt)" placeholder="Dr." list="title-prefixes" v-model="form.title_prefix" :error="form.errors.title_prefix" />
                    <VInput id="first_name" class="sm:col-span-4" label="Vorname" v-model="form.first_name" :error="form.errors.first_name" />
                    <VInput id="last_name" class="sm:col-span-5" label="Nachname" v-model="form.last_name" :error="form.errors.last_name" />
                    <VInput id="title_suffix" class="sm:col-span-3" label="Titel (nachgestellt)" placeholder="MSc" list="title-suffixes" v-model="form.title_suffix" :error="form.errors.title_suffix" />
                </div>
            </section>

            <!-- Firma -->
            <section class="card p-6">
                <h2 class="card-title"><BuildingOffice2Icon class="h-5 w-5 text-primary-500" />Firma</h2>
                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-12">
                    <VInput id="company_name" class="sm:col-span-8" label="Firmenname" v-model="form.company_name" :error="form.errors.company_name" />
                    <VInput id="company_suffix" class="sm:col-span-4" label="Rechtsform / Zusatz" placeholder="GmbH" list="company-suffixes" v-model="form.company_suffix" :error="form.errors.company_suffix" />
                    <VInput id="uid_number" class="sm:col-span-6" label="UID-Nummer" placeholder="ATU12345678" v-model="form.uid_number" :error="form.errors.uid_number" />
                </div>
                <p class="mt-3 text-xs text-gray-500">Nachname oder Firmenname muss angegeben werden.</p>
            </section>

            <!-- Adresse -->
            <section class="card p-6">
                <h2 class="card-title"><MapPinIcon class="h-5 w-5 text-primary-500" />Adresse</h2>
                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-12">
                    <VAddressSearch class="sm:col-span-12" @select="fillAddress" />
                    <VInput id="street" class="sm:col-span-12" label="Straße & Hausnummer" v-model="form.street" :error="form.errors.street" />
                    <VInput id="zip" class="sm:col-span-3" label="PLZ" v-model="form.zip" :error="form.errors.zip" />
                    <VInput id="city" class="sm:col-span-5" label="Ort" v-model="form.city" :error="form.errors.city" />
                    <VInput id="country" class="sm:col-span-4" label="Land" v-model="form.country" :error="form.errors.country" />
                </div>
            </section>

            <!-- Kontakt -->
            <section class="card p-6">
                <h2 class="card-title"><PhoneIcon class="h-5 w-5 text-primary-500" />Kontakt</h2>
                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-12">
                    <VInput id="email" class="sm:col-span-7" type="email" label="E-Mail" placeholder="name@firma.at" v-model="form.email" :error="form.errors.email" />
                    <VInput id="phone" class="sm:col-span-5" type="tel" label="Telefon" placeholder="+43 664 1234567" v-model="form.phone" :error="form.errors.phone" />
                </div>
            </section>

            <!-- Interessen (multiple choice) -->
            <section class="card p-6 xl:col-span-2">
                <h2 class="card-title"><SparklesIcon class="h-5 w-5 text-primary-500" />Interessiert an</h2>
                <p class="mt-1 text-sm text-gray-500">Mehrfachauswahl möglich.</p>

                <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <button v-for="interest in interests" :key="interest.value" type="button"
                        class="relative flex items-center gap-3 rounded-xl border p-3.5 text-left transition"
                        :class="form.interests.includes(interest.value)
                            ? 'border-primary-500 bg-primary-500/[0.08] shadow-[0_10px_30px_-12px_rgba(31,173,174,0.6)]'
                            : 'border-gray-200 hover:border-gray-300 dark:border-white/10 dark:hover:border-white/20'"
                        :aria-pressed="form.interests.includes(interest.value)"
                        @click="toggleInterest(interest.value)">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                            :class="form.interests.includes(interest.value) ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/5'">
                            <component :is="interestIcons[interest.value]" class="h-5 w-5" />
                        </span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ interest.label }}</span>
                        <CheckIcon v-if="form.interests.includes(interest.value)" class="absolute top-2 right-2 h-4 w-4 text-primary-500" />
                    </button>
                </div>
                <div v-if="form.errors.interests" class="text-xs mt-2 text-red-500">{{ form.errors.interests }}</div>
            </section>

            <!-- Bemerkung -->
            <section class="card p-6 xl:col-span-2">
                <h2 class="card-title"><ChatBubbleBottomCenterTextIcon class="h-5 w-5 text-primary-500" />Bemerkung</h2>
                <div class="mt-5">
                    <VTextarea id="notes" :rows="4" placeholder="Interne Notizen zum Kunden …" v-model="form.notes" />
                    <div v-if="form.errors.notes" class="text-xs mt-1 text-red-500">{{ form.errors.notes }}</div>
                </div>
            </section>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <VButton :href="route('customers.index')" variant="secondary">Abbrechen</VButton>
            <VButton type="submit" :loading="form.processing" :disabled="form.processing">
                {{ customer ? 'Speichern' : 'Kunde anlegen' }}
            </VButton>
        </div>

        <datalist id="title-prefixes">
            <option v-for="title in ['Dr.', 'Mag.', 'Mag.a', 'MMag.', 'DI', 'Dipl.-Ing.', 'Ing.', 'Prof.', 'Dkfm.']" :key="title" :value="title" />
        </datalist>
        <datalist id="title-suffixes">
            <option v-for="title in ['BSc', 'MSc', 'BA', 'MA', 'MBA', 'PhD', 'LL.M.', 'BEd', 'MEd']" :key="title" :value="title" />
        </datalist>
        <datalist id="company-suffixes">
            <option v-for="suffix in ['GmbH', 'e.U.', 'OG', 'KG', 'AG', 'GmbH & Co KG', 'GesbR', 'FlexCo']" :key="suffix" :value="suffix" />
        </datalist>
    </form>
</template>
