<script setup>
import Layout from '@/Layouts/Layout.vue';
import InvoiceForm from './InvoiceForm.vue';

defineProps({
    invoice: Object,
    customers: Array,
    catalog: Array,
    paymentTermsDays: Number,
    profileComplete: Boolean,
    contract: Object,
});
</script>

<template>
    <Layout title="Neue Rechnung" :backTo="route('invoices.index')">
        <InvoiceForm :invoice="invoice" :customers="customers" :catalog="catalog" :paymentTermsDays="paymentTermsDays" :profileComplete="profileComplete" :contract="contract" />
    </Layout>
</template>
