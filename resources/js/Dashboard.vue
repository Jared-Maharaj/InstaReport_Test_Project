<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Menu from 'primevue/menu';
import Message from 'primevue/message';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import Bell from '@primeicons/vue/bell';
import CheckCircle from '@primeicons/vue/check-circle';
import ChevronDown from '@primeicons/vue/chevron-down';
import Clock from '@primeicons/vue/clock';
import File from '@primeicons/vue/file';
import Home from '@primeicons/vue/home';
import Inbox from '@primeicons/vue/inbox';
import Plus from '@primeicons/vue/plus';
import Refresh from '@primeicons/vue/refresh';
import Shield from '@primeicons/vue/shield';
import SignOut from '@primeicons/vue/sign-out';
import User from '@primeicons/vue/user';
import WavePulse from '@primeicons/vue/wave-pulse';

const IncidentReport = defineAsyncComponent(() => import('./IncidentReport.vue'));
const activePage = ref('home');
const reportBusy = ref(false);
const user = ref(null);
const reports = ref([]);
const summary = ref(null);
const meta = ref({ current_page: 1, per_page: 15, total: 0 });
const first = ref(0);
const loading = ref(true);
const loggingOut = ref(false);
const error = ref('');
const accountError = ref('');
const profileMenu = ref(null);
let latestRequest = 0;

const name = computed(() => user.value?.name?.trim() || user.value?.email || 'Your account');
const welcomeName = computed(() => user.value?.name?.trim().split(/\s+/)[0] || 'there');
const tiles = [
    { key: 'total', label: 'Total reports', icon: File, color: 'bg-blue-50 text-blue-600' },
    { key: 'open', label: 'Open', icon: Clock, color: 'bg-amber-50 text-amber-600' },
    { key: 'in_progress', label: 'In progress', icon: WavePulse, color: 'bg-indigo-50 text-indigo-600' },
    { key: 'resolved', label: 'Resolved', icon: CheckCircle, color: 'bg-emerald-50 text-emerald-600' },
];
const statusSeverity = { OPEN: 'warn', IN_PROGRESS: 'info', RESOLVED: 'success', CLOSED: 'secondary' };
const dateFormat = new Intl.DateTimeFormat('en-ZA', { dateStyle: 'medium', timeStyle: 'short' });
const profileItems = [{ label: 'Log out', command: logout }];

function label(value) {
    return value.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

async function api(url, method = 'GET') {
    const headers = { Accept: 'application/json' };
    if (method === 'POST') {
        const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
        if (!cookie) {
            throw new Error('Your session has expired. Refresh this page to sign in again.');
        }
        headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
    }

    const response = await fetch(url, { method, headers, credentials: 'same-origin' });
    if (response.status === 401) {
        window.location.replace('/');
        throw new Error('Your session has expired. Please sign in again.');
    }
    if (!response.ok) {
        throw new Error(response.status === 419
            ? 'Your session has expired. Refresh this page and try again.'
            : 'We couldn’t complete that request. Please try again.');
    }
    return response.json();
}

async function loadCases(page = 1) {
    const request = ++latestRequest;
    first.value = (page - 1) * meta.value.per_page;
    loading.value = true;
    error.value = '';
    try {
        const data = await api(`/api/cases?page=${page}`);
        if (request !== latestRequest) return;
        reports.value = data.data;
        summary.value = data.summary;
        meta.value = data.meta;
        first.value = (data.meta.current_page - 1) * data.meta.per_page;
    } catch (exception) {
        if (request !== latestRequest) return;
        error.value = exception instanceof TypeError
            ? 'We couldn’t connect. Check your internet connection and try again.'
            : exception.message;
        first.value = (meta.value.current_page - 1) * meta.value.per_page;
    } finally {
        if (request === latestRequest) loading.value = false;
    }
}

async function loadUser() {
    try {
        user.value = (await api('/api/auth/user')).data;
    } catch (exception) {
        accountError.value = 'We couldn’t load your account details. Refresh this page to try again.';
    }
}

function showHome() {
    if (reportBusy.value) return;
    activePage.value = 'home';
    loadCases(1);
}

function showReport() {
    if (!reportBusy.value) activePage.value = 'report';
}

async function logout() {
    if (loggingOut.value || reportBusy.value) return;
    loggingOut.value = true;
    accountError.value = '';
    try {
        await api('/api/auth/logout', 'POST');
        window.location.replace('/');
    } catch (exception) {
        accountError.value = exception instanceof TypeError
            ? 'We couldn’t connect to sign you out. Please try again.'
            : exception.message;
        loggingOut.value = false;
    }
}

onMounted(() => {
    loadUser();
    loadCases();
});
</script>

<template>
    <div class="flex h-dvh flex-col bg-slate-50 font-sans text-slate-800">
        <header class="flex h-20 shrink-0 items-center justify-between gap-4 border-b border-slate-200/80 bg-white px-4 sm:px-7">
            <img src="/img/InstaReports%20Logo%20(Blue).png" alt="InstaReports" class="h-16 w-32 object-contain object-left sm:w-48">
            <div class="flex items-center gap-2 sm:gap-5">
                <Button text rounded severity="secondary" disabled aria-label="Notifications are not available yet" title="Notifications coming soon">
                    <Bell :size="20" />
                </Button>
                <div class="h-7 w-px bg-slate-200" aria-hidden="true"></div>
                <Button text severity="secondary" :disabled="loggingOut || reportBusy" aria-label="Account menu" aria-haspopup="menu" aria-controls="profile-menu" @click="profileMenu.toggle($event)">
                    <Avatar shape="circle" class="bg-blue-100! text-blue-700!"><User :size="18" /></Avatar>
                    <span class="hidden max-w-48 truncate text-sm font-semibold sm:block">{{ name }}</span>
                    <ChevronDown :size="12" />
                </Button>
                <Menu id="profile-menu" ref="profileMenu" :model="profileItems" popup>
                    <template #item="{ item, props }">
                        <a v-bind="props.action"><SignOut :size="16" /><span>{{ item.label }}</span></a>
                    </template>
                </Menu>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <aside class="hidden w-56 shrink-0 flex-col justify-between border-r border-slate-200/80 bg-white px-4 py-7 md:flex">
                <nav aria-label="Main navigation" class="flex flex-col gap-2">
                    <Button text class="justify-start!" :class="activePage === 'home' ? 'bg-blue-50! text-blue-700!' : 'text-slate-500!'" :aria-current="activePage === 'home' ? 'page' : undefined" :disabled="reportBusy" @click="showHome"><Home :size="19" /><span>Home</span></Button>
                    <Button text severity="secondary" class="justify-start!" :class="activePage === 'report' ? 'bg-blue-50! text-blue-700!' : ''" :aria-current="activePage === 'report' ? 'page' : undefined" :disabled="reportBusy" @click="showReport"><Plus :size="19" /><span>Report incident</span></Button>
                </nav>
                <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-500">
                    <span class="text-blue-500"><Shield :size="20" /></span>
                    Every report helps build a safer community.
                </div>
            </aside>

            <main class="min-w-0 flex-1 overflow-y-auto p-4 sm:p-7 lg:p-10">
                <div class="mx-auto flex max-w-7xl flex-col gap-7">
                    <nav aria-label="Main navigation" class="flex gap-2 md:hidden">
                        <Button text size="small" :class="activePage === 'home' ? 'bg-blue-50! text-blue-700!' : 'text-slate-500!'" :aria-current="activePage === 'home' ? 'page' : undefined" :disabled="reportBusy" @click="showHome"><Home :size="16" />Home</Button>
                        <Button text size="small" severity="secondary" :class="activePage === 'report' ? 'bg-blue-50! text-blue-700!' : ''" :aria-current="activePage === 'report' ? 'page' : undefined" :disabled="reportBusy" @click="showReport"><Plus :size="16" />Report incident</Button>
                    </nav>

                    <Message v-if="accountError" severity="error">{{ accountError }}</Message>
                    <IncidentReport v-if="activePage === 'report'" :user="user" @home="showHome" @busy="reportBusy = $event" />
                    <template v-else>
                        <div class="flex flex-wrap items-center justify-between gap-5">
                            <div>
                                <p class="mb-2 text-xs font-semibold tracking-[0.16em] text-blue-600 uppercase">Your community dashboard</p>
                                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Welcome back, {{ welcomeName }}</h1>
                                <p class="mt-2 text-sm text-slate-500">Here’s an overview of the reports you’ve submitted.</p>
                            </div>
                            <Button @click="showReport"><Plus :size="18" /><span>Report incident</span></Button>
                        </div>

                        <section aria-label="Report overview" class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
                            <Card v-for="tile in tiles" :key="tile.key" class="border border-slate-200/60 shadow-none!" :pt="{ body: { class: 'p-4! lg:p-5!' } }">
                                <template #content>
                                    <div class="flex items-center gap-3 lg:gap-4">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl lg:size-12" :class="tile.color"><component :is="tile.icon" :size="23" /></span>
                                        <div>
                                            <h2 class="text-xs font-medium text-slate-500 sm:text-sm">{{ tile.label }}</h2>
                                            <Skeleton v-if="loading && !summary" width="3rem" height="1.75rem" class="mt-2" />
                                            <p v-else class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ summary?.[tile.key]?.toLocaleString() ?? '—' }}</p>
                                        </div>
                                    </div>
                            </template>
                        </Card>
                    </section>

                    <section aria-labelledby="reports-title" class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm shadow-slate-200/30">
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
                            <div>
                                <h2 id="reports-title" class="text-base font-semibold text-slate-900">Your submitted reports</h2>
                                <p class="mt-1 text-xs text-slate-500">All your reports, with the latest submissions first.</p>
                            </div>
                            <Button text rounded severity="secondary" :disabled="loading" aria-label="Refresh reports" @click="loadCases(meta.current_page)"><Refresh :size="17" /></Button>
                        </div>
                        <Message v-if="error" severity="error" class="m-4">
                            {{ error }}
                            <Button text size="small" @click="loadCases(meta.current_page)">Try again</Button>
                        </Message>
                        <DataTable v-model:first="first" :value="reports" data-key="reference" lazy paginator :rows="meta.per_page" :total-records="meta.total" :loading="loading" :always-show-paginator="meta.total > 0" :page-link-size="3" paginator-template="CurrentPageReport PrevPageLink PageLinks NextPageLink" current-page-report-template="{first}–{last} of {totalRecords}" table-style="min-width: 58rem" @page="loadCases($event.page + 1)">
                            <template #empty>
                                <div class="flex flex-col items-center gap-3 px-4 py-14 text-center">
                                    <span class="flex size-14 items-center justify-center rounded-full bg-blue-50 text-blue-500"><Inbox :size="28" /></span>
                                    <h3 class="font-semibold text-slate-800">{{ error ? 'Reports unavailable' : 'No reports yet' }}</h3>
                                    <p class="max-w-sm text-sm text-slate-500">{{ error ? 'Please retry to load your reports.' : 'Your submitted incident reports will appear here.' }}</p>
                                </div>
                            </template>
                            <Column field="reference" header="Reference">
                                <template #body="{ data }"><span class="block max-w-52 truncate font-mono text-xs font-medium text-slate-700" :title="data.reference">{{ data.reference }}</span></template>
                            </Column>
                            <Column field="type" header="Type">
                                <template #body="{ data }"><Tag :severity="data.type === 'SECURITY' ? 'danger' : 'info'" :value="label(data.type)" /></template>
                            </Column>
                            <Column field="category" header="Category"><template #body="{ data }"><span class="text-sm">{{ label(data.category) }}</span></template></Column>
                            <Column field="address" header="Location"><template #body="{ data }"><span class="block max-w-44 truncate text-sm text-slate-500" :title="data.address">{{ data.address }}</span></template></Column>
                            <Column field="created_at" header="Date reported"><template #body="{ data }"><time :datetime="data.created_at" class="text-xs whitespace-nowrap text-slate-500">{{ dateFormat.format(new Date(data.created_at)) }}</time></template></Column>
                            <Column field="status" header="Status"><template #body="{ data }"><Tag :severity="statusSeverity[data.status] ?? 'secondary'" :value="label(data.status)" /></template></Column>
                        </DataTable>
                    </section>
                    </template>
                    <p class="pb-3 text-center text-xs text-slate-400">Small actions. Safer communities. Thank you for doing your part.</p>
                </div>
            </main>
        </div>
    </div>
</template>