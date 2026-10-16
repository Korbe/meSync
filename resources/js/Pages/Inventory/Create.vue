<script setup>
import Layout from '@/Layouts/Layout.vue';
import InventoryForm from './InventoryForm.vue';

defineProps({
    categories: Array,
    gwgLimit: Number,
});
</script>

<template>
    <Layout title="Neuer Gegenstand" :backTo="route('inventory.index')">
        <InventoryForm :categories="categories" :gwgLimit="gwgLimit" />
    </Layout>
</template>
