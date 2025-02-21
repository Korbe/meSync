<script setup>
import Layout from '@/Layouts/Layout.vue';
import VInput from "@/components/VInput.vue";
import VSelect from "@/components/VSelect.vue";
import VCheckbox from "@/components/VCheckbox.vue";
import VRadio from "@/components/VRadio.vue";
import VButton from "@/components/VButton.vue";
import { HomeIcon } from '@heroicons/vue/24/solid';
import EmotionChart from './EmotionChart.vue';

import { defineProps, ref, watch  } from 'vue';
import Datepicker from '@/components/Datepicker.vue';
import { router } from '@inertiajs/vue3';

// Definiere die Props, die von Inertia übergeben werden
const props = defineProps({
    averageEmotionScore: {
        type: Number,
        required: true,
    },
    timestamps: {
        type: Array,
        required: true,
    },
    scores: {
        type: Array,
        required: true,
    },
    dateRange: {
        type: Object,
        required: true,
    },
});

const dateRange = ref({
    startDate: new Date(props.dateRange?.startDate || new Date().setDate(new Date().getDate() - 7)),
    endDate: new Date(props.dateRange?.endDate || new Date())
});

watch(dateRange, (newRange) => {
    router.get('/dashboard', {
        startDate: newRange.startDate.toLocaleString(),
        endDate: newRange.endDate.toLocaleString()
    }, { preserveState: true, replace: true });
});

</script>

<template>
    <Layout title="Dashboard">
        <template v-slot:actions>
            <Datepicker align="right" v-model="dateRange" />
        </template>


        <div class="py-12">

            <EmotionChart :score="averageEmotionScore" :labels="timestamps" :data="scores" />

            <!-- <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-5">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="space-y-4 space-x-4 p-5">




                        <HomeIcon class="w-6 h-6"></HomeIcon>

                        <VButton icon="HomeIcon">Home</VButton>
                        <VButton icon="UserIcon">Profil</VButton>
                        <VButton icon="BellIcon" />

                        <VButton variant="primary" icon="CheckIcon">Speichern</VButton>
                        <VButton variant="secondary" icon="PencilIcon">Bearbeiten</VButton>
                        <VButton variant="danger" icon="TrashIcon">Löschen</VButton>

                        <VButton loading>Loading</VButton>

                        <br />


                        <VButton variant="primary">Primary</VButton>
                        <VButton variant="secondary">Secondary</VButton>
                        <VButton variant="danger">Danger</VButton>
                        <VButton disabled>Disabled</VButton>
                        <VButton loading>Loading</VButton>
                        <VButton>
                            <template #icon>
                                <svg class="fill-current text-gray-400 shrink-0" width="16" height="16"
                                    viewBox="0 0 16 16">
                                    <path
                                        d="M15 7H9V1c0-.6-.4-1-1-1S7 .4 7 1v6H1c-.6 0-1 .4-1 1s.4 1 1 1h6v6c0 .6.4 1 1 1s1-.4 1-1V9h6c.6 0 1-.4 1-1s-.4-1-1-1z" />
                                </svg>
                            </template>
                            Add Event
                        </VButton>



                        <VRadio v-model="selectedOption" name="options" value="option1" label="Option 1" />
                        <VRadio v-model="selectedOption" name="options" value="option2" label="Option 2" />

                        <VCheckbox v-model="isChecked" label="Aktiv" />

                        <VSelect id="country" label="Country" placeholder="Choose a country" :options="[
                            { value: 'italy', label: 'Italy' },
                            { value: 'usa', label: 'USA' },
                            { value: 'uk', label: 'United Kingdom' }
                        ]" v-model="selectedCountry" />

                        <VInput id="default" label="Default" />

                        <VInput id="tooltip" label="W/ Tooltip" tooltip="Hier steht ein Hinweis!" />

                        <VInput id="mandatory" label="Mandatory" mandatory />

                        <VInput id="prefix" label="W/ Prefix" prefix="USD" />

                        <VInput id="suffix" label="W/ Suffix" suffix="%" />

                        <VInput id="placeholder" label="W/ Placeholder" placeholder="Something cool..." />

                        <VInput id="icon" label="W/ Icon" :icon="'IconComponent'" />
                        <VInput id="supporting-text" label="W/ Supporting Text"
                            supportingText="Zusätzliche Informationen" />
                        <VInput id="disabled" label="Disabled" placeholder="Something cool..." disabled />
                        <VInput id="error" label="Error" error="Dieses Feld ist erforderlich!" />
                        <VInput id="success" label="Success" success="Klingt gut!" />
                    </div>
                </div>
            </div> -->
        </div>
    </Layout>
</template>
