<script setup>
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import CatalogForm from './CatalogForm.vue';

defineProps({
    item: Object,
});
</script>

<template>
    <Layout :title="item.name" :backTo="route('catalog.index')">
        <template v-slot:actions>
            <VButton :href="route('catalog.delete', item.id)" variant="secondary" icon="TrashIcon">Löschen</VButton>
        </template>

        <CatalogForm :item="item" />
    </Layout>
</template>
