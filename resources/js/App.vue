<script setup>
import { ref } from 'vue';

const count = ref(0);
</script>

<template>
    <main>
        <h1>InstaReports</h1>
        <p>Vue is connected to Laravel.</p>

        <button type="button" @click="count++">
            Setup check: {{ count }}
        </button>
    </main>
</template>