<script setup>
import Layout from '@/Layouts/Layout.vue';
import ChartCard from '@/components/Charts/ChartCard.vue';
import VButton from '@/components/VButton.vue';

defineProps({
    emotion: Object,
    primaryLabel: String,
    secondaryLabel: String,
    weeklyEmotions: Array,
    week_timestamps: Array,
    week_scores: Array,
    average_week_score: Number,
});

function formatDate(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}.${month}.${year}`;
}

</script>
<template>
    <Layout title="Emotion" :backTo="route('emotions.index')">
        <template v-slot:actions>
            <VButton :href="route('emotions.edit', emotion.id)">Edit</VButton>
        </template>

        <div class="bg-white dark:bg-gray-800 shadow-xs overflow-hidden shadow-xl sm:rounded-lg p-5">

            <ChartCard title="Wochenverfassung" subtitle="Einträge aus der Woche" :labels="week_timestamps" :data="week_scores" :score="average_week_score" />

            <div class="flex flex-row justify-between items-center">
                <div class="flex justify-center items-center">
                    <h1 class="mr-4 text-6xl">{{ emotion.score }}</h1>
                    <div>
                        <h1 class="text-3xl">{{ primaryLabel }}</h1>
                        <h2>{{ secondaryLabel }}</h2>
                    </div>
                </div>
                <p class="text-xl">{{ formatDate(emotion.created_at) }}</p>
            </div>


            <p class="mt-5">{{ emotion.description }}</p>
        </div>
    </Layout>
</template>
