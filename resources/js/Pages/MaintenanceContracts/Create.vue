<script setup>
import Layout from '@/Layouts/Layout.vue';
import ContractForm from './ContractForm.vue';

defineProps({
    customers: Array,
    types: Array,
    customerId: Number,
});
</script>

<template>
    <Layout title="Neuer Wartungsvertrag" :backTo="route('contracts.index')">
        <ContractForm :customers="customers" :types="types" :customerId="customerId" />
    </Layout>
</template>
