<script setup>
import Layout from '@/Layouts/Layout.vue';
import RecurringForm from './RecurringForm.vue';

defineProps({
    categories: Array,
    intervals: Array,
    scope: String,
});
</script>

<template>
    <Layout title="Neuer Vertrag" :backTo="route('finance.contracts.index')">
        <RecurringForm :categories="categories" :intervals="intervals" :scope="scope" />
    </Layout>
</template>
