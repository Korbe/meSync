<script setup>
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';

defineProps({
    emotion: Object
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

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-5">

            <div class="flex flex-row justify-between items-center">
                <div class="flex justify-center items-center">
                    <h1 class="mr-4 text-6xl">{{ emotion.score }}</h1>
                    <div>
                        <h1 class="text-3xl">{{ emotion.primary }}</h1>
                        <h2>{{ emotion.secondary }}</h2>
                    </div>
                </div>
                <p>{{ formatDate(emotion.created_at) }}</p>
            </div>


            <p class="mt-5">{{ emotion.description }}</p>
        </div>
    </Layout>
</template>
