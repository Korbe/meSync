<script setup>
import Layout from '@/Layouts/Layout.vue';
import VButton from '@/components/VButton.vue';
import CustomerForm from './CustomerForm.vue';

defineProps({
    customer: Object,
});
</script>

<template>
    <Layout :title="customer.display_name" :backTo="route('customers.index')">
        <template v-slot:actions>
            <VButton :href="route('contracts.create', { customer: customer.id })" variant="secondary" icon="WrenchScrewdriverIcon">Wartungsvertrag</VButton>
            <VButton :href="route('customers.delete', customer.id)" variant="secondary" icon="TrashIcon">Löschen</VButton>
        </template>

        <CustomerForm :customer="customer" />
    </Layout>
</template>
