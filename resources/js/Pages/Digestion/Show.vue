<script setup>
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';

defineProps({
    digestion: Object,
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
    <Layout title="Digestion" :backTo="route('digestions.index')">
        <template v-slot:actions>
            <VButton :href="route('digestions.edit', digestion.id)">Edit</VButton>
        </template>

        <div class="bg-white dark:bg-gray-800 shadow-xs overflow-hidden shadow-xl sm:rounded-lg p-5">

            <div class="flex flex-row justify-between items-center">
                <div class="flex justify-center items-center">
                    <h1 class="mr-4 text-6xl">{{ digestion.score }}</h1>
                </div>
                <p class="text-xl">{{ formatDate(digestion.created_at) }}</p>
            </div>


            <p class="mt-5">{{ digestion.description }}</p>
        </div>
    </Layout>
</template>
