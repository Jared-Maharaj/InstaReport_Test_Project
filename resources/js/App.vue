<script setup>
import { nextTick, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import ArrowRight from '@primeicons/vue/arrow-right';
import ChartLine from '@primeicons/vue/chart-line';
import CheckCircle from '@primeicons/vue/check-circle';
import Envelope from '@primeicons/vue/envelope';
import Lock from '@primeicons/vue/lock';
import MapMarker from '@primeicons/vue/map-marker';
import Shield from '@primeicons/vue/shield';
import Spinner from '@primeicons/vue/spinner';
import Users from '@primeicons/vue/users';

defineProps({ authenticated: Boolean });

const email = ref('');
const emailInput = ref(null);
const pending = ref(false);
const fieldError = ref('');
const requestError = ref('');
const confirmation = ref('');
const features = [
    { icon: Shield, title: 'Report it', text: 'Simple and secure' },
    { icon: Users, title: 'Make a difference', text: 'Help your community' },
    { icon: ChartLine, title: 'Stay informed', text: 'Find your reports' },
];

async function sendLink() {
    if (pending.value) {
        return;
    }

    clearFeedback();
    email.value = email.value.trim();

    if (!email.value || !emailInput.value.$el.checkValidity()) {
        fieldError.value = 'Enter a valid email address.';
        emailInput.value.$el.focus();
        return;
    }

    const csrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    if (!csrfCookie) {
        requestError.value = 'Your session has expired. Refresh this page and try again.';
        return;
    }

    pending.value = true;

    try {
        const response = await fetch('/api/auth/magiclink', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(csrfCookie.slice('XSRF-TOKEN='.length)),
            },
            body: JSON.stringify({ email: email.value }),
        });
        const data = await response.json().catch(() => null);

        if (response.ok) {
            confirmation.value = data?.message ?? 'If this email is registered, a sign-in link will be sent shortly.';
        } else if (response.status === 422) {
            fieldError.value = data?.errors?.email?.[0] ?? 'Enter a valid email address.';
        } else if (response.status === 429) {
            const seconds = Number(response.headers.get('Retry-After'));
            const minutes = Math.ceil(seconds / 60);
            requestError.value = seconds > 0
                ? `Please wait ${minutes} ${minutes === 1 ? 'minute' : 'minutes'} before requesting another link.`
                : 'Too many requests. Please wait a few minutes and try again.';
        } else if (response.status === 419) {
            requestError.value = 'Your session has expired. Refresh this page and try again.';
        } else {
            requestError.value = 'We couldn’t send your link right now. Please try again shortly.';
        }
    } catch {
        requestError.value = 'We couldn’t connect. Check your internet connection and try again.';
    } finally {
        pending.value = false;
    }

    if (fieldError.value) {
        await nextTick();
        emailInput.value.$el.focus();
    }
}

function clearFeedback() {
    fieldError.value = '';
    requestError.value = '';
    confirmation.value = '';
}
</script>

<template>
    <main class="h-dvh overflow-hidden font-sans">
        <div class="signin-scene relative isolate flex h-full flex-col overflow-hidden px-6 py-4 text-white sm:px-10 sm:py-6 lg:px-16" style="--community-backdrop: url('/img/Ballito%20Backdrop.webp')">
            <header class="relative z-10 flex shrink-0 items-center justify-between gap-6">
                <img src="/img/InstaReports%20Logo%20(White).png" alt="InstaReports" class="h-16 w-52 object-contain object-left md:h-20 md:w-64">
                <span class="hidden items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-medium tracking-wide sm:flex">
                    <MapMarker :size="14" aria-hidden="true" />
                    Our community. Our responsibility.
                </span>
            </header>

            <div class="relative z-10 mx-auto grid min-h-0 w-full max-w-7xl flex-1 grid-rows-[minmax(0,1fr)] items-center gap-10 py-4 md:grid-cols-[1fr_1fr] lg:grid-cols-[1.15fr_1fr] lg:gap-24">
                <section aria-labelledby="community-title" class="hidden max-w-xl md:block">
                    <p class="mb-5 flex items-center gap-3 text-xs font-semibold tracking-[0.22em] text-blue-100 uppercase">
                        <span class="h-px w-9 bg-blue-200/70" aria-hidden="true"></span>
                        A better place starts with us
                    </p>
                    <h1 id="community-title" class="text-4xl leading-[1.08] font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                        Safer communities.<br>
                        <span class="text-blue-200">Stronger together.</span>
                    </h1>
                    <p class="mt-6 max-w-sm text-base leading-relaxed text-slate-200 sm:text-lg">
                        Report an incident, raise a concern, and help make a difference where you live.
                    </p>

                    <div class="mt-10 grid grid-cols-3 gap-4 border-t border-white/20 pt-7 lg:mt-14 lg:gap-6">
                        <div v-for="feature in features" :key="feature.title">
                            <div class="mb-3 text-blue-200"><component :is="feature.icon" :size="24" aria-hidden="true" /></div>
                            <h2 class="text-xs font-semibold sm:text-sm">{{ feature.title }}</h2>
                            <p class="mt-1.5 text-xs leading-relaxed text-blue-100/80">{{ feature.text }}</p>
                        </div>
                    </div>
                </section>

                <Card class="max-h-full w-full max-w-lg overflow-y-auto justify-self-center text-slate-900 shadow-2xl shadow-slate-950/25 md:justify-self-end" :pt="{ body: { class: 'p-6! sm:p-8!' } }">
                    <template #content>
                        <section aria-labelledby="signin-title">
                            <div class="signin-emblem mb-6 flex size-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <component :is="authenticated ? CheckCircle : Envelope" :size="24" aria-hidden="true" />
                            </div>
                            <p class="signin-eyebrow mb-2 text-xs font-semibold tracking-[0.16em] text-blue-600 uppercase">Welcome to InstaReports</p>
                            <h2 id="signin-title" class="text-2xl font-semibold tracking-tight sm:text-[1.75rem]">
                                {{ authenticated ? 'You’re signed in' : 'Sign in to InstaReports' }}
                            </h2>
                            <p class="mt-3 text-sm leading-relaxed text-slate-500">
                                {{ authenticated ? 'Your secure session is active. Thank you for helping make your community a better place.' : 'Enter your email and we’ll send you a secure sign-in link. No password to remember.' }}
                            </p>

                            <form v-if="!authenticated" class="mt-8" novalidate :aria-busy="pending" @submit.prevent="sendLink">
                                <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email address</label>
                                <IconField>
                                    <InputIcon><Envelope :size="16" /></InputIcon>
                                    <InputText id="email" ref="emailInput" v-model="email" type="email" name="email" autocomplete="email" inputmode="email" placeholder="you@example.com" required maxlength="255" fluid size="large" :disabled="pending" :invalid="Boolean(fieldError)" :aria-invalid="Boolean(fieldError)" :aria-describedby="fieldError ? 'email-error' : 'email-help'" @input="clearFeedback" />
                                </IconField>
                                <p v-if="fieldError" id="email-error" role="alert" class="mt-2 text-sm text-red-600">{{ fieldError }}</p>
                                <p v-else id="email-help" class="mt-2 text-xs text-slate-500">Use the email address registered with InstaReports.</p>

                                <Button type="submit" class="mt-6 w-full" size="large" :disabled="pending">
                                    <Spinner v-if="pending" :size="16" spin aria-hidden="true" />
                                    <span>{{ pending ? 'Sending your link…' : 'Send sign-in link' }}</span>
                                    <span v-if="!pending" class="ml-auto"><ArrowRight :size="16" aria-hidden="true" /></span>
                                </Button>

                                <Message v-if="confirmation" severity="success" class="mt-5" role="status" aria-live="polite">
                                    <template #icon><CheckCircle :size="20" aria-hidden="true" /></template>
                                    <span class="block font-semibold">Check your inbox</span>
                                    <span class="mt-1 block text-sm leading-relaxed">{{ confirmation }}</span>
                                </Message>
                                <Message v-if="requestError" severity="error" class="mt-5">{{ requestError }}</Message>
                            </form>

                            <div class="signin-trust mt-8 flex items-center justify-center gap-2 border-t border-slate-100 pt-5 text-xs text-slate-500">
                                <Lock :size="14" aria-hidden="true" />
                                Secure access. One link. No password.
                            </div>
                        </section>
                    </template>
                </Card>
            </div>

            <footer class="relative z-10 hidden shrink-0 flex-wrap items-center justify-between gap-3 text-xs text-blue-100/75 md:flex">
                <p>Small actions. Real impact.</p>
                <p>Connecting communities, one report at a time.</p>
            </footer>
        </div>
    </main>
</template>

<style scoped>
.signin-scene {
    background-color: #102c4c;
    background-image: linear-gradient(105deg, rgb(7 30 55 / 94%) 0%, rgb(12 43 75 / 78%) 43%, rgb(20 56 93 / 40%) 100%), var(--community-backdrop);
    background-position: center;
    background-size: cover;
}

@media (max-height: 820px) {
    .signin-emblem,
    .signin-eyebrow {
        display: none;
    }

    .signin-scene form,
    .signin-trust {
        margin-top: 1.25rem;
    }
}
</style>