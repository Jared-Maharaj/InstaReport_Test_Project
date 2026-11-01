<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Menu from 'primevue/menu';
import Message from 'primevue/message';
import Skeleton from 'primevue/skeleton';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Bell from '@primeicons/vue/bell';
import CheckCircle from '@primeicons/vue/check-circle';
import ChevronDown from '@primeicons/vue/chevron-down';
import Clock from '@primeicons/vue/clock';
import Eye from '@primeicons/vue/eye';
import File from '@primeicons/vue/file';
import Home from '@primeicons/vue/home';
import Inbox from '@primeicons/vue/inbox';
import Plus from '@primeicons/vue/plus';
import Refresh from '@primeicons/vue/refresh';
import Shield from '@primeicons/vue/shield';
import SignOut from '@primeicons/vue/sign-out';
import User from '@primeicons/vue/user';
import WavePulse from '@primeicons/vue/wave-pulse';
import Users from '@primeicons/vue/users';

import IncidentSummary from './IncidentSummary.vue';

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
const detailVisible = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const selectedReference = ref('');
const selectedCase = ref(null);
let latestDetailRequest = 0;

const isManager = computed(() => user.value?.role === 'AREA_MANAGER');
const isArea = computed(() => activePage.value === 'area');
const reportsEndpoint = computed(() => isArea.value ? '/api/area/reports' : '/api/cases');
const filters = ref({ reference: '', severity: null, status: null });
const appliedFilters = ref({});
const detailEndpoint = ref('/api/cases');
const severityOptions = ['HIGH', 'MEDIUM', 'LOW'].map(value => ({ label: label(value), value }));
const statusOptions = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'].map(value => ({ label: label(value), value }));

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
    if (response.status === 403) throw new Error('You don’t have permission to access Area Reports.');
    if (response.status === 422) {
        const data = await response.json();
        throw new Error(Object.values(data.errors ?? {}).flat().join(' ') || 'Check your filters and try again.');
    }
    if (response.status === 404) throw new Error('This report is no longer available.');
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
        const query = new URLSearchParams({ ...(isArea.value ? appliedFilters.value : {}), page });
        const data = await api(`${reportsEndpoint.value}?${query}`);
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

async function viewCase(reference) {
    const request = ++latestDetailRequest;
    detailEndpoint.value = reportsEndpoint.value;
    selectedReference.value = reference;
    selectedCase.value = null;
    detailError.value = '';
    detailLoading.value = true;
    detailVisible.value = true;
    try {
        const data = await api(`${detailEndpoint.value}/${encodeURIComponent(reference)}`);
        if (request === latestDetailRequest) selectedCase.value = data.data;
    } catch (exception) {
        if (request === latestDetailRequest) detailError.value = exception instanceof TypeError
            ? 'We couldn’t connect. Check your connection and try again.'
            : exception.message;
    } finally {
        if (request === latestDetailRequest) detailLoading.value = false;
    }
}

async function loadUser() {
    try {
        user.value = (await api('/api/auth/user')).data;
    } catch (exception) {
        accountError.value = 'We couldn’t load your account details. Refresh this page to try again.';
    }
}

function showPage(page) {
    if (reportBusy.value || (page === 'area' && !isManager.value)) return;
    activePage.value = page;
    reports.value = [];
    summary.value = null;
    meta.value = { current_page: 1, per_page: 15, total: 0 };
    loadCases(1);
}

function showHome() {
    showPage('home');
}

function showReport() {
    if (!reportBusy.value) activePage.value = 'report';
}

function applyFilters(clear = false) {
    if (clear) filters.value = { reference: '', severity: null, status: null };
    appliedFilters.value = Object.fromEntries(Object.entries(filters.value)
        .map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value])
        .filter(([, value]) => value));
    reports.value = [];
    meta.value = { current_page: 1, per_page: 15, total: 0 };
    loadCases(1);
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
                    <Button v-if="isManager" text severity="secondary" class="justify-start!" :class="isArea ? 'bg-blue-50! text-blue-700!' : ''" :aria-current="isArea ? 'page' : undefined" :disabled="reportBusy" @click="showPage('area')"><Users :size="19" /><span>Area Reports</span></Button>
                </nav>
                <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-500">
                    <span class="text-blue-500"><Shield :size="20" /></span>
                    Every report helps build a safer community.
                </div>
            </aside>

            <main class="min-w-0 flex-1 overflow-y-auto p-4 sm:p-7 lg:p-10">
                <div class="mx-auto flex max-w-7xl flex-col gap-7">
                    <nav aria-label="Main navigation" class="flex flex-wrap gap-2 md:hidden">
                        <Button text size="small" :class="activePage === 'home' ? 'bg-blue-50! text-blue-700!' : 'text-slate-500!'" :aria-current="activePage === 'home' ? 'page' : undefined" :disabled="reportBusy" @click="showHome"><Home :size="16" />Home</Button>
                        <Button text size="small" severity="secondary" :class="activePage === 'report' ? 'bg-blue-50! text-blue-700!' : ''" :aria-current="activePage === 'report' ? 'page' : undefined" :disabled="reportBusy" @click="showReport"><Plus :size="16" />Report incident</Button>
                        <Button v-if="isManager" text size="small" severity="secondary" :class="isArea ? 'bg-blue-50! text-blue-700!' : ''" :aria-current="isArea ? 'page' : undefined" :disabled="reportBusy" @click="showPage('area')"><Users :size="16" />Area Reports</Button>
                    </nav>

                    <Message v-if="accountError" severity="error">{{ accountError }}</Message>
                    <IncidentReport v-if="activePage === 'report'" :user="user" @home="showHome" @busy="reportBusy = $event" />
                    <template v-else>
                        <div class="flex flex-wrap items-center justify-between gap-5">
                            <div>
                                <p class="mb-2 text-xs font-semibold tracking-[0.16em] text-blue-600 uppercase">{{ isArea ? 'Community overview' : 'Your community dashboard' }}</p>
                                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">{{ isArea ? 'Area Reports' : `Welcome back, ${welcomeName}` }}</h1>
                                <p class="mt-2 text-sm text-slate-500">{{ isArea ? 'Read-only access to incident reports from across the community.' : 'Here’s an overview of the reports you’ve submitted.' }}</p>
                            </div>
                            <Button @click="showReport"><Plus :size="18" /><span>Report incident</span></Button>
                        </div>

                        <section v-if="!isArea" aria-label="Report overview" class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
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
                                <h2 id="reports-title" class="text-base font-semibold text-slate-900">{{ isArea ? 'Community reports' : 'Your submitted reports' }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ isArea ? 'High severity first, then newest reported. Filter below to narrow the results.' : 'All your reports, with the latest submissions first.' }}</p>
                            </div>
                            <Button text rounded severity="secondary" :disabled="loading" aria-label="Refresh reports" @click="loadCases(meta.current_page)"><Refresh :size="17" /></Button>
                        </div>
                        <form v-if="isArea" class="grid items-end gap-3 border-b border-slate-100 bg-slate-50/60 p-5 sm:grid-cols-2 xl:grid-cols-[1.3fr_1fr_1fr_auto]" @submit.prevent="applyFilters()">
                            <div><label for="area-reference" class="mb-2 block text-xs font-semibold text-slate-600">Case reference</label><InputText id="area-reference" v-model="filters.reference" placeholder="Search a reference…" maxlength="40" fluid /></div>
                            <div><label for="area-severity" class="mb-2 block text-xs font-semibold text-slate-600">Severity</label><Select v-model="filters.severity" input-id="area-severity" :options="severityOptions" option-label="label" option-value="value" show-clear placeholder="All severities" fluid /></div>
                            <div><label for="area-status" class="mb-2 block text-xs font-semibold text-slate-600">Status</label><Select v-model="filters.status" input-id="area-status" :options="statusOptions" option-label="label" option-value="value" show-clear placeholder="All statuses" fluid /></div>
                            <div class="flex gap-2"><Button type="submit" :disabled="loading">Apply filters</Button><Button type="button" text severity="secondary" :disabled="loading" @click="applyFilters(true)">Clear</Button></div>
                        </form>
                        <Message v-if="error" severity="error" class="m-4">
                            {{ error }}
                            <Button text size="small" @click="loadCases(meta.current_page)">Try again</Button>
                        </Message>
                        <DataTable v-model:first="first" :value="reports" data-key="reference" lazy paginator :rows="meta.per_page" :total-records="meta.total" :loading="loading" :always-show-paginator="meta.total > 0" :page-link-size="3" paginator-template="CurrentPageReport PrevPageLink PageLinks NextPageLink" current-page-report-template="{first}–{last} of {totalRecords}" table-style="min-width: 58rem" @page="loadCases($event.page + 1)">
                            <template #empty>
                                <div class="flex flex-col items-center gap-3 px-4 py-14 text-center">
                                    <span class="flex size-14 items-center justify-center rounded-full bg-blue-50 text-blue-500"><Inbox :size="28" /></span>
                                    <h3 class="font-semibold text-slate-800">{{ error ? 'Reports unavailable' : isArea ? 'No matching reports' : 'No reports yet' }}</h3>
                                    <p class="max-w-sm text-sm text-slate-500">{{ error ? 'Please retry to load reports.' : isArea ? 'Try changing or clearing your filters.' : 'Your submitted incident reports will appear here.' }}</p>
                                </div>
                            </template>
                            <Column field="reference" header="Reference">
                                <template #body="{ data }"><span class="block max-w-52 truncate font-mono text-xs font-medium text-slate-700" :title="data.reference">{{ data.reference }}</span></template>
                            </Column>
                            <Column v-if="isArea" header="Reporter"><template #body="{ data }"><div class="max-w-44 text-sm"><p class="truncate font-medium" :title="data.reporter?.name">{{ data.reporter?.name || 'Unnamed reporter' }}</p><p class="mt-1 truncate text-xs text-slate-500" :title="data.reporter?.email">{{ data.reporter?.email }}</p></div></template></Column>
                            <Column field="type" header="Type">
                                <template #body="{ data }"><Tag :severity="data.type === 'SECURITY' ? 'danger' : 'info'" :value="label(data.type)" /></template>
                            </Column>
                            <Column field="category" header="Category"><template #body="{ data }"><span class="text-sm">{{ label(data.category) }}</span></template></Column>
                            <Column field="address" header="Location"><template #body="{ data }"><span class="block max-w-44 truncate text-sm text-slate-500" :title="data.address">{{ data.address }}</span></template></Column>
                            <Column field="created_at" header="Date reported"><template #body="{ data }"><time :datetime="data.created_at" class="text-xs whitespace-nowrap text-slate-500">{{ dateFormat.format(new Date(data.created_at)) }}</time></template></Column>
                            <Column v-if="isArea" header="Severity"><template #body="{ data }"><Tag v-if="data.severity" :value="label(data.severity)" :severity="{ HIGH: 'danger', MEDIUM: 'warn', LOW: 'info' }[data.severity]" /><span v-else class="text-xs text-slate-400">Not applicable</span></template></Column>
                            <Column field="status" header="Status"><template #body="{ data }"><Tag :severity="statusSeverity[data.status] ?? 'secondary'" :value="label(data.status)" /></template></Column>
                            <Column header="View" style="width: 4rem">
                                <template #body="{ data }"><Button text rounded :aria-label="`View report ${data.reference}`" aria-controls="case-detail" :aria-expanded="detailVisible && selectedReference === data.reference" @click="viewCase(data.reference)"><Eye :size="18" /></Button></template>
                            </Column>
                        </DataTable>
                    </section>
                    </template>
                    <p class="pb-3 text-center text-xs text-slate-400">Small actions. Safer communities. Thank you for doing your part.</p>
                </div>
            </main>
        </div>
        <Dialog id="case-detail" v-model:visible="detailVisible" modal dismissable-mask block-scroll :draggable="false" header="Incident report details" class="w-[calc(100vw-2rem)] max-w-4xl" :pt="{ mask: { class: 'backdrop-blur-sm' } }">
            <div v-if="detailLoading" role="status" aria-label="Loading report" class="space-y-5 py-4"><Skeleton height="8rem" /><Skeleton height="14rem" /></div>
            <Message v-else-if="detailError" severity="error">{{ detailError }}<Button text size="small" @click="viewCase(selectedReference)">Try again</Button></Message>
            <IncidentSummary v-else-if="selectedCase" :key="selectedCase.reference" :incident="selectedCase" :user="user" :cases-endpoint="detailEndpoint" download-photos />
        </Dialog>
    </div>
</template>