<script setup>
import { computed } from 'vue';
import Button from 'primevue/button';
import Pencil from '@primeicons/vue/pencil';

const props = defineProps({ incident: { type: Object, required: true }, user: Object, review: Boolean, disabled: Boolean });
const emit = defineEmits(['edit']);
const dateFormat = new Intl.DateTimeFormat('en-ZA', { dateStyle: 'medium', timeStyle: 'short' });
const sections = computed(() => {
    const incident = props.incident;
    const reporter = incident.reporter ?? props.user;
    return [
        { title: 'Incident details', step: 1, rows: [
            ['Type', label(incident.type)], ['Category', label(incident.category)],
            ...(incident.type === 'SECURITY' ? [['Severity', label(incident.severity)]] : []),
            ['Description', incident.description],
        ] },
        { title: 'Location & time', step: 2, rows: [
            ['Address', incident.address],
            ['Coordinates', incident.latitude != null && incident.longitude != null ? `${incident.latitude}, ${incident.longitude}` : 'Not provided'],
            ['Occurred at', formatDate(incident.occurred_at)],
            ...(incident.created_at ? [['Reported at', formatDate(incident.created_at)], ['Reported by', reporter?.name || reporter?.email || 'Your account']] : []),
            ...(incident.reporter ? [['Reporter email', incident.reporter.email]] : []),
        ] },
    ];
});
function label(value) {
    return value ? value.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) : 'Not provided';
}
function formatDate(value) {
    return value ? dateFormat.format(new Date(value)) : 'Not provided';
}
</script>

<template>
    <div :class="review ? '' : 'grid gap-7 sm:grid-cols-2'">
        <section v-for="section in sections" :key="section.title" :class="review ? 'mt-6 border-b border-slate-100 pb-6' : ''">
            <div class="mb-4" :class="review ? 'flex items-center justify-between gap-3' : ''">
                <component :is="review ? 'h3' : 'h2'" class="font-semibold" :class="review ? '' : 'text-slate-900'">{{ section.title }}</component>
                <Button v-if="review" type="button" text size="small" :disabled="disabled" :aria-label="`Edit ${section.title.toLowerCase()}`" @click="emit('edit', section.step)"><Pencil :size="14" />Edit</Button>
            </div>
            <dl class="grid text-sm" :class="review ? 'gap-4' : 'gap-3'">
                <div v-for="[key, value] in section.rows" :key="key" :class="review ? 'grid gap-1 sm:grid-cols-[9rem_1fr]' : ''">
                    <dt :class="review ? 'text-slate-500' : 'text-xs text-slate-500'">{{ key }}</dt>
                    <dd class="min-w-0 break-words whitespace-pre-wrap text-slate-800" :class="review ? '' : 'mt-1'">{{ value }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>