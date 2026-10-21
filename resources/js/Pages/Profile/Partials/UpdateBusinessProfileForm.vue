<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { BuildingStorefrontIcon, SwatchIcon, CheckIcon } from '@heroicons/vue/24/outline';
import VButton from '@/components/VButton.vue';
import VActionMessage from '@/components/VActionMessage.vue';
import VInput from '@/components/VInput.vue';

const props = defineProps({
    profile: { type: Object, default: null },
});

const fields = [
    'name', 'brand_name', 'street', 'zip', 'city', 'country', 'email', 'phone', 'website',
    'uid_number', 'bank_name', 'account_holder', 'iban', 'bic',
];

const form = useForm({
    ...Object.fromEntries(fields.map(field => [field, props.profile?.[field] ?? ''])),
    country: props.profile?.country ?? 'Österreich',
    payment_terms_days: String(props.profile?.payment_terms_days ?? 14),
    default_tax_rate: String(Number(props.profile?.default_tax_rate ?? 20)),
    invoice_color: props.profile?.invoice_color ?? '#1fadae',
});

const presets = [
    { name: 'Teal', value: '#1fadae' },
    { name: 'Blau', value: '#2563eb' },
    { name: 'Indigo', value: '#4f46e5' },
    { name: 'Violett', value: '#7c3aed' },
    { name: 'Pink', value: '#db2777' },
    { name: 'Rot', value: '#dc2626' },
    { name: 'Orange', value: '#ea580c' },
    { name: 'Amber', value: '#f59e0b' },
    { name: 'Grün', value: '#16a34a' },
    { name: 'Smaragd', value: '#059669' },
    { name: 'Schiefer', value: '#334155' },
    { name: 'Schwarz', value: '#171717' },
];

const isValidColor = computed(() => /^#[0-9a-fA-F]{6}$/.test(form.invoice_color));
const previewColor = computed(() => isValidColor.value ? form.invoice_color : '#1fadae');

// Same rule as the PDF: the text color with the higher contrast wins
const onColor = computed(() => {
    const [r, g, b] = [1, 3, 5].map(i => parseInt(previewColor.value.slice(i, i + 2), 16) / 255)
        .map(c => c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    return (luminance + 0.05) / 0.05 > 1.05 / (luminance + 0.05) ? '#0a0a0a' : '#ffffff';
});

const submit = () => {
    form.put(route('business-profile.update'), { preserveScroll: true });
};
</script>

<template>
    <section class="card overflow-hidden">
        <form @submit.prevent="submit">
            <div class="p-6">
                <h2 class="card-title"><BuildingStorefrontIcon class="h-5 w-5 text-primary-500" />Rechnungsdaten</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Absender, Kontakt und Bankverbindung, die auf deinen Rechnungen stehen. Die IBAN wird auch für den Zahlungs-QR-Code verwendet.</p>

                <div class="mt-6 grid grid-cols-1 gap-8 xl:grid-cols-3">
                    <!-- Absender -->
                    <div class="space-y-4">
                        <h3 class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Absender</h3>
                        <VInput id="bp_name" label="Name / Firma" placeholder="Lukas Korbitsch" v-model="form.name" :error="form.errors.name" />
                        <div>
                            <VInput id="bp_brand_name" label="Unternehmensbezeichnung (optional)" placeholder="z. B. Korbitsch Web Software und IT Dienstleistungen" v-model="form.brand_name" :error="form.errors.brand_name" />
                            <p class="mt-1 text-xs text-gray-500">Für Mails an Kunden: Betreff, Absendername und Grußformel. Auf den Rechnungen steht weiterhin der Name darüber.</p>
                        </div>
                        <VInput id="bp_street" label="Straße & Hausnummer" v-model="form.street" :error="form.errors.street" />
                        <div class="grid grid-cols-3 gap-3">
                            <VInput id="bp_zip" label="PLZ" v-model="form.zip" :error="form.errors.zip" />
                            <VInput id="bp_city" class="col-span-2" label="Ort" v-model="form.city" :error="form.errors.city" />
                        </div>
                        <VInput id="bp_country" label="Land" v-model="form.country" :error="form.errors.country" />
                    </div>

                    <!-- Kontakt -->
                    <div class="space-y-4">
                        <h3 class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Kontakt &amp; Steuer</h3>
                        <VInput id="bp_email" type="email" label="E-Mail" v-model="form.email" :error="form.errors.email" />
                        <VInput id="bp_phone" type="tel" label="Telefon" v-model="form.phone" :error="form.errors.phone" />
                        <VInput id="bp_website" label="Webseite" placeholder="korbitsch.at" v-model="form.website" :error="form.errors.website" />
                        <VInput id="bp_uid" label="UID-Nummer (optional)" placeholder="ATU12345678" v-model="form.uid_number" :error="form.errors.uid_number" />
                    </div>

                    <!-- Bank -->
                    <div class="space-y-4">
                        <h3 class="font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500">Bank &amp; Zahlung</h3>
                        <VInput id="bp_bank" label="Bank" placeholder="N26" v-model="form.bank_name" :error="form.errors.bank_name" />
                        <VInput id="bp_holder" label="Kontoinhaber" v-model="form.account_holder" :error="form.errors.account_holder" />
                        <VInput id="bp_iban" label="IBAN" placeholder="AT61 1904 3002 3457 3201" v-model="form.iban" :error="form.errors.iban" />
                        <VInput id="bp_bic" label="BIC" placeholder="BKAUATWW" v-model="form.bic" :error="form.errors.bic" />
                        <div class="grid grid-cols-2 gap-3">
                            <VInput id="bp_terms" label="Zahlungsziel" suffix="Tage" v-model="form.payment_terms_days" :error="form.errors.payment_terms_days" />
                            <VInput id="bp_tax" label="USt. Standard" suffix="%" v-model="form.default_tax_rate" :error="form.errors.default_tax_rate" />
                        </div>
                    </div>
                </div>

                <!-- Rechnungsfarbe -->
                <div class="mt-8 border-t border-gray-200 pt-6 dark:border-white/[0.08]">
                    <h3 class="flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.18em] text-gray-500"><SwatchIcon class="h-4 w-4" />Rechnungsfarbe</h3>

                    <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div>
                            <div class="flex flex-wrap gap-2.5">
                                <button v-for="preset in presets" :key="preset.value" type="button"
                                    class="relative flex h-9 w-9 items-center justify-center rounded-full ring-offset-2 ring-offset-white transition hover:scale-110 dark:ring-offset-gray-900"
                                    :class="form.invoice_color.toLowerCase() === preset.value ? 'ring-2 ring-gray-900 dark:ring-white' : ''"
                                    :style="{ backgroundColor: preset.value }"
                                    :title="preset.name"
                                    @click="form.invoice_color = preset.value">
                                    <CheckIcon v-if="form.invoice_color.toLowerCase() === preset.value" class="h-4 w-4 text-white mix-blend-difference" />
                                </button>
                            </div>

                            <div class="mt-4 flex items-end gap-3">
                                <label class="relative block h-10 w-14 shrink-0 cursor-pointer overflow-hidden rounded-lg border border-gray-200 dark:border-white/10" title="Eigene Farbe wählen">
                                    <input type="color" v-model="form.invoice_color" class="absolute -inset-2 h-[calc(100%+1rem)] w-[calc(100%+1rem)] cursor-pointer border-0 p-0">
                                </label>
                                <VInput id="bp_color" class="w-36" label="Eigene Farbe" placeholder="#1fadae" v-model="form.invoice_color" :error="form.errors.invoice_color" />
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Hellere und dunklere Abstufungen werden automatisch berechnet. Bei hellen Farben wird die Schrift auf dem Betragsbalken dunkel.</p>
                        </div>

                        <!-- Live preview of the invoice -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white text-[11px] text-gray-700 shadow-sm dark:border-white/10">
                            <div class="h-1.5" :style="{ backgroundColor: previewColor }"></div>
                            <div class="p-5">
                                <div class="text-lg font-bold text-gray-900">Rechnung <span :style="{ color: previewColor }">2026-001</span></div>
                                <div class="mt-3 flex border-b pb-1.5 text-[9px] uppercase tracking-[0.15em] text-gray-400" :style="{ borderColor: previewColor }">
                                    <span class="flex-1">Beschreibung</span><span>Betrag</span>
                                </div>
                                <div v-for="item in [['Artikel 1', '100,00'], ['Artikel 2', '200,00'], ['Artikel 3', '168,00']]" :key="item[0]" class="flex border-b border-gray-100 py-1.5">
                                    <span class="flex-1 font-semibold text-gray-900">{{ item[0] }}</span><span>{{ item[1] }}</span>
                                </div>
                                <div class="mt-3 ml-auto flex w-3/5 items-center justify-between px-3 py-2" :style="{ backgroundColor: previewColor, color: onColor }">
                                    <span class="text-[8px] uppercase tracking-[0.15em] opacity-80">Zu zahlen</span>
                                    <span class="text-sm font-bold">€ 561,60</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50/60 px-6 py-4 dark:border-white/[0.06] dark:bg-white/[0.02]">
                <VActionMessage :on="form.recentlySuccessful">Gespeichert.</VActionMessage>
                <VButton type="submit" :loading="form.processing" :disabled="form.processing">Speichern</VButton>
            </div>
        </form>
    </section>
</template>
