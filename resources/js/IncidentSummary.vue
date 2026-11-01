<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import CheckCircle from '@primeicons/vue/check-circle';
import Copy from '@primeicons/vue/copy';
import Download from '@primeicons/vue/download';

const props = defineProps({ incident: { type: Object, required: true }, user: Object, downloadPhotos: Boolean });
const copyFeedback = ref('');
const downloading = ref(null);
const downloadError = ref('');
const statusSeverity = { OPEN: 'warn', IN_PROGRESS: 'info', RESOLVED: 'success', CLOSED: 'secondary' };
const dateFormat = new Intl.DateTimeFormat('en-ZA', { dateStyle: 'medium', timeStyle: 'short' });
const sections = computed(() => [
    { title: 'Incident details', rows: [
        ['Type', label(props.incident.type)], ['Category', label(props.incident.category)],
        ...(props.incident.type === 'SECURITY' ? [['Severity', label(props.incident.severity)]] : []),
        ['Description', props.incident.description],
    ] },
    { title: 'Location & time', rows: [
        ['Address', props.incident.address],
        ['Coordinates', props.incident.latitude != null && props.incident.longitude != null ? `${props.incident.latitude}, ${props.incident.longitude}` : 'Not provided'],
        ['Occurred at', formatDate(props.incident.occurred_at)],
        ['Reported at', formatDate(props.incident.created_at)],
        ['Reported by', props.user?.name || props.user?.email || 'Your account'],
    ] },
]);
const photoUrl = (photo) => `/api/cases/${encodeURIComponent(props.incident.reference)}/photos/${photo.id}`;
const previews = computed(() => props.incident.photos.map(photo => ({ name: photo.original_name || 'Incident photo', src: photoUrl(photo) })));

function label(value) {
    return value ? value.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) : 'Not provided';
}
function formatDate(value) {
    return value ? dateFormat.format(new Date(value)) : 'Not provided';
}
function sizeLabel(bytes) {
    return bytes < 1048576 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1048576).toFixed(1)} MB`;
}
async function copyReference() {
    copyFeedback.value = '';
    try {
        await navigator.clipboard.writeText(props.incident.reference);
        copyFeedback.value = 'Copied to clipboard.';
    } catch {
        copyFeedback.value = 'Copy unavailable. Select the reference and copy it manually.';
    }
}
async function download(photo) {
    if (downloading.value !== null) return;
    downloading.value = photo.id;
    downloadError.value = '';
    try {
        const response = await fetch(photoUrl(photo), { credentials: 'same-origin' });
        if (response.status === 401) {
            window.location.replace('/');
            return;
        }
        if (!response.ok) throw new Error('Photo unavailable');
        const url = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = url;
        link.download = photo.original_name || `incident-photo-${photo.id}`;
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch {
        downloadError.value = 'We couldn’t download this photo. Please try again.';
    } finally {
        downloading.value = null;
    }
}
</script>

<template>
    <Card class="border border-emerald-200 shadow-none!" :pt="{ body: { class: 'p-5! sm:p-8!' } }">
        <template #content>
            <div class="mb-7 flex items-start gap-4 rounded-xl bg-emerald-50 p-5">
                <span class="text-emerald-500"><CheckCircle :size="38" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-emerald-800">Your case reference</p>
                    <div class="mt-2 flex items-center gap-2">
                        <p class="min-w-0 break-all font-mono text-base font-semibold text-slate-900 sm:text-xl">{{ incident.reference }}</p>
                        <Button type="button" text rounded icon-only severity="secondary" class="shrink-0" aria-label="Copy case reference" title="Copy case reference" @click="copyReference"><Copy :size="18" /></Button>
                    </div>
                    <p v-if="copyFeedback" role="status" class="mt-1 text-xs text-emerald-800">{{ copyFeedback }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2"><Tag :value="incident.status" :severity="statusSeverity[incident.status] ?? 'secondary'" /><span v-if="!downloadPhotos" class="text-xs text-emerald-800">Your report has been received.</span></div>
                </div>
            </div>
            <div class="grid gap-7 sm:grid-cols-2">
                <section v-for="section in sections" :key="section.title">
                    <h2 class="mb-4 font-semibold text-slate-900">{{ section.title }}</h2>
                    <dl class="grid gap-3 text-sm"><div v-for="[key, value] in section.rows" :key="key"><dt class="text-xs text-slate-500">{{ key }}</dt><dd class="mt-1 min-w-0 break-words whitespace-pre-wrap text-slate-800">{{ value }}</dd></div></dl>
                </section>
            </div>
            <section v-if="previews.length" class="mt-7 border-t border-slate-100 pt-6">
                <h2 class="mb-4 font-semibold">Attached photos ({{ previews.length }})</h2>
                <ul v-if="downloadPhotos" class="divide-y divide-slate-100">
                    <li v-for="photo in incident.photos" :key="photo.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0"><p class="break-all text-sm text-slate-800">{{ photo.original_name || 'Incident photo' }}</p><p class="mt-1 text-xs text-slate-500">{{ sizeLabel(photo.size) }}</p></div>
                        <Button type="button" text rounded :disabled="downloading !== null" :aria-label="`Download ${photo.original_name || 'incident photo'}`" @click="download(photo)"><Download :size="18" /></Button>
                    </li>
                </ul>
                <Message v-if="downloadError" severity="error" class="mt-3">{{ downloadError }}</Message>
                <div v-else-if="!downloadPhotos" class="grid grid-cols-2 gap-3 sm:grid-cols-3"><figure v-for="photo in previews" :key="photo.src"><img :src="photo.src" :alt="photo.name" class="aspect-video w-full rounded-lg object-cover"><figcaption class="mt-2 truncate text-xs text-slate-500" :title="photo.name">{{ photo.name }}</figcaption></figure></div>
            </section>
            <div v-if="$slots.actions" class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
                <slot name="actions" />
            </div>
        </template>
    </Card>
</template>