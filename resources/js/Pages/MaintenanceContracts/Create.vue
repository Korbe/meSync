<script setup>
import Layout from '@/Layouts/Layout.vue';
import ContractForm from './ContractForm.vue';

defineProps({
    customers: Array,
    types: Array,
    customerId: Number,
    prefill: Object,
});
</script>

<template>
    <Layout title="Neuer Wartungsvertrag" :backTo="route('contracts.index')">
        <ContractForm :customers="customers" :types="types" :customerId="customerId" :prefill="prefill" />
    </Layout>
</template>
