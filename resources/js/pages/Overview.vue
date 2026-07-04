<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useSlideshow } from '@/composables/useSlideshow';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'System Overview', href: '#' }];

type Theme = 'dark' | 'paper';

interface Slide {
    dur: number;
    theme: Theme;
}

/** Ordered slide metadata — the single source of truth for count & timing. */
const slides: Slide[] = [
    { dur: 5200, theme: 'dark' }, // Title
    { dur: 6200, theme: 'paper' }, // Six modules
    { dur: 6000, theme: 'dark' }, // Access & security
    { dur: 6200, theme: 'paper' }, // Dashboard
    { dur: 6400, theme: 'paper' }, // Billing invoices
    { dur: 6400, theme: 'paper' }, // 5-tab panel
    { dur: 6400, theme: 'paper' }, // Two-way communication
    { dur: 6000, theme: 'dark' }, // Find member
    { dur: 8000, theme: 'dark' }, // Ending
];

const { index, playing, progress, go, next, prev, toggle, restart } = useSlideshow({
    total: slides.length,
    durations: slides.map((s) => s.dur),
});

const onDark = computed(() => slides[index.value]?.theme === 'dark');
const badge = computed(
    () =>
        `${String(index.value + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`,
);

/** Staggered reveal delay matching the original explainer (base .12s, step .14s). */
const revealDelay = (step: number) => `${(0.12 + (step - 1) * 0.14).toFixed(2)}s`;

// ---- Content data (keeps the template DRY & easy to maintain) ----
const modules = [
    { num: '01', icon: 'i-login', title: 'Login & Password', text: 'Secure access and self-service password reset.' },
    { num: '02', icon: 'i-dash', title: 'Dashboard', text: 'SOA summary cards grouped by status and due date.' },
    { num: '03', icon: 'i-bill', title: 'Billing Invoices', text: 'View, filter, update status, and download PDFs.' },
    { num: '04', icon: 'i-concern', title: 'Concerns', text: 'Submit and track billing or member inquiries.' },
    { num: '05', icon: 'i-remit', title: 'Remittance Advices', text: 'Upload proof of payment against invoices.' },
    { num: '06', icon: 'i-member', title: 'Find Member', text: 'Search policy, claim, and enrollment records.' },
];

const loginSteps = [
    'Go to esoa.valucarehealth.com',
    'Enter your username (e.g. JDELACRUZ)',
    'Enter your password',
    'Optional: tick “Remember me”',
    'Click Log In',
];

const resetSteps = [
    'Click “Forgot password?”',
    'Enter your registered email',
    'Click “Email password reset link”',
    'Open your inbox',
    'Click the link and set a new password',
];

const statCards = [
    { label: 'Past Due', sub: 'Exceeded due date', variant: '' },
    { label: 'Due ≤ 30 days', sub: 'Nearest deadlines', variant: '' },
    { label: 'Due ≤ 60 days', sub: 'Approaching', variant: '' },
    { label: 'Due ≤ 90 days', sub: 'Mid-term', variant: '' },
    { label: 'Due ≤ 120 days', sub: 'Longer lead', variant: '' },
    { label: 'Due > 120 days', sub: 'Longest lead time', variant: '' },
    { label: 'Endorsed', sub: 'Endorsed for payment', variant: 'gold' },
    { label: 'Disputed', sub: 'Under dispute', variant: 'dk' },
];

const billingActions = [
    { icon: 'i-filter', title: 'Filter & search ', text: '— by branch, status, SOA number, and date ranges (combinable).' },
    { icon: 'i-edit', title: 'Update status ', text: '— set an invoice to Endorsed or Disputed.' },
    { icon: 'i-pdf', title: 'Download files ', text: '— open the billing PDF or Excel breakdown.' },
    { icon: 'i-eye', title: 'Inspect details ', text: '— open any row for a 5-tab side panel.' },
];

const billingColumns = [
    { title: 'Billing Invoice ', text: '— BI-000063966' },
    { title: 'Account / Branch ', text: '— account and its branch' },
    { title: 'Bill Date ', text: '— when it was generated' },
    { title: 'Due In ', text: '— Due Today, Past Due…' },
    { title: 'Status ', text: '— Unpaid · Endorsed · Paid · Disputed' },
    { title: 'Actions ', text: '— edit status · view PDF' },
];

const detailTabs = [
    { icon: 'i-list', title: 'Details', text: 'Full fields, period coverage, amount, plus PDF & Excel.' },
    { icon: 'i-member', title: 'Account / Branch Members', text: 'Members under this branch; search by policy or name.' },
    { icon: 'i-concern', title: 'Concerns', text: 'Concerns linked to this invoice. View-only.' },
    { icon: 'i-remit', title: 'Remittance Advices', text: 'Payments submitted for this invoice. View-only.' },
    { icon: 'i-hist', title: 'SOA Activities', text: 'Audit trail: who viewed or changed it, and when.' },
];

const concernSteps = [
    { title: 'Link an invoice ', text: '— optionally tie it to a specific bill.' },
    { title: 'Pick a type ', text: '— Scanned · Member · Billing · Other.' },
    { title: 'Describe & attach ', text: '— required title & details, optional file.' },
    { title: 'Track status ', text: '— follow resolution from the list.' },
];

const remittanceSteps = [
    { title: 'Enter payment ', text: '— deposit date & mode (Bank, Online, Check, Cash…).' },
    { title: 'Link invoice(s) ', text: '— attach the payment to what it covers.' },
    { title: 'Upload 3 files ', text: '— image, PDF, and Excel of the remittance.' },
    { title: 'Save & record ', text: '— logged with who uploaded and when.' },
];

const memberFields = ['Policy Number', 'Last Name', 'First Name', 'Batch Number', 'Account Code', 'Company Name'];

const endingPills = ['Centralized & secure', 'Transparent', 'Actionable', 'Complete records'];
</script>

<template>
    <Head title="System Overview" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="esoa-overview flex h-full flex-1 flex-col gap-4 p-4">
            <!-- SVG symbol defs (defined once, reused everywhere) -->
            <svg width="0" height="0" style="position: absolute" aria-hidden="true">
                <defs>
                    <symbol id="i-login" viewBox="0 0 512 512"><path d="M416 448h-84c-6.6 0-12-5.4-12-12v-40c0-6.6 5.4-12 12-12h84c17.7 0 32-14.3 32-32V160c0-17.7-14.3-32-32-32h-84c-6.6 0-12-5.4-12-12V76c0-6.6 5.4-12 12-12h84c53 0 96 43 96 96v192c0 53-43 96-96 96zM167 71.6l19.5-19.5c9.4-9.4 24.6-9.4 33.9 0L419.5 251.7c9.4 9.4 9.4 24.6 0 33.9L220.4 484.6c-9.4 9.4-24.6 9.4-33.9 0L167 465.1c-9.5-9.5-9.3-25 .4-34.3L295.3 320H24c-13.3 0-24-10.7-24-24v-32c0-13.3 10.7-24 24-24h271.3L167.4 105.9c-9.8-9.3-10-24.8-.4-34.3z" /></symbol>
                    <symbol id="i-dash" viewBox="0 0 512 512"><path d="M0 32C0 14.3 14.3 0 32 0H192c17.7 0 32 14.3 32 32V192c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32V32zM0 320c0-17.7 14.3-32 32-32H192c17.7 0 32 14.3 32 32V480c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32V320zM288 32c0-17.7 14.3-32 32-32H480c17.7 0 32 14.3 32 32V192c0 17.7-14.3 32-32 32H320c-17.7 0-32-14.3-32-32V32zm0 288c0-17.7 14.3-32 32-32H480c17.7 0 32 14.3 32 32V480c0 17.7-14.3 32-32 32H320c-17.7 0-32-14.3-32-32V320z" /></symbol>
                    <symbol id="i-bill" viewBox="0 0 384 512"><path d="M64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V160H256c-17.7 0-32-14.3-32-32V0H64zM256 0V128H384L256 0zM176 88c0-8.8 7.2-16 16-16s16 7.2 16 16v6.4c8.5 1.9 16.4 4.7 22.4 6.7 8.4 2.8 12.9 11.9 10.1 20.2s-11.9 12.9-20.2 10.1c-4.6-1.5-9.1-3.1-13.7-4.4-9.3-2.7-18.5-1.5-25 2.5-6 3.6-8.4 8.3-8.9 12.4-.5 3.9 .5 6.4 1.9 8.4 1.6 2.3 4.3 4.6 8.9 7.2 10.4 5.9 24.6 9.8 36.5 13.1l1.4 .4c11 3 23.7 6.6 33.2 12.5 5.4 3.4 10.9 8.1 14.7 14.8 3.9 6.9 5.5 14.6 4.5 22.9-2.2 18.7-16.6 30.4-33.2 34.4v6.4c0 8.8-7.2 16-16 16s-16-7.2-16-16v-6.4c-10.7-2.6-20.5-6.4-28.6-9.6-8.2-3.3-12.2-12.6-8.9-20.8s12.6-12.2 20.8-8.9c1.2 .5 2.4 .9 3.6 1.4 6.4 2.5 12.6 5 18.2 6.4 9.9 2.4 18.4 1 24-2.2 5.6-3.2 8.4-7.7 9-13 .4-3.7-.4-6.2-1.7-8.1-1.5-2.2-4.1-4.5-8.5-7.2-10.2-6.3-24.2-10.4-36.1-13.7l-2.1-.6c-10.8-3-22.9-6.4-32.1-11.9-5.3-3.2-10.8-7.8-14.7-14.4-4-6.8-5.7-14.5-4.7-22.9 2.2-18.4 16.5-30.4 33-34.5V88z" /></symbol>
                    <symbol id="i-concern" viewBox="0 0 512 512"><path d="M256 448c141.4 0 256-93.1 256-208S397.4 32 256 32S0 125.1 0 240c0 45.1 17.7 86.8 47.7 120.9c-1.9 24.5-11.4 46.3-21.4 62.9c-5.5 9.2-11.1 16.6-15.2 21.6c-2.1 2.5-3.7 4.4-4.9 5.7c-.6 .6-1 1.1-1.3 1.4l-.3 .3c0 0 0 0 0 0c0 0 0 0 0 0s0 0 0 0s0 0 0 0c-4.6 4.6-5.9 11.4-3.4 17.4c2.5 6 8.3 9.9 14.8 9.9c28.7 0 57.6-8.9 81.6-19.3c22.9-10 42.4-21.9 54.3-30.6c31.8 11.5 67 17.9 104.1 17.9z" /></symbol>
                    <symbol id="i-remit" viewBox="0 0 576 512"><path d="M64 64C28.7 64 0 92.7 0 128V384c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm48 160H464c8.8 0 16 7.2 16 16v96c0 8.8-7.2 16-16 16H112c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm-16-64c0-8.8 7.2-16 16-16H208c8.8 0 16 7.2 16 16s-7.2 16-16 16H112c-8.8 0-16-7.2-16-16z" /></symbol>
                    <symbol id="i-member" viewBox="0 0 640 512"><path d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192h42.7c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0H21.3C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7h42.7C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3H405.3zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 456.7C128 393.3 179.3 342 242.7 342H397.3C460.7 342 512 393.3 512 456.7c0 12.9-10.5 23.3-23.3 23.3H151.3C138.5 480 128 469.6 128 456.7z" /></symbol>
                    <symbol id="i-lock" viewBox="0 0 448 512"><path d="M144 144v48H304V144c0-44.2-35.8-80-80-80s-80 35.8-80 80zM80 192V144C80 64.5 144.5 0 224 0s144 64.5 144 144v48h16c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V256c0-35.3 28.7-64 64-64H80z" /></symbol>
                    <symbol id="i-filter" viewBox="0 0 512 512"><path d="M3.9 54.9C10.5 40.9 24.5 32 40 32H472c15.5 0 29.5 8.9 36.1 22.9s4.6 30.5-5.2 42.5L320 320.9V448c0 12.1-6.8 23.2-17.7 28.6s-23.8 4.3-33.5-3l-64-48c-8.1-6-12.8-15.5-12.8-25.6V320.9L9 97.3C-.7 85.4-2.8 68.8 3.9 54.9z" /></symbol>
                    <symbol id="i-edit" viewBox="0 0 512 512"><path d="M441.9 39.9l30.2 30.2c15.6 15.6 15.6 40.9 0 56.6L440.1 158.7l-86.8-86.8 31.9-31.9c15.6-15.6 40.9-15.6 56.6 0zM320.3 104.9l86.8 86.8L200.9 397.9c-4.3 4.3-9.7 7.5-15.6 9.2l-95.4 27.3c-5.7 1.6-11.8 .1-16-4.1s-5.8-10.3-4.1-16l27.3-95.4c1.7-5.9 4.9-11.3 9.2-15.6L320.3 104.9z" /></symbol>
                    <symbol id="i-eye" viewBox="0 0 576 512"><path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z" /></symbol>
                    <symbol id="i-pdf" viewBox="0 0 512 512"><path d="M0 64C0 28.7 28.7 0 64 0H224V128c0 17.7 14.3 32 32 32H384V304H176c-35.3 0-64 28.7-64 64V512H64c-35.3 0-64-28.7-64-64V64zm384 64H256V0L384 128zM176 352h32c30.9 0 56 25.1 56 56s-25.1 56-56 56H192v32c0 8.8-7.2 16-16 16s-16-7.2-16-16V448 368c0-8.8 7.2-16 16-16zm32 80c13.3 0 24-10.7 24-24s-10.7-24-24-24H192v48h16zm96-80h32c26.5 0 48 21.5 48 48v64c0 26.5-21.5 48-48 48H304c-8.8 0-16-7.2-16-16V368c0-8.8 7.2-16 16-16zm32 128c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H320v96h16zm80-112c0-8.8 7.2-16 16-16h48c8.8 0 16 7.2 16 16s-7.2 16-16 16H448v32h32c8.8 0 16 7.2 16 16s-7.2 16-16 16H448v48c0 8.8-7.2 16-16 16s-16-7.2-16-16V432 368z" /></symbol>
                    <symbol id="i-list" viewBox="0 0 512 512"><path d="M40 48C26.7 48 16 58.7 16 72v48c0 13.3 10.7 24 24 24H88c13.3 0 24-10.7 24-24V72c0-13.3-10.7-24-24-24H40zm192 16c-17.7 0-32 14.3-32 32s14.3 32 32 32H480c17.7 0 32-14.3 32-32s-14.3-32-32-32H232zm0 160c-17.7 0-32 14.3-32 32s14.3 32 32 32H480c17.7 0 32-14.3 32-32s-14.3-32-32-32H232zm0 160c-17.7 0-32 14.3-32 32s14.3 32 32 32H480c17.7 0 32-14.3 32-32s-14.3-32-32-32H232zM16 232v48c0 13.3 10.7 24 24 24H88c13.3 0 24-10.7 24-24V232c0-13.3-10.7-24-24-24H40c-13.3 0-24 10.7-24 24zM40 368c-13.3 0-24 10.7-24 24v48c0 13.3 10.7 24 24 24H88c13.3 0 24-10.7 24-24V392c0-13.3-10.7-24-24-24H40z" /></symbol>
                    <symbol id="i-hist" viewBox="0 0 512 512"><path d="M75 75L41 41C25.9 25.9 0 36.6 0 57.9V168c0 13.3 10.7 24 24 24H134.1c21.4 0 32.1-25.9 17-41l-30.8-30.8C155 85.5 203 64 256 64c106 0 192 86 192 192s-86 192-192 192c-40.8 0-78.6-12.7-109.7-34.4c-14.5-10.1-34.4-6.6-44.6 7.9s-6.6 34.4 7.9 44.6C151.2 495 201.7 512 256 512c141.4 0 256-114.6 256-256S397.4 0 256 0C185.3 0 121.3 28.7 75 75zm181 53c-13.3 0-24 10.7-24 24V256c0 6.4 2.5 12.5 7 17l72 72c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-65-65V152c0-13.3-10.7-24-24-24z" /></symbol>
                    <symbol id="i-check" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z" /></symbol>
                    <symbol id="i-search" viewBox="0 0 512 512"><path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" /></symbol>
                    <symbol id="i-prev" viewBox="0 0 512 512"><path d="M459.5 440.6c9.5 7.9 22.8 9.7 34.1 4.4s18.4-16.6 18.4-29V96c0-12.4-7.2-23.7-18.4-29s-24.5-3.6-34.1 4.4L288 214.3V256v41.7L459.5 440.6zM256 352V256 128 96c0-12.4-7.2-23.7-18.4-29s-24.5-3.6-34.1 4.4l-192 160C4.2 237.5 0 246.5 0 256s4.2 18.5 11.5 24.6l192 160c9.5 7.9 22.8 9.7 34.1 4.4s18.4-16.6 18.4-29V352z" /></symbol>
                    <symbol id="i-next" viewBox="0 0 512 512"><path d="M52.5 440.6c-9.5 7.9-22.8 9.7-34.1 4.4S0 428.4 0 416V96C0 83.6 7.2 72.3 18.4 67s24.5-3.6 34.1 4.4L224 214.3V256v41.7L52.5 440.6zM256 352V256 128 96c0-12.4 7.2-23.7 18.4-29s24.5-3.6 34.1 4.4l192 160c7.3 6.1 11.5 15.1 11.5 24.6s-4.2 18.5-11.5 24.6l-192 160c-9.5 7.9-22.8 9.7-34.1 4.4s-18.4-16.6-18.4-29V352z" /></symbol>
                    <symbol id="i-play" viewBox="0 0 384 512"><path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80V432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z" /></symbol>
                    <symbol id="i-pause" viewBox="0 0 320 512"><path d="M48 64C21.5 64 0 85.5 0 112V400c0 26.5 21.5 48 48 48H80c26.5 0 48-21.5 48-48V112c0-26.5-21.5-48-48-48H48zm192 0c-26.5 0-48 21.5-48 48V400c0 26.5 21.5 48 48 48h32c26.5 0 48-21.5 48-48V112c0-26.5-21.5-48-48-48H240z" /></symbol>
                    <symbol id="i-replay" viewBox="0 0 512 512"><path d="M463.5 224H472c13.3 0 24-10.7 24-24V72c0-9.7-5.8-18.5-14.8-22.2s-19.3-1.7-26.2 5.2L413.4 96.6c-87.6-86.5-228.7-86.2-315.8 1c-87.5 87.5-87.5 229.3 0 316.8s229.3 87.5 316.8 0c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0c-62.5 62.5-163.8 62.5-226.3 0s-62.5-163.8 0-226.3c62.2-62.2 162.7-62.5 225.3-1L327 183c-6.9 6.9-8.9 17.2-5.2 26.2s12.5 14.8 22.2 14.8H463.5z" /></symbol>
                </defs>
            </svg>

            <div class="frame">
                <div class="stage" :class="{ 'on-dark': onDark }">
                    <!-- 1 · TITLE -->
                    <section class="slide dark" :class="{ active: index === 0 }">
                        <div class="orb o1"></div>
                        <div class="orb o2"></div>
                        <div style="position: relative; z-index: 2">
                            <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">Confidential · For authorized users</p>
                            <h1 class="title reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin: 14px 0 6px">eSOA</h1>
                            <p class="subtitle reveal" :style="{ '--reveal-delay': revealDelay(3) }">Electronic Statement of Account</p>
                            <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(4) }" style="margin-top: 22px; max-width: 56ch">
                                A web-based portal for corporate clients to monitor billing invoices, track SOA statuses, raise concerns,
                                upload remittance advices, and look up member records — all in one place.
                            </p>
                            <div class="meta reveal" :style="{ '--reveal-delay': revealDelay(5) }" style="margin-top: 34px">
                                <div><div class="k">Version</div><div class="v">v2.0</div></div>
                                <div><div class="k">Date</div><div class="v">July 2026</div></div>
                            </div>
                        </div>
                    </section>

                    <!-- 2 · SIX MODULES -->
                    <section class="slide paper" :class="{ active: index === 1 }">
                        <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">The platform</p>
                        <h2 class="head reveal" :style="{ '--reveal-delay': revealDelay(1) }" style="margin-top: 6px">What eSOA covers</h2>
                        <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 10px">
                            Six modules, one login — organized around the billing lifecycle a client actually follows.
                        </p>
                        <div class="grid">
                            <div v-for="(m, i) in modules" :key="m.num" class="mcard reveal" :style="{ '--reveal-delay': revealDelay(i + 2) }">
                                <span class="num">{{ m.num }}</span>
                                <div class="chip"><svg><use :href="`#${m.icon}`" /></svg></div>
                                <h3>{{ m.title }}</h3>
                                <p>{{ m.text }}</p>
                            </div>
                        </div>
                    </section>

                    <!-- 3 · ACCESS & SECURITY -->
                    <section class="slide dark" :class="{ active: index === 2 }">
                        <div class="orb dim" style="width: 34%; left: -12%; bottom: -18%"></div>
                        <div style="position: relative; z-index: 2">
                            <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">Step one</p>
                            <h2 class="head reveal" :style="{ '--reveal-delay': revealDelay(1) }" style="margin-top: 6px">Getting in: access &amp; security</h2>
                            <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 10px">
                                Credentials are issued by your ValuCare account representative. Access begins at
                                <b style="color: #fff">esoa.valucarehealth.com</b>.
                            </p>
                            <div class="cols">
                                <div class="panel reveal" :style="{ '--reveal-delay': revealDelay(3) }">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px">
                                        <div class="dot" style="width: 40px; background: var(--ov-red)"><svg style="width: 52%; height: 52%; fill: #fff"><use href="#i-lock" /></svg></div>
                                        <h3 style="margin: 0">Logging in</h3>
                                    </div>
                                    <div v-for="(step, i) in loginSteps" :key="i" class="row" :style="i === loginSteps.length - 1 ? 'margin-bottom:0' : ''">
                                        <b>{{ i + 1 }}&nbsp;</b><span>{{ step }}</span>
                                    </div>
                                </div>
                                <div class="panel reveal" :style="{ '--reveal-delay': revealDelay(4) }">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px">
                                        <div class="dot" style="width: 40px; background: var(--ov-red)"><svg style="width: 52%; height: 52%; fill: #fff"><use href="#i-lock" /></svg></div>
                                        <h3 style="margin: 0">Resetting a password</h3>
                                    </div>
                                    <div v-for="(step, i) in resetSteps" :key="i" class="row" :style="i === resetSteps.length - 1 ? 'margin-bottom:0' : ''">
                                        <b>{{ i + 1 }}&nbsp;</b><span>{{ step }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 4 · DASHBOARD -->
                    <section class="slide paper" :class="{ active: index === 3 }">
                        <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">The snapshot</p>
                        <h2 class="head reveal" :style="{ '--reveal-delay': revealDelay(1) }" style="margin-top: 6px">Dashboard: the SOA at a glance</h2>
                        <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 10px">
                            Your landing page. Invoices are grouped into status cards with live counts — click any card to jump into a pre-filtered list.
                        </p>
                        <div class="board">
                            <div
                                v-for="(s, i) in statCards"
                                :key="s.label"
                                class="stat reveal"
                                :class="s.variant"
                                :style="{ '--reveal-delay': revealDelay(2 + Math.floor(i / 2)) }"
                            >
                                <div class="lbl">{{ s.label }}</div>
                                <div class="sub">{{ s.sub }}</div>
                            </div>
                        </div>
                        <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(6) }" style="margin-top: 22px; font-size: clamp(12px, 1.3vw, 16px)">
                            <b style="color: var(--ov-red)">Reading the board:</b> aging buckets show cash-flow urgency, while
                            <b style="color: var(--ov-gold)">Endorsed</b> and <b style="color: var(--ov-ink)">Disputed</b> track where each invoice sits.
                        </p>
                    </section>

                    <!-- 5 · BILLING INVOICES -->
                    <section class="slide paper" :class="{ active: index === 4 }">
                        <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">The core workspace</p>
                        <h2 class="head reveal" :style="{ '--reveal-delay': revealDelay(1) }" style="margin-top: 6px">Billing Invoices</h2>
                        <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 10px">
                            Where clients spend most of their time — a filterable register of every statement of account, with actions on every row.
                        </p>
                        <div class="cols">
                            <div class="panel reveal" :style="{ '--reveal-delay': revealDelay(3) }">
                                <h3>What you can do</h3>
                                <div class="tag">Four core actions</div>
                                <div v-for="(a, i) in billingActions" :key="i" class="row" :style="i === billingActions.length - 1 ? 'margin-bottom:0' : ''">
                                    <div class="dot"><svg><use :href="`#${a.icon}`" /></svg></div>
                                    <div><b>{{ a.title }}</b><span>{{ a.text }}</span></div>
                                </div>
                            </div>
                            <div class="panel d reveal" :style="{ '--reveal-delay': revealDelay(4) }">
                                <h3>Every row shows</h3>
                                <div class="tag" style="color: var(--ov-gold-lt)">Six columns</div>
                                <div v-for="(c, i) in billingColumns" :key="i" class="row" :style="i === billingColumns.length - 1 ? 'margin-bottom:0' : ''">
                                    <div><b style="color: var(--ov-gold-lt)">{{ c.title }}</b><span>{{ c.text }}</span></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 6 · 5-TAB PANEL -->
                    <section class="slide paper" :class="{ active: index === 5 }">
                        <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">Drill down</p>
                        <h2 class="head reveal" :style="{ '--reveal-delay': revealDelay(1) }" style="margin-top: 6px">Inside an invoice: the 5-tab panel</h2>
                        <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 10px">
                            Click any invoice row and a detail panel slides open — a complete, single-invoice view built from five tabs.
                        </p>
                        <div class="tabs">
                            <div
                                v-for="(t, i) in detailTabs"
                                :key="t.title"
                                class="tab reveal"
                                :class="{ first: i === 0 }"
                                :style="{ '--reveal-delay': revealDelay(i + 3) }"
                            >
                                <div class="chip"><svg><use :href="`#${t.icon}`" /></svg></div>
                                <h4>{{ t.title }}</h4>
                                <p>{{ t.text }}</p>
                            </div>
                        </div>
                    </section>

                    <!-- 7 · TWO-WAY COMMUNICATION -->
                    <section class="slide paper" :class="{ active: index === 6 }">
                        <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">You act, not just view</p>
                        <h2 class="head reveal" :style="{ '--reveal-delay': revealDelay(1) }" style="margin-top: 6px">Two-way communication</h2>
                        <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 10px">Beyond viewing, clients raise concerns and submit proof of payment.</p>
                        <div class="cols">
                            <div class="panel reveal" :style="{ '--reveal-delay': revealDelay(3) }">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px">
                                    <div class="dot" style="width: 44px; background: var(--ov-red)"><svg style="width: 52%; height: 52%; fill: #fff"><use href="#i-concern" /></svg></div>
                                    <div><h3 style="margin: 0">Concerns</h3><div class="tag" style="margin: 0">Submit &amp; track inquiries</div></div>
                                </div>
                                <div v-for="(s, i) in concernSteps" :key="i" class="row" :style="i === concernSteps.length - 1 ? 'margin-bottom:0' : ''">
                                    <div class="dot"><svg><use href="#i-check" /></svg></div>
                                    <div><b>{{ s.title }}</b><span>{{ s.text }}</span></div>
                                </div>
                            </div>
                            <div class="panel reveal" :style="{ '--reveal-delay': revealDelay(4) }">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px">
                                    <div class="dot" style="width: 44px; background: var(--ov-red)"><svg style="width: 52%; height: 52%; fill: #fff"><use href="#i-remit" /></svg></div>
                                    <div><h3 style="margin: 0">Remittance Advices</h3><div class="tag" style="margin: 0">Upload proof of payment</div></div>
                                </div>
                                <div v-for="(s, i) in remittanceSteps" :key="i" class="row" :style="i === remittanceSteps.length - 1 ? 'margin-bottom:0' : ''">
                                    <div class="dot"><svg><use href="#i-check" /></svg></div>
                                    <div><b>{{ s.title }}</b><span>{{ s.text }}</span></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 8 · FIND MEMBER -->
                    <section class="slide dark" :class="{ active: index === 7 }">
                        <div class="orb dim" style="width: 30%; right: -8%; bottom: -14%"></div>
                        <div style="position: relative; z-index: 2">
                            <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">Records at hand</p>
                            <h2 class="head reveal" :style="{ '--reveal-delay': revealDelay(1) }" style="margin-top: 6px; display: flex; align-items: center; gap: 16px">
                                <span style="width: clamp(40px, 4vw, 58px); aspect-ratio: 1; border-radius: 50%; background: #fff; display: inline-grid; place-items: center">
                                    <svg style="width: 52%; height: 52%; fill: var(--ov-red)"><use href="#i-search" /></svg>
                                </span>
                                Find Member
                            </h2>
                            <p class="lead reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 10px">
                                Look up any member under your corporate account to verify enrollment, policy, and claim details.
                            </p>
                            <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(2) }" style="margin-top: 20px">Search by any one field</p>
                            <div class="fields">
                                <div v-for="(f, i) in memberFields" :key="f" class="field reveal" :style="{ '--reveal-delay': revealDelay(3 + Math.floor(i / 2)) }">{{ f }}</div>
                            </div>
                            <div class="resultbar reveal" :style="{ '--reveal-delay': revealDelay(6) }">
                                <h4>Results return the full record</h4>
                                <p>
                                    Policy № · Batch № · Claim № · Full name · Account code · Company · Process date — with a
                                    <b>View Attachments</b> action for RM-Team documents (read-only).
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- 9 · ENDING -->
                    <section class="slide dark end" :class="{ active: index === 8 }">
                        <div class="orb big"></div>
                        <div class="orb o2" style="right: -6%; bottom: -16%"></div>
                        <div class="end-inner">
                            <div class="logo-badge reveal" :style="{ '--reveal-delay': revealDelay(1) }"><img src="/images/vc-ico.png" alt="Value Care logo" /></div>
                            <p class="eyebrow reveal" :style="{ '--reveal-delay': revealDelay(1) }">One portal · always current</p>
                            <h1 class="reveal" :style="{ '--reveal-delay': revealDelay(2) }">Everything, in one place.</h1>
                            <p class="tagline reveal" :style="{ '--reveal-delay': revealDelay(3) }">eSOA turns billing follow-up into a few clicks.</p>
                            <div class="pills">
                                <span v-for="(p, i) in endingPills" :key="p" class="pill reveal" :style="{ '--reveal-delay': revealDelay(i + 4) }">{{ p }}</span>
                            </div>
                            <p class="foot reveal" :style="{ '--reveal-delay': revealDelay(8) }">
                                <b>Reminder:</b> always log out after each session — especially on shared or public computers.
                            </p>
                            <p class="foot reveal" :style="{ '--reveal-delay': revealDelay(8) }" style="margin-top: 4px; opacity: 0.7">
                                Value Care Health Systems, Inc. · ICT Department · esoa.valucarehealth.com
                            </p>
                        </div>
                    </section>

                    <!-- chrome -->
                    <div class="progress"><div class="bar" :style="{ width: `${progress * 100}%` }"></div></div>
                    <div class="badge">{{ badge }}</div>
                    <div class="dots">
                        <i v-for="(s, i) in slides" :key="i" :class="{ on: i === index }" @click="go(i)"></i>
                    </div>
                </div>

                <!-- external controls -->
                <div class="controls">
                    <button class="btn" title="Previous (←)" @click="prev"><svg><use href="#i-prev" /></svg>Prev</button>
                    <button class="btn primary" title="Play / Pause (Space)" @click="toggle">
                        <svg><use :href="playing ? '#i-pause' : '#i-play'" /></svg><span>{{ playing ? 'Pause' : 'Play' }}</span>
                    </button>
                    <button class="btn" title="Next (→)" @click="next">Next<svg><use href="#i-next" /></svg></button>
                    <button class="btn" title="Restart (R)" @click="restart"><svg><use href="#i-replay" /></svg>Restart</button>
                </div>
                <p class="hint">
                    <kbd>Space</kbd> play/pause · <kbd>←</kbd><kbd>→</kbd> navigate · <kbd>R</kbd> restart · click a dot to jump
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* All variables & selectors are namespaced under .esoa-overview so nothing
   leaks into the surrounding app shell. */
.esoa-overview {
    --ov-red: #8c1420;
    --ov-red-dk: #5e0d16;
    --ov-red-deep: #43070e;
    --ov-gold: #c7962c;
    --ov-gold-lt: #e3be6b;
    --ov-ink: #2b2b2b;
    --ov-paper: #fbfaf8;
    --ov-slate: #6b6b6b;
    --ov-card: #ffffff;
    --ov-soft: #f5e7e8;
}

.frame {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
}

.stage {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 18px;
    overflow: hidden;
    background: var(--ov-paper);
    color: var(--ov-ink);
    box-shadow: 0 40px 90px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(0, 0, 0, 0.05);
}

/* ---- slides ---- */
.slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.7s ease, visibility 0.7s;
    padding: clamp(28px, 5.2%, 72px);
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.slide.active {
    opacity: 1;
    visibility: visible;
}
.slide.dark {
    background: linear-gradient(135deg, var(--ov-red-dk) 0%, var(--ov-red-deep) 100%);
    color: #fff;
}
.slide.paper {
    background: radial-gradient(90% 70% at 100% 0%, #fdf6f2 0%, var(--ov-paper) 55%);
}

/* reveal animation applied only when active */
.reveal {
    opacity: 0;
    transform: translateY(22px);
    transition:
        opacity 0.6s ease,
        transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
    transition-delay: var(--reveal-delay, 0s);
}
.slide.active .reveal {
    opacity: 1;
    transform: none;
}

.eyebrow {
    font-size: clamp(11px, 1.05vw, 14px);
    letter-spacing: 0.32em;
    font-weight: 700;
    color: var(--ov-gold);
    text-transform: uppercase;
}
.slide.paper .eyebrow {
    color: var(--ov-red);
}
h1.title {
    font-family: 'Georgia', 'Times New Roman', serif;
    font-weight: 700;
    line-height: 1.02;
    font-size: clamp(40px, 7.4vw, 104px);
    letter-spacing: -0.5px;
}
.subtitle {
    font-family: 'Georgia', serif;
    font-style: italic;
    color: var(--ov-gold-lt);
    font-size: clamp(18px, 2.7vw, 38px);
    line-height: 1.15;
}
h2.head {
    font-family: 'Georgia', 'Times New Roman', serif;
    font-weight: 700;
    font-size: clamp(26px, 3.9vw, 54px);
    line-height: 1.05;
    color: var(--ov-red);
}
.slide.dark h2.head {
    color: #fff;
}
.lead {
    font-size: clamp(13px, 1.55vw, 21px);
    line-height: 1.5;
    color: var(--ov-slate);
    max-width: 60ch;
}
.slide.dark .lead {
    color: #ead9db;
}

/* decorative circle motif */
.orb {
    position: absolute;
    border-radius: 50%;
    background: var(--ov-red);
    pointer-events: none;
}
.orb.o1 {
    width: 46%;
    aspect-ratio: 1;
    right: -11%;
    top: -24%;
    opacity: 0.9;
}
.orb.o2 {
    width: 26%;
    aspect-ratio: 1;
    right: 2%;
    bottom: -14%;
    background: rgba(199, 150, 44, 0.16);
}
.orb.dim {
    background: rgba(140, 20, 32, 0.55);
}

/* meta chips (title) */
.meta {
    display: flex;
    gap: 44px;
    margin-top: 6px;
    flex-wrap: wrap;
}
.meta .k {
    font-size: 11px;
    letter-spacing: 0.22em;
    color: var(--ov-gold);
    font-weight: 700;
    text-transform: uppercase;
}
.meta .v {
    font-size: clamp(15px, 1.5vw, 20px);
    font-weight: 700;
    color: #fff;
    margin-top: 4px;
}

/* module grid */
.grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: clamp(12px, 1.6%, 22px);
    margin-top: clamp(14px, 2.4%, 30px);
}
.mcard {
    background: var(--ov-card);
    border-radius: 14px;
    padding: clamp(14px, 1.7vw, 24px);
    box-shadow: 0 10px 26px rgba(80, 10, 18, 0.1);
    position: relative;
    overflow: hidden;
}
.mcard .num {
    position: absolute;
    top: 10px;
    right: 16px;
    font-family: 'Georgia', serif;
    font-weight: 700;
    font-size: clamp(26px, 3vw, 40px);
    color: #ead9c4;
}
.chip {
    width: clamp(38px, 3.4vw, 52px);
    aspect-ratio: 1;
    border-radius: 50%;
    background: var(--ov-soft);
    display: grid;
    place-items: center;
    margin-bottom: 12px;
}
.chip svg {
    width: 56%;
    height: 56%;
    fill: var(--ov-red);
}
.mcard h3 {
    font-size: clamp(14px, 1.4vw, 19px);
    color: var(--ov-ink);
    margin-bottom: 5px;
}
.mcard p {
    font-size: clamp(11px, 1.05vw, 14px);
    color: var(--ov-slate);
    line-height: 1.4;
}

/* dashboard status cards */
.board {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: clamp(9px, 1.1%, 16px);
    margin-top: clamp(14px, 2.6%, 30px);
}
.stat {
    border-radius: 12px;
    padding: clamp(12px, 1.4vw, 20px);
    color: #fff;
    min-height: clamp(78px, 10vw, 120px);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: var(--ov-red);
    box-shadow: 0 8px 20px rgba(80, 10, 18, 0.18);
}
.stat.gold {
    background: var(--ov-gold);
    color: #3a2a00;
}
.stat.dk {
    background: #3f3f3f;
}
.stat .lbl {
    font-size: clamp(13px, 1.35vw, 20px);
    font-weight: 700;
}
.stat .sub {
    font-size: clamp(10px, 0.95vw, 13px);
    opacity: 0.85;
}

/* two-column panels */
.cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: clamp(16px, 2.4%, 34px);
    margin-top: clamp(16px, 3%, 34px);
}
.panel {
    background: var(--ov-card);
    border-radius: 16px;
    padding: clamp(18px, 2.2vw, 34px);
    box-shadow: 0 14px 34px rgba(80, 10, 18, 0.12);
}
.panel.d {
    background: var(--ov-red-dk);
    color: #fff;
}
.panel h3 {
    font-size: clamp(17px, 1.9vw, 26px);
    color: var(--ov-red);
    margin-bottom: 4px;
}
.panel.d h3 {
    color: #fff;
}
.panel .tag {
    font-size: clamp(11px, 1.1vw, 14px);
    color: var(--ov-gold);
    font-weight: 700;
    margin-bottom: 16px;
}
.row {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: clamp(10px, 1.5vw, 18px);
}
.dot {
    flex: 0 0 auto;
    width: clamp(20px, 1.9vw, 26px);
    aspect-ratio: 1;
    border-radius: 50%;
    background: var(--ov-soft);
    display: grid;
    place-items: center;
    margin-top: 2px;
}
.dot svg {
    width: 58%;
    height: 58%;
    fill: var(--ov-red);
}
.panel.d .dot {
    background: rgba(255, 255, 255, 0.14);
}
.panel.d .dot svg {
    fill: var(--ov-gold-lt);
}
.row b {
    font-size: clamp(12px, 1.25vw, 16px);
    color: var(--ov-ink);
}
.panel.d .row b {
    color: #fff;
}
.row span {
    font-size: clamp(11px, 1.1vw, 14px);
    color: var(--ov-slate);
}
.panel.d .row span {
    color: #ead9db;
}
.slide.dark .panel .row b {
    color: var(--ov-ink);
}

/* tabs strip (5-tab panel) */
.tabs {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: clamp(8px, 1.1%, 15px);
    margin-top: clamp(16px, 3%, 34px);
}
.tab {
    background: var(--ov-card);
    border-radius: 12px;
    padding: clamp(12px, 1.5vw, 22px) clamp(8px, 1vw, 14px);
    text-align: center;
    box-shadow: 0 10px 24px rgba(80, 10, 18, 0.1);
}
.tab.first {
    background: var(--ov-red);
    color: #fff;
}
.tab .chip {
    margin: 0 auto 12px;
}
.tab.first .chip {
    background: #fff;
}
.tab.first .chip svg {
    fill: var(--ov-red);
}
.tab h4 {
    font-size: clamp(12px, 1.15vw, 16px);
    color: var(--ov-red);
    margin-bottom: 8px;
    line-height: 1.15;
}
.tab.first h4 {
    color: #fff;
}
.tab p {
    font-size: clamp(10px, 0.95vw, 12.5px);
    color: var(--ov-slate);
    line-height: 1.35;
}
.tab.first p {
    color: #f3e4e5;
}

/* find member fields */
.fields {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: clamp(10px, 1.4%, 18px);
    margin-top: clamp(14px, 2.4%, 26px);
}
.field {
    background: #fff;
    border-radius: 30px;
    padding: clamp(11px, 1.3vw, 17px) clamp(18px, 2vw, 26px);
    font-weight: 700;
    color: var(--ov-ink);
    font-size: clamp(12px, 1.2vw, 16px);
}
.resultbar {
    margin-top: clamp(14px, 2.4%, 26px);
    background: #fff;
    border-radius: 16px;
    padding: clamp(16px, 2vw, 26px);
    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.18);
}
.resultbar h4 {
    color: var(--ov-red);
    font-size: clamp(15px, 1.6vw, 22px);
    margin-bottom: 8px;
}
.resultbar p {
    color: var(--ov-ink);
    font-size: clamp(11px, 1.2vw, 15px);
}

/* ENDING slide */
.end .orb.big {
    width: 60%;
    left: -18%;
    top: -30%;
    background: rgba(140, 20, 32, 0.5);
}
.end-inner {
    position: relative;
    z-index: 2;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: clamp(10px, 1.6vw, 20px);
}
.end .logo-badge {
    width: clamp(72px, 8vw, 110px);
    aspect-ratio: 1;
    border-radius: 26px;
    background: #fff;
    display: grid;
    place-items: center;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
}
.end .logo-badge img {
    width: 62%;
    height: 62%;
    object-fit: contain;
}
.end h1 {
    font-family: 'Georgia', serif;
    font-weight: 700;
    font-size: clamp(38px, 6.4vw, 90px);
    color: #fff;
    line-height: 1;
}
.end .tagline {
    font-family: 'Georgia', serif;
    font-style: italic;
    color: var(--ov-gold-lt);
    font-size: clamp(16px, 2.3vw, 32px);
}
.pills {
    display: flex;
    gap: clamp(10px, 1.4vw, 20px);
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 6px;
}
.pill {
    border: 1px solid rgba(227, 190, 107, 0.5);
    border-radius: 30px;
    padding: clamp(8px, 1vw, 12px) clamp(14px, 1.8vw, 24px);
    color: #f3e4e5;
    font-size: clamp(11px, 1.15vw, 15px);
    font-weight: 600;
    background: rgba(255, 255, 255, 0.04);
}
.end .foot {
    margin-top: clamp(12px, 2vw, 24px);
    color: #c9aeb0;
    font-size: clamp(11px, 1.15vw, 15px);
}
.end .foot b {
    color: var(--ov-gold);
}

/* ---- controls / chrome ---- */
.progress {
    position: absolute;
    left: 0;
    top: 0;
    height: 4px;
    width: 100%;
    background: rgba(0, 0, 0, 0.1);
    z-index: 20;
}
.stage.on-dark .progress {
    background: rgba(255, 255, 255, 0.16);
}
.progress .bar {
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, var(--ov-gold), var(--ov-gold-lt));
    transition: width 0.1s linear;
}
.dots {
    position: absolute;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 9px;
    z-index: 20;
}
.dots i {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: rgba(140, 20, 32, 0.28);
    cursor: pointer;
    transition: all 0.3s;
}
.dots i.on {
    background: var(--ov-red);
    width: 26px;
    border-radius: 6px;
}
.stage.on-dark .dots i {
    background: rgba(255, 255, 255, 0.3);
}
.stage.on-dark .dots i.on {
    background: var(--ov-gold);
}
.badge {
    position: absolute;
    top: 14px;
    left: 18px;
    z-index: 20;
    font-size: 11px;
    letter-spacing: 0.2em;
    font-weight: 700;
    color: rgba(140, 20, 32, 0.6);
    text-transform: uppercase;
}
.stage.on-dark .badge {
    color: rgba(255, 255, 255, 0.75);
}

.controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 18px;
    flex-wrap: wrap;
}
.btn {
    appearance: none;
    border: 1px solid var(--ov-red);
    background: var(--ov-red);
    color: #fff;
    border-radius: 12px;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition:
        background 0.2s,
        transform 0.1s;
}
.btn:hover {
    background: var(--ov-red-dk);
}
.btn:active {
    transform: translateY(1px);
}
.btn svg {
    width: 16px;
    height: 16px;
    fill: currentColor;
}
.btn.primary {
    background: var(--ov-gold);
    color: #2a1a00;
    border-color: transparent;
}
.btn.primary:hover {
    background: var(--ov-gold-lt);
}
.hint {
    color: var(--ov-slate);
    font-size: 12px;
    text-align: center;
    margin-top: 10px;
}
kbd {
    background: rgba(140, 20, 32, 0.08);
    border: 1px solid rgba(140, 20, 32, 0.2);
    border-radius: 5px;
    padding: 1px 6px;
    font-size: 11px;
    font-family: inherit;
}

@media (max-width: 640px) {
    .meta {
        gap: 22px;
    }
    .hint {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .reveal {
        transition: none;
        opacity: 1;
        transform: none;
    }
    .slide {
        transition: opacity 0.2s;
    }
    .progress .bar {
        transition: none;
    }
}
</style>
