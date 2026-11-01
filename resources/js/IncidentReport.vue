<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import DatePicker from 'primevue/datepicker';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Step from 'primevue/step';
import StepList from 'primevue/steplist';
import StepPanel from 'primevue/steppanel';
import StepPanels from 'primevue/steppanels';
import Stepper from 'primevue/stepper';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import ArrowLeft from '@primeicons/vue/arrow-left';
import ArrowRight from '@primeicons/vue/arrow-right';
import CheckCircle from '@primeicons/vue/check-circle';
import Copy from '@primeicons/vue/copy';
import Home from '@primeicons/vue/home';
import Images from '@primeicons/vue/images';
import Pencil from '@primeicons/vue/pencil';
import Plus from '@primeicons/vue/plus';
import Send from '@primeicons/vue/send';
import Shield from '@primeicons/vue/shield';
import Spinner from '@primeicons/vue/spinner';
import Times from '@primeicons/vue/times';
import Wrench from '@primeicons/vue/wrench';

const props = defineProps({ user: Object });
const emit = defineEmits(['home', 'busy']);
const initial = () => ({ type: null, category: null, severity: null, description: '', address: '', latitude: null, longitude: null, occurred_at: null });
const form = reactive(initial());
const step = ref(1);
const errors = ref({});
const requestError = ref('');
const pending = ref(false);
const saved = ref(null);
const copyFeedback = ref('');
const photos = ref([]);
const uploader = ref(null);
const heading = ref(null);
const categories = {
    SECURITY: ['BREAK_IN', 'THEFT', 'PHYSICAL_ATTACK', 'OTHER'],
    SERVICE_DELIVERY: ['POTHOLE', 'ELECTRICITY_OUTAGE', 'WATER_OUTAGE', 'OTHER'],
};
const types = [
    { value: 'SECURITY', label: 'Security', detail: 'Crime, threats, or personal safety', icon: Shield },
    { value: 'SERVICE_DELIVERY', label: 'Service delivery', detail: 'Roads, electricity, water, and services', icon: Wrench },
];
const steps = ['Incident details', 'Location & time', 'Photos', 'Review'];
const fieldSteps = { type: 1, category: 1, severity: 1, description: 1, address: 2, latitude: 2, longitude: 2, occurred_at: 2, photos: 3 };
const dateFormat = new Intl.DateTimeFormat('en-ZA', { dateStyle: 'medium', timeStyle: 'short' });
const categoryOptions = computed(() => (categories[form.type] ?? []).map(value => ({ label: label(value), value })));
const photoErrors = computed(() => Object.entries(errors.value).filter(([key, value]) => key.startsWith('photos') && value).map(([, value]) => value));
const details = computed(() => saved.value ?? form);
const sections = computed(() => [
    { title: 'Incident details', step: 1, rows: [
        ['Type', label(details.value.type)], ['Category', label(details.value.category)],
        ...(details.value.type === 'SECURITY' ? [['Severity', label(details.value.severity)]] : []),
        ['Description', details.value.description],
    ] },
    { title: 'Location & time', step: 2, rows: [
        ['Address', details.value.address],
        ['Coordinates', details.value.latitude != null && details.value.longitude != null ? `${details.value.latitude}, ${details.value.longitude}` : 'Not provided'],
        ['Occurred at', formatDate(details.value.occurred_at)],
        ...(saved.value ? [['Reported at', formatDate(saved.value.created_at)], ['Reported by', props.user?.name || props.user?.email || 'Your account']] : []),
    ] },
]);
const previews = computed(() => saved.value
    ? saved.value.photos.map(photo => ({ name: photo.original_name || 'Incident photo', size: photo.size, src: `/api/cases/${encodeURIComponent(saved.value.reference)}/photos/${photo.id}` }))
    : photos.value.map(file => ({ name: file.name, size: file.size, src: file.objectURL })));

watch(() => form.type, () => {
    form.category = null;
    if (form.type !== 'SECURITY') form.severity = null;
    for (const field of ['type', 'category', 'severity']) delete errors.value[field];
});

for (const field of Object.keys(initial())) {
    watch(() => form[field], () => {
        delete errors.value[field];
        if (field === 'latitude' || field === 'longitude') {
            delete errors.value.latitude;
            delete errors.value.longitude;
        }
    });
}

function label(value) {
    return value ? value.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) : 'Not provided';
}
function formatDate(value) {
    return value ? dateFormat.format(new Date(value)) : 'Not provided';
}
function sizeLabel(bytes) {
    return bytes < 1048576 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1048576).toFixed(1)} MB`;
}
function validate(target) {
    errors.value = {};
    if (target > 1) {
        if (!categories[form.type]) errors.value.type = 'Choose an incident type.';
        if (!categories[form.type]?.includes(form.category)) errors.value.category = 'Choose a category for this incident type.';
        if (form.type === 'SECURITY' && !['HIGH', 'MEDIUM', 'LOW'].includes(form.severity)) errors.value.severity = 'Choose a severity.';
        if ([...form.description.trim()].length < 10 || [...form.description.trim()].length > 5000) errors.value.description = 'Describe what happened in 10–5,000 characters.';
    }
    if (target > 2) {
        if (!form.address.trim() || [...form.address.trim()].length > 1000) errors.value.address = 'Enter a descriptive address of up to 1,000 characters.';
        for (const [field, limit, other] of [['latitude', 90, 'longitude'], ['longitude', 180, 'latitude']]) {
            if (form[field] == null && form[other] != null) errors.value[field] = 'Provide both coordinates, or leave both blank.';
            else if (form[field] != null && (!Number.isFinite(form[field]) || Math.abs(form[field]) > limit)) errors.value[field] = `Enter a number between -${limit} and ${limit}.`;
        }
        if (!(form.occurred_at instanceof Date) || Number.isNaN(form.occurred_at.getTime())) errors.value.occurred_at = 'Choose when the incident occurred.';
        else if (form.occurred_at.getTime() > Date.now()) errors.value.occurred_at = 'The incident time cannot be in the future.';
    }
    if (target > 3 && photos.value.length > 5) errors.value.photos = 'Attach up to five photos. Remove extra photos to continue.';
    return Object.keys(errors.value).length === 0;
}
async function focusStep(invalid = false) {
    await nextTick();
    heading.value?.scrollIntoView({ block: 'start' });
    if (invalid) {
        const field = Object.keys(errors.value)[0];
        const control = document.getElementById(field === 'type' ? 'type-SECURITY' : field);
        (control ?? heading.value)?.focus({ preventScroll: true });
    } else heading.value?.focus({ preventScroll: true });
}
async function goTo(target) {
    if (pending.value) return;
    requestError.value = '';
    if (target > step.value && !validate(target)) {
        step.value = fieldSteps[Object.keys(errors.value)[0].split('.')[0]] ?? step.value;
        await focusStep(true);
        return;
    }
    step.value = target;
    await focusStep();
}
function selectPhotos({ files }) {
    photos.value = [...files];
    for (const key of Object.keys(errors.value)) if (key.startsWith('photos')) delete errors.value[key];
    if (files.length > 5) errors.value.photos = 'Attach up to five photos. Remove extra photos to continue.';
}
function removePhoto(event) {
    URL.revokeObjectURL(event.file.objectURL);
    selectPhotos(event);
}
function clearPhotos() {
    for (const file of photos.value) URL.revokeObjectURL(file.objectURL);
    photos.value = [];
    uploader.value?.clear();
}
function reset() {
    clearPhotos();
    Object.assign(form, initial());
    saved.value = null;
    copyFeedback.value = '';
    errors.value = {};
    requestError.value = '';
    step.value = 1;
    focusStep();
}
async function copyReference() {
    copyFeedback.value = '';
    try {
        await navigator.clipboard.writeText(saved.value.reference);
        copyFeedback.value = 'Copied to clipboard.';
    } catch {
        copyFeedback.value = 'Copy unavailable. Select the reference and copy it manually.';
    }
}
async function submitReport() {
    if (pending.value || saved.value) return;
    if (!validate(4)) {
        step.value = fieldSteps[Object.keys(errors.value)[0].split('.')[0]] ?? 4;
        await focusStep(true);
        return;
    }
    const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
    if (!cookie) {
        requestError.value = 'Your session has expired. Keep a copy of your details, then refresh this page to sign in again.';
        return;
    }
    const body = new FormData();
    for (const field of ['type', 'category', 'description', 'address']) body.append(field, form[field].trim());
    if (form.type === 'SECURITY') body.append('severity', form.severity);
    if (form.latitude != null && form.longitude != null) {
        body.append('latitude', form.latitude);
        body.append('longitude', form.longitude);
    }
    body.append('occurred_at', form.occurred_at.toISOString());
    photos.value.forEach((file, index) => body.append(`photos[${index}]`, file, file.name));
    pending.value = true;
    emit('busy', true);
    requestError.value = '';
    try {
        const response = await fetch('/api/cases/incident/report', {
            method: 'POST', credentials: 'same-origin', body,
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) },
        });
        const data = await response.json().catch(() => null);
        if (response.status === 401) {
            window.location.replace('/');
            return;
        }
        if (response.status === 422) {
            errors.value = Object.fromEntries(Object.entries(data?.errors ?? {}).map(([field, messages]) => [field, messages[0]]));
            step.value = fieldSteps[Object.keys(errors.value)[0]?.split('.')[0]] ?? 4;
            requestError.value = 'Please correct the highlighted fields and submit again.';
            await focusStep(true);
        } else if (response.status === 413) {
            step.value = 3;
            requestError.value = 'The upload is too large for this server. Try fewer or smaller photos.';
            await focusStep();
        } else if (response.status === 419) {
            requestError.value = 'Your session has expired. Keep a copy of your details, then refresh this page to sign in again.';
        } else if (!response.ok || !data?.data?.reference) {
            requestError.value = 'We couldn’t confirm submission. Check Home for your report before trying again.';
        } else {
            saved.value = data.data;
            clearPhotos();
            await focusStep();
        }
    } catch {
        requestError.value = 'We couldn’t confirm submission. Check your connection and your reports on Home before trying again.';
    } finally {
        pending.value = false;
        emit('busy', false);
    }
}
onBeforeUnmount(clearPhotos);
</script>

<template>
    <div class="mx-auto w-full max-w-4xl">
        <header class="mb-7">
            <p class="mb-2 text-xs font-semibold tracking-[0.16em] text-blue-600 uppercase">Make a difference</p>
            <h1 ref="heading" tabindex="-1" class="text-2xl font-semibold tracking-tight text-slate-900 outline-none sm:text-3xl">{{ saved ? 'Report submitted successfully!' : 'Report an incident' }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ saved ? 'Thank you for helping make your community safer and more responsive.' : 'Tell us what happened. A few clear details can make a real difference.' }}</p>
        </header>

        <Card v-if="saved" class="border border-emerald-200 shadow-none!" :pt="{ body: { class: 'p-5! sm:p-8!' } }">
            <template #content>
                <div class="mb-7 flex items-start gap-4 rounded-xl bg-emerald-50 p-5">
                    <span class="text-emerald-500"><CheckCircle :size="38" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-emerald-800">Your case reference</p>
                        <div class="mt-2 flex items-center gap-2">
                            <p class="min-w-0 break-all font-mono text-base font-semibold text-slate-900 sm:text-xl">{{ saved.reference }}</p>
                            <Button type="button" text rounded icon-only severity="secondary" class="shrink-0" aria-label="Copy case reference" title="Copy case reference" @click="copyReference"><Copy :size="18" /></Button>
                        </div>
                        <p v-if="copyFeedback" role="status" class="mt-1 text-xs text-emerald-800">{{ copyFeedback }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2"><Tag :value="saved.status" severity="warn" /><span class="text-xs text-emerald-800">Your report has been received.</span></div>
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
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><figure v-for="photo in previews" :key="photo.src"><img :src="photo.src" :alt="photo.name" class="aspect-video w-full rounded-lg object-cover"><figcaption class="mt-2 truncate text-xs text-slate-500" :title="photo.name">{{ photo.name }}</figcaption></figure></div>
                </section>
                <div class="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
                    <Button type="button" outlined class="flex-1" @click="emit('home')"><Home :size="17" />Back to home</Button>
                    <Button type="button" class="flex-1" @click="reset"><Plus :size="17" />Report another incident</Button>
                </div>
            </template>
        </Card>

        <form v-else novalidate :aria-busy="pending" @submit.prevent="step === 4 ? submitReport() : goTo(step + 1)">
            <Stepper :value="step" linear>
                <StepList class="mb-5">
                    <Step v-for="(title, index) in steps" :key="title" :value="index + 1" :pt="{ root: { class: 'min-w-0! gap-1! px-0! lg:gap-2! lg:px-2!' }, header: { class: 'flex-col! gap-1! lg:flex-row! lg:gap-2!' }, title: { class: 'text-xs! lg:text-sm!' } }"><span class="hidden lg:inline">{{ title }}</span><span class="lg:hidden">{{ ['Details', 'Place', 'Photos', 'Review'][index] }}</span></Step>
                </StepList>
                <Card class="border border-slate-200/70 shadow-none!" :pt="{ body: { class: 'p-5! sm:p-8!' } }">
                    <template #content>
                        <Message v-if="requestError" severity="error" class="mb-6">{{ requestError }}</Message>
                        <StepPanels class="p-0!">
                            <StepPanel :value="1">
                                <h2 class="text-lg font-semibold text-slate-900">What happened?</h2>
                                <p class="mt-1 text-sm text-slate-500">Choose the incident type and tell us about it.</p>
                                <fieldset class="mt-6">
                                    <legend class="mb-3 text-sm font-semibold">Incident type <span class="text-red-500">*</span></legend>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label v-for="type in types" :key="type.value" :for="`type-${type.value}`" class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors" :class="form.type === type.value ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-blue-300'">
                                            <span class="mt-1 text-blue-600"><component :is="type.icon" :size="24" /></span>
                                            <span class="flex-1"><span class="block text-sm font-semibold">{{ type.label }}</span><span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ type.detail }}</span></span>
                                            <RadioButton v-model="form.type" :input-id="`type-${type.value}`" name="type" :value="type.value" :invalid="!!errors.type" :aria-describedby="errors.type ? 'type-error' : undefined" />
                                        </label>
                                    </div>
                                    <p v-if="errors.type" id="type-error" role="alert" class="field-error">{{ errors.type }}</p>
                                </fieldset>
                                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                                    <div><label for="category" class="field-label">Category *</label><Select v-model="form.category" input-id="category" :options="categoryOptions" option-label="label" option-value="value" :disabled="!form.type" :placeholder="form.type ? 'Choose a category' : 'Choose an incident type first'" fluid :invalid="!!errors.category" :aria-describedby="errors.category ? 'category-error' : undefined" /><p v-if="errors.category" id="category-error" role="alert" class="field-error">{{ errors.category }}</p></div>
                                    <div v-if="form.type === 'SECURITY'"><label id="severity-label" class="field-label">Severity *</label><SelectButton v-model="form.severity" :options="['HIGH', 'MEDIUM', 'LOW']" aria-labelledby="severity-label" :invalid="!!errors.severity" :aria-describedby="errors.severity ? 'severity-error' : undefined"><template #option="{ option }">{{ label(option) }}</template></SelectButton><p v-if="errors.severity" id="severity-error" role="alert" class="field-error">{{ errors.severity }}</p></div>
                                </div>
                                <div class="mt-6"><label for="description" class="field-label">Description *</label><Textarea id="description" v-model="form.description" rows="5" maxlength="5000" fluid placeholder="Describe what happened, who or what was affected, and any useful details…" :invalid="!!errors.description" :aria-describedby="errors.description ? 'description-error' : 'description-help'" /><div class="mt-2 flex justify-between gap-3 text-xs text-slate-500"><span id="description-help">10–5,000 characters. Include details for an “Other” report.</span><span class="shrink-0">{{ form.description.length }}/5,000</span></div><p v-if="errors.description" id="description-error" role="alert" class="field-error">{{ errors.description }}</p></div>
                            </StepPanel>

                            <StepPanel :value="2">
                                <h2 class="text-lg font-semibold text-slate-900">Where and when?</h2>
                                <p class="mt-1 text-sm text-slate-500">A descriptive address is enough. Coordinates are optional.</p>
                                <div class="mt-6"><label for="address" class="field-label">Address or location *</label><InputText id="address" v-model="form.address" maxlength="1000" fluid placeholder="Street address, area, or a nearby landmark" :invalid="!!errors.address" :aria-describedby="errors.address ? 'address-error' : undefined" /><p v-if="errors.address" id="address-error" role="alert" class="field-error">{{ errors.address }}</p></div>
                                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                                    <div v-for="field in ['latitude', 'longitude']" :key="field"><label :for="field" class="field-label">{{ label(field) }} <span class="font-normal text-slate-400">(optional)</span></label><InputNumber v-model="form[field]" :input-id="field" :use-grouping="false" :max-fraction-digits="7" locale="en-ZA" fluid :placeholder="field === 'latitude' ? 'e.g. -29.5380' : 'e.g. 31.2140'" :invalid="!!errors[field]" :aria-describedby="errors[field] ? `${field}-error` : 'coordinates-help'" /><p v-if="errors[field]" :id="`${field}-error`" role="alert" class="field-error">{{ errors[field] }}</p></div>
                                </div>
                                <p id="coordinates-help" class="mt-2 text-xs text-slate-500">Provide both coordinates or leave both blank. Latitude: −90 to 90. Longitude: −180 to 180.</p>
                                <div class="mt-6 sm:max-w-sm"><label for="occurred_at" class="field-label">When did the incident occur? *</label><DatePicker v-model="form.occurred_at" input-id="occurred_at" show-icon show-time hour-format="24" date-format="dd/mm/yy" :manual-input="false" :max-date="new Date()" fluid placeholder="Choose date and time" :invalid="!!errors.occurred_at" :aria-describedby="errors.occurred_at ? 'occurred_at-error' : 'time-help'" /><p id="time-help" class="mt-2 text-xs text-slate-500">Use your local time. This may differ from when you submit your report.</p><p v-if="errors.occurred_at" id="occurred_at-error" role="alert" class="field-error">{{ errors.occurred_at }}</p></div>
                            </StepPanel>

                            <StepPanel :value="3">
                                <div class="flex items-center gap-3"><h2 class="text-lg font-semibold text-slate-900">Supporting photos</h2><Tag severity="secondary" value="Optional" /></div>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500">Add up to five JPEG, PNG, or WebP images, up to 10 MB each. You can continue without photos.</p>
                                <FileUpload ref="uploader" class="mt-6" accept="image/jpeg,image/png,image/webp" multiple custom-upload :max-file-size="10485760" :show-upload-button="false" :show-cancel-button="false" :choose-button-props="{ type: 'button', outlined: true }" choose-label="Choose photos" invalid-file-size-message="{0}: exceeds the 10 MB limit." invalid-file-type-message="{0}: choose a JPEG, PNG, or WebP image." @select="selectPhotos" @remove="removePhoto">
                                    <template #content="{ files, messages, removeFileCallback }">
                                        <Message v-for="message in messages" :key="message" severity="error" class="mb-3">{{ message }}</Message>
                                        <div v-if="files.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            <figure v-for="(file, index) in files" :key="file.objectURL" class="min-w-0 overflow-hidden rounded-xl border border-slate-200">
                                                <img :src="file.objectURL" :alt="file.name" class="aspect-video w-full object-cover">
                                                <figcaption class="flex items-center gap-2 p-3"><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium" :title="file.name">{{ file.name }}</p><p class="mt-1 text-xs text-slate-500">{{ sizeLabel(file.size) }}</p></div><Button type="button" text rounded severity="danger" :aria-label="`Remove ${file.name}`" @click="removeFileCallback(index)"><Times :size="16" /></Button></figcaption>
                                            </figure>
                                        </div>
                                    </template>
                                    <template #empty><div class="flex flex-col items-center gap-3 py-10 text-center text-slate-500"><Images :size="36" /><p class="text-sm">Drag photos here, or use Choose photos above.</p><p class="text-xs">No photos? No problem — continue to review.</p></div></template>
                                </FileUpload>
                                <Message v-for="message in photoErrors" :key="message" severity="error" class="mt-3">{{ message }}</Message>
                            </StepPanel>

                            <StepPanel :value="4">
                                <h2 class="text-lg font-semibold text-slate-900">Review your report</h2>
                                <p class="mt-1 text-sm text-slate-500">Check the details below before sending your report.</p>
                                <section v-for="section in sections" :key="section.title" class="mt-6 border-b border-slate-100 pb-6">
                                    <div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-semibold">{{ section.title }}</h3><Button type="button" text size="small" :disabled="pending" :aria-label="`Edit ${section.title.toLowerCase()}`" @click="goTo(section.step)"><Pencil :size="14" />Edit</Button></div>
                                    <dl class="grid gap-4 text-sm"><div v-for="[key, value] in section.rows" :key="key" class="grid gap-1 sm:grid-cols-[9rem_1fr]"><dt class="text-slate-500">{{ key }}</dt><dd class="min-w-0 break-words whitespace-pre-wrap text-slate-800">{{ value }}</dd></div></dl>
                                </section>
                                <section class="mt-5"><div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-semibold">Photos ({{ previews.length }})</h3><Button type="button" text size="small" :disabled="pending" aria-label="Edit photos" @click="goTo(3)"><Pencil :size="14" />Edit</Button></div><p v-if="!previews.length" class="text-sm text-slate-500">No photos attached.</p><div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3"><figure v-for="photo in previews" :key="photo.src"><img :src="photo.src" :alt="photo.name" class="aspect-video w-full rounded-lg object-cover"><figcaption class="mt-2 truncate text-xs text-slate-500" :title="photo.name">{{ photo.name }} · {{ sizeLabel(photo.size) }}</figcaption></figure></div></section>
                            </StepPanel>
                        </StepPanels>
                        <div class="mt-8 flex items-center justify-between gap-3 border-t border-slate-100 pt-6">
                            <Button type="button" outlined severity="secondary" :disabled="pending" @click="step === 1 ? emit('home') : goTo(step - 1)"><ArrowLeft :size="16" />{{ step === 1 ? 'Back to home' : 'Back' }}</Button>
                            <Button v-if="step < 4" type="button" @click="goTo(step + 1)">{{ step === 3 ? 'Review report' : 'Continue' }}<ArrowRight :size="16" /></Button>
                            <Button v-else type="submit" :disabled="pending"><Spinner v-if="pending" :size="16" spin /><Send v-else :size="16" />{{ pending ? 'Submitting…' : 'Submit report' }}</Button>
                        </div>
                    </template>
                </Card>
            </Stepper>
        </form>
    </div>
</template>

<style scoped>
@reference '../css/app.css';
.field-label { @apply mb-2 block text-sm font-semibold text-slate-700; }
.field-error { @apply mt-2 text-sm text-red-600; }
</style>