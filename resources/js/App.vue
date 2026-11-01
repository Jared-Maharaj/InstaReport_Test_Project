<script setup>
import { defineAsyncComponent } from 'vue';
import SignIn from './SignIn.vue';

defineProps({ authenticated: Boolean });

const Dashboard = defineAsyncComponent(() => import('./Dashboard.vue'));
</script>

<template>
    <Dashboard v-if="authenticated" />
    <SignIn v-else />
</template>