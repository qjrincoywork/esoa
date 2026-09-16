<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { overview } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'FAQs', href: '#' }];

interface FaqEntry {
    /** The question as shown to the user. */
    q: string;
    /** The answer paragraph. */
    a: string;
    /** Optional enumerated values that belong to the answer. */
    bullets?: string[];
}

interface FaqTopic {
    id: string;
    icon: string;
    title: string;
    blurb: string;
    entries: FaqEntry[];
}

/**
 * Single source of truth for the FAQ. Everything below — numbering, counts,
 * topic chips, the search index — is derived from this list, so adding or
 * rewording an entry here is the only edit needed.
 */
const topics: FaqTopic[] = [
    {
        id: 'login-access',
        icon: 'i-login',
        title: 'Login & Access',
        blurb: 'Getting into the portal and recovering your credentials.',
        entries: [
            {
                q: 'How do I access the eSOAv2 portal?',
                a: 'Open your browser and go to esoa.valucarehealth.com, then enter your assigned username and password.',
            },
            {
                q: 'I forgot my password. What should I do?',
                a: 'Click “Forgot Password?” on the login page, enter your registered email address, and follow the password reset instructions sent to your email.',
            },
            {
                q: 'I did not receive my login credentials. Who should I contact?',
                a: 'Please contact your assigned ValuCare Account Representative for assistance.',
            },
            {
                q: 'Can I stay logged in on my device?',
                a: 'Yes. You may enable the “Remember Me” option to re-login again once the session has run out on your device.',
            },
        ],
    },
    {
        id: 'dashboard',
        icon: 'i-dash',
        title: 'Dashboard',
        blurb: 'Reading the summary cards and jumping into the details.',
        entries: [
            {
                q: 'What information can I see on the Dashboard?',
                a: 'The Dashboard provides an overview of billing invoices grouped by aging and statuses such as Due, Past Due, Endorsed, Disputed, Unpaid, and Paid.',
            },
            {
                q: 'What happens when I click a Dashboard status card?',
                a: 'The system automatically opens the Billing Invoices page and displays invoices matching the selected status.',
            },
            {
                q: 'What does “Past Due” mean?',
                a: 'Past Due invoices are invoices that have exceeded their due date and are categorized according to the number of overdue days.',
            },
        ],
    },
    {
        id: 'billing-invoices',
        icon: 'i-bill',
        title: 'Billing Invoices',
        blurb: 'Searching, filtering, downloading, and updating statuses.',
        entries: [
            {
                q: 'How can I search for a specific invoice?',
                a: 'Use the SOA Number / Billing Invoice filter and enter the invoice number (for example, BI-000063966).',
            },
            {
                q: 'Can I filter invoices by account, branch, or status?',
                a: 'Yes. The Billing Invoices module allows filtering by Account Type, Account, Branch, Status, Bill Type, Bill Date Range, and Due Date Range.',
            },
            {
                q: 'How do I download a billing invoice?',
                a: 'Click the Billing PDF File icon, or open the invoice details and download the PDF from the Details tab.',
            },
            {
                q: 'Can I download the invoice breakdown in Excel format?',
                a: 'Yes. If available, the Details tab contains downloadable XLS/XLSX files for invoice breakdowns.',
            },
            {
                q: 'Can I update the status of an invoice?',
                a: 'Yes. Client users may update an invoice to Endorsed or Disputed using the Edit icon.',
            },
            {
                q: 'Why can’t I change an invoice to Paid?',
                a: 'Only Endorsed and Disputed statuses are available for client-side updates. Other status changes must be coordinated with ValuCare.',
            },
            {
                q: 'What information is available in the invoice details panel?',
                a: 'The panel contains five tabs:',
                bullets: ['Details', 'Account / Branch Members', 'Concerns', 'Remittance Advices', 'SOA Activities'],
            },
            {
                q: 'What is the SOA Activities tab used for?',
                a: 'It displays the audit trail of all actions performed on an invoice, including status changes and invoice views.',
            },
        ],
    },
    {
        id: 'concerns',
        icon: 'i-concern',
        title: 'Concerns',
        blurb: 'Raising inquiries and following them through to resolution.',
        entries: [
            {
                q: 'How do I submit a concern?',
                a: 'Go to the Concerns module or to a specific invoice’s Concerns tab, click Submit Concern, complete the required fields, and click Save.',
            },
            {
                q: 'Is selecting a billing invoice required when submitting a concern?',
                a: 'Yes. Linking a billing invoice is required so the concern can easily be identified against the right bill.',
            },
            {
                q: 'What types of concerns can I submit?',
                a: 'Available concern types include:',
                bullets: ['Scanned Documents', 'Member Concerns', 'Billing Concerns', 'Other Concerns'],
            },
            {
                q: 'Can I attach supporting documents to a concern?',
                a: 'Yes. Supporting attachments may be uploaded when submitting a concern.',
            },
            {
                q: 'How can I track my concern?',
                a: 'All submitted concerns are listed in the Concerns module, or in a specific invoice’s Concerns tab, where you can monitor their status and details.',
            },
        ],
    },
    {
        id: 'remittance-advice',
        icon: 'i-remit',
        title: 'Remittance Advice',
        blurb: 'Submitting proof of payment against your invoices.',
        entries: [
            {
                q: 'How do I upload proof of payment?',
                a: 'Navigate to Remittance Advices, click Upload Remittance Advice, complete the payment details, attach the remittance document, and save.',
            },
            {
                q: 'What payment methods can I select?',
                a: 'Available modes of payment are:',
                bullets: ['Bank Deposit', 'Online Transfer', 'Check', 'Cash', 'Others'],
            },
            {
                q: 'Can one remittance advice be linked to multiple invoices?',
                a: 'Yes. During upload, you can select the billing invoice(s) covered by the payment.',
            },
            {
                q: 'Can I view previously submitted remittances?',
                a: 'Yes. The Remittance Advices module displays all remittance records that you have uploaded.',
            },
        ],
    },
    {
        id: 'finding-members',
        icon: 'i-member',
        title: 'Finding Members',
        blurb: 'Looking up member records and their attachments.',
        entries: [
            {
                q: 'How can I search for a member?',
                a: 'You may search using:',
                bullets: ['Policy Number', 'Last Name', 'First Name', 'Batch Number', 'Account Code', 'Company Name'],
            },
            {
                q: 'Do I need to complete all search fields?',
                a: 'No. At least one search field is required to retrieve results.',
            },
            {
                q: 'Can I view member-related attachments?',
                a: 'Yes. Click the View Attachments icon available in the search results.',
            },
            {
                q: 'Can I upload or delete member attachments?',
                a: 'No. Attachments are uploaded by the Records Management (RM) Team and are available in read-only mode for client users.',
            },
            {
                q: 'Why can’t I open an attachment PDF?',
                a: 'Make sure your browser allows pop-ups from the eSOA website, as attachments open in a separate browser tab.',
            },
        ],
    },
    {
        id: 'general-security',
        icon: 'i-lock',
        title: 'General & Security',
        blurb: 'Data accuracy, audit trails, and safe use of the portal.',
        entries: [
            {
                q: 'What should I do if data is missing or appears incorrect?',
                a: 'Submit a concern through the Concerns module, or contact your ValuCare Account Representative for verification.',
            },
            {
                q: 'Is my activity recorded in the system?',
                a: 'Yes. Invoice-related actions are logged in the SOA Activities audit trail.',
            },
            {
                q: 'Should I log out after using the portal?',
                a: 'Yes. Always log out after each session, especially when using a shared or public computer.',
            },
            {
                q: 'Where can I find a quick walkthrough of the system?',
                a: 'Use the System Overview feature available from the user account menu. It contains a guided overview of the portal’s main functions.',
            },
            {
                q: 'What browsers are recommended for using eSOAv2?',
                a: 'Use a modern, updated web browser such as Microsoft Edge, Google Chrome, Mozilla Firefox, or Safari for the best experience.',
            },
        ],
    },
];

/**
 * Flattened once at module scope: the content is static, so the running number,
 * anchor id and lower-cased search index are built a single time instead of on
 * every keystroke.
 */
let running = 0;
const sections = topics.map((topic) => ({
    ...topic,
    entries: topic.entries.map((entry, i) => ({
        ...entry,
        no: ++running,
        id: `${topic.id}-${i + 1}`,
        haystack: [entry.q, entry.a, ...(entry.bullets ?? [])].join(' ').toLowerCase(),
    })),
}));

const totalQuestions = running;

// ---- search / filter state ----
const query = ref('');
const activeTopic = ref('all');
const openIds = ref(new Set<string>());
const searchEl = ref<HTMLInputElement | null>(null);

const needle = computed(() => query.value.trim().toLowerCase());

const visibleSections = computed(() =>
    sections
        .filter((s) => activeTopic.value === 'all' || s.id === activeTopic.value)
        .map((s) => ({
            ...s,
            entries: needle.value ? s.entries.filter((e) => e.haystack.includes(needle.value)) : s.entries,
        }))
        .filter((s) => s.entries.length > 0),
);

const resultCount = computed(() => visibleSections.value.reduce((n, s) => n + s.entries.length, 0));

// ---- open / close ----
const isOpen = (id: string) => openIds.value.has(id);

const toggleEntry = (id: string) => {
    if (openIds.value.has(id)) openIds.value.delete(id);
    else openIds.value.add(id);
};

const expandAll = () => visibleSections.value.forEach((s) => s.entries.forEach((e) => openIds.value.add(e.id)));
const collapseAll = () => openIds.value.clear();

/** A search only helps if the matching answers are readable straight away. */
watch(needle, (value) => {
    if (value) expandAll();
});

const selectTopic = (id: string) => {
    activeTopic.value = id;
};

const clearSearch = () => {
    query.value = '';
    searchEl.value?.focus();
};

// ---- match highlighting (escaped first, so the markup stays inert) ----
const HTML_ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const escapeHtml = (value: string) => value.replace(/[&<>"']/g, (ch) => HTML_ESCAPES[ch]);
const escapeRegExp = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const highlight = (text: string) => {
    const safe = escapeHtml(text);
    if (!needle.value) return safe;

    const pattern = new RegExp(escapeRegExp(escapeHtml(needle.value)), 'gi');
    return safe.replace(pattern, (match) => `<mark>${match}</mark>`);
};

const pad = (n: number) => String(n).padStart(2, '0');

// ---- keyboard: "/" focuses the search, Esc clears it ----
const isTypingTarget = (target: EventTarget | null) => {
    const el = target as HTMLElement | null;
    return !!el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === '/' && !isTypingTarget(event.target)) {
        event.preventDefault();
        searchEl.value?.focus();
    } else if (event.key === 'Escape' && query.value) {
        query.value = '';
    }
};

/** Deep links such as /faq#concerns-3 open and scroll to that single answer. */
const openFromHash = () => {
    const id = window.location.hash.replace('#', '');
    if (!id) return;

    const entry = sections.flatMap((s) => s.entries).find((e) => e.id === id);
    if (!entry) return;

    openIds.value.add(entry.id);
    nextTick(() => document.getElementById(entry.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    openFromHash();
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="FAQs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="esoa-faq flex h-full flex-1 flex-col gap-4 p-4">
            <!-- SVG symbol defs (defined once, reused everywhere) -->
            <svg width="0" height="0" style="position: absolute" aria-hidden="true">
                <defs>
                    <symbol id="i-login" viewBox="0 0 512 512"><path d="M416 448h-84c-6.6 0-12-5.4-12-12v-40c0-6.6 5.4-12 12-12h84c17.7 0 32-14.3 32-32V160c0-17.7-14.3-32-32-32h-84c-6.6 0-12-5.4-12-12V76c0-6.6 5.4-12 12-12h84c53 0 96 43 96 96v192c0 53-43 96-96 96zM167 71.6l19.5-19.5c9.4-9.4 24.6-9.4 33.9 0L419.5 251.7c9.4 9.4 9.4 24.6 0 33.9L220.4 484.6c-9.4 9.4-24.6 9.4-33.9 0L167 465.1c-9.5-9.5-9.3-25 .4-34.3L295.3 320H24c-13.3 0-24-10.7-24-24v-32c0-13.3 10.7-24 24-24h271.3L167.4 105.9c-9.8-9.3-10-24.8-.4-34.3z" /></symbol>
                    <symbol id="i-dash" viewBox="0 0 512 512"><path d="M0 32C0 14.3 14.3 0 32 0H192c17.7 0 32 14.3 32 32V192c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32V32zM0 320c0-17.7 14.3-32 32-32H192c17.7 0 32 14.3 32 32V480c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32V320zM288 32c0-17.7 14.3-32 32-32H480c17.7 0 32 14.3 32 32V192c0 17.7-14.3 32-32 32H320c-17.7 0-32-14.3-32-32V32zm0 288c0-17.7 14.3-32 32-32H480c17.7 0 32 14.3 32 32V480c0 17.7-14.3 32-32 32H320c-17.7 0-32-14.3-32-32V320z" /></symbol>
                    <symbol id="i-bill" viewBox="0 0 384 512"><path d="M64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V160H256c-17.7 0-32-14.3-32-32V0H64zM256 0V128H384L256 0zM176 88c0-8.8 7.2-16 16-16s16 7.2 16 16v6.4c8.5 1.9 16.4 4.7 22.4 6.7 8.4 2.8 12.9 11.9 10.1 20.2s-11.9 12.9-20.2 10.1c-4.6-1.5-9.1-3.1-13.7-4.4-9.3-2.7-18.5-1.5-25 2.5-6 3.6-8.4 8.3-8.9 12.4-.5 3.9 .5 6.4 1.9 8.4 1.6 2.3 4.3 4.6 8.9 7.2 10.4 5.9 24.6 9.8 36.5 13.1l1.4 .4c11 3 23.7 6.6 33.2 12.5 5.4 3.4 10.9 8.1 14.7 14.8 3.9 6.9 5.5 14.6 4.5 22.9-2.2 18.7-16.6 30.4-33.2 34.4v6.4c0 8.8-7.2 16-16 16s-16-7.2-16-16v-6.4c-10.7-2.6-20.5-6.4-28.6-9.6-8.2-3.3-12.2-12.6-8.9-20.8s12.6-12.2 20.8-8.9c1.2 .5 2.4 .9 3.6 1.4 6.4 2.5 12.6 5 18.2 6.4 9.9 2.4 18.4 1 24-2.2 5.6-3.2 8.4-7.7 9-13 .4-3.7-.4-6.2-1.7-8.1-1.5-2.2-4.1-4.5-8.5-7.2-10.2-6.3-24.2-10.4-36.1-13.7l-2.1-.6c-10.8-3-22.9-6.4-32.1-11.9-5.3-3.2-10.8-7.8-14.7-14.4-4-6.8-5.7-14.5-4.7-22.9 2.2-18.4 16.5-30.4 33-34.5V88z" /></symbol>
                    <symbol id="i-concern" viewBox="0 0 512 512"><path d="M256 448c141.4 0 256-93.1 256-208S397.4 32 256 32S0 125.1 0 240c0 45.1 17.7 86.8 47.7 120.9c-1.9 24.5-11.4 46.3-21.4 62.9c-5.5 9.2-11.1 16.6-15.2 21.6c-2.1 2.5-3.7 4.4-4.9 5.7c-.6 .6-1 1.1-1.3 1.4l-.3 .3c-4.6 4.6-5.9 11.4-3.4 17.4c2.5 6 8.3 9.9 14.8 9.9c28.7 0 57.6-8.9 81.6-19.3c22.9-10 42.4-21.9 54.3-30.6c31.8 11.5 67 17.9 104.1 17.9z" /></symbol>
                    <symbol id="i-remit" viewBox="0 0 576 512"><path d="M64 64C28.7 64 0 92.7 0 128V384c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm48 160H464c8.8 0 16 7.2 16 16v96c0 8.8-7.2 16-16 16H112c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm-16-64c0-8.8 7.2-16 16-16H208c8.8 0 16 7.2 16 16s-7.2 16-16 16H112c-8.8 0-16-7.2-16-16z" /></symbol>
                    <symbol id="i-member" viewBox="0 0 640 512"><path d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192h42.7c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0H21.3C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7h42.7C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3H405.3zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 456.7C128 393.3 179.3 342 242.7 342H397.3C460.7 342 512 393.3 512 456.7c0 12.9-10.5 23.3-23.3 23.3H151.3C138.5 480 128 469.6 128 456.7z" /></symbol>
                    <symbol id="i-lock" viewBox="0 0 448 512"><path d="M144 144v48H304V144c0-44.2-35.8-80-80-80s-80 35.8-80 80zM80 192V144C80 64.5 144.5 0 224 0s144 64.5 144 144v48h16c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V256c0-35.3 28.7-64 64-64H80z" /></symbol>
                    <symbol id="i-search" viewBox="0 0 512 512"><path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" /></symbol>
                    <symbol id="i-help" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM169.8 165.3c7.9-22.3 29.1-37.3 52.8-37.3h58.3c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24V250.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1H222.6c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z" /></symbol>
                    <symbol id="i-chev" viewBox="0 0 448 512"><path d="M201.4 342.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 274.7 86.6 137.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z" /></symbol>
                    <symbol id="i-close" viewBox="0 0 384 512"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z" /></symbol>
                    <symbol id="i-slides" viewBox="0 0 576 512"><path d="M64 0C28.7 0 0 28.7 0 64V352c0 35.3 28.7 64 64 64H240l-10.7 32H160c-17.7 0-32 14.3-32 32s14.3 32 32 32H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H346.7L336 416H512c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64H64zM512 64V288H64V64H512z" /></symbol>
                </defs>
            </svg>

            <div class="frame">
                <!-- HERO -->
                <section class="hero">
                    <div class="orb o1"></div>
                    <div class="orb o2"></div>
                    <div class="hero-inner">
                        <p class="eyebrow">Help center · eSOA v2</p>
                        <h1 class="title">Frequently Asked <span>Questions</span></h1>
                        <p class="lead">
                            Quick answers to the questions clients ask most — from logging in and reading the dashboard, to raising a
                            concern, uploading a remittance advice, and finding a member record.
                        </p>

                        <div class="searchbar">
                            <svg class="s-ico" aria-hidden="true"><use href="#i-search" /></svg>
                            <input
                                ref="searchEl"
                                v-model="query"
                                type="search"
                                aria-label="Search the FAQs"
                                placeholder="Search a question or keyword — “password”, “remittance”, “PDF”…"
                            />
                            <button
                                v-if="query"
                                type="button"
                                class="s-clear"
                                title="Clear search"
                                aria-label="Clear search"
                                @click="clearSearch"
                            >
                                <svg aria-hidden="true"><use href="#i-close" /></svg>
                            </button>
                        </div>

                        <p class="hero-meta">
                            <b>{{ totalQuestions }}</b> answers across <b>{{ sections.length }}</b> topics · press <kbd>/</kbd> to
                            search
                        </p>
                    </div>
                </section>

                <!-- TOPIC FILTER + BULK CONTROLS -->
                <div class="toolbar">
                    <div class="chips" role="tablist" aria-label="Filter by topic">
                        <button
                            type="button"
                            class="chip"
                            :class="{ on: activeTopic === 'all' }"
                            role="tab"
                            :aria-selected="activeTopic === 'all'"
                            @click="selectTopic('all')"
                        >
                            <svg aria-hidden="true"><use href="#i-help" /></svg>
                            All topics
                            <span class="count">{{ totalQuestions }}</span>
                        </button>
                        <button
                            v-for="s in sections"
                            :key="s.id"
                            type="button"
                            class="chip"
                            :class="{ on: activeTopic === s.id }"
                            role="tab"
                            :aria-selected="activeTopic === s.id"
                            @click="selectTopic(s.id)"
                        >
                            <svg aria-hidden="true"><use :href="`#${s.icon}`" /></svg>
                            {{ s.title }}
                            <span class="count">{{ s.entries.length }}</span>
                        </button>
                    </div>

                    <div class="bulk">
                        <span v-if="needle" class="result-note">
                            {{ resultCount }} {{ resultCount === 1 ? 'match' : 'matches' }} for “{{ query.trim() }}”
                        </span>
                        <button type="button" class="ghost" @click="expandAll">Expand all</button>
                        <button type="button" class="ghost" @click="collapseAll">Collapse all</button>
                    </div>
                </div>

                <!-- TOPIC SECTIONS -->
                <section v-for="s in visibleSections" :id="s.id" :key="s.id" class="topic">
                    <header class="topic-head">
                        <div class="chip-ico"><svg aria-hidden="true"><use :href="`#${s.icon}`" /></svg></div>
                        <div class="topic-text">
                            <h2>{{ s.title }}</h2>
                            <p>{{ s.blurb }}</p>
                        </div>
                        <span class="topic-count">{{ s.entries.length }}</span>
                    </header>

                    <div class="qa-list">
                        <article
                            v-for="entry in s.entries"
                            :id="entry.id"
                            :key="entry.id"
                            class="qa"
                            :class="{ open: isOpen(entry.id) }"
                        >
                            <h3 class="qa-h">
                                <button
                                    :id="`${entry.id}-question`"
                                    type="button"
                                    class="qa-btn"
                                    :aria-expanded="isOpen(entry.id)"
                                    :aria-controls="`${entry.id}-answer`"
                                    @click="toggleEntry(entry.id)"
                                >
                                    <span class="no">{{ pad(entry.no) }}</span>
                                    <span class="q" v-html="highlight(entry.q)"></span>
                                    <svg class="chev" aria-hidden="true"><use href="#i-chev" /></svg>
                                </button>
                            </h3>

                            <div
                                :id="`${entry.id}-answer`"
                                class="answer-wrap"
                                role="region"
                                :aria-labelledby="`${entry.id}-question`"
                                :aria-hidden="!isOpen(entry.id)"
                            >
                                <div class="answer">
                                    <p v-html="highlight(entry.a)"></p>
                                    <ul v-if="entry.bullets">
                                        <li v-for="b in entry.bullets" :key="b" v-html="highlight(b)"></li>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- EMPTY STATE -->
                <div v-if="!visibleSections.length" class="empty">
                    <div class="chip-ico"><svg aria-hidden="true"><use href="#i-search" /></svg></div>
                    <h3>No answers matched “{{ query.trim() }}”</h3>
                    <p>Try a shorter keyword, or clear the search to browse every topic.</p>
                    <button type="button" class="solid" @click="clearSearch">Clear search</button>
                </div>

                <!-- CTA -->
                <section class="cta">
                    <div class="cta-text">
                        <p class="eyebrow">Still need help?</p>
                        <h3>Can’t find your answer here?</h3>
                        <p>
                            Submit a concern from the Concerns module so it is tracked against the right invoice, or contact your
                            ValuCare Account Representative for assistance.
                        </p>
                    </div>
                    <Link :href="overview()" class="solid" prefetch>
                        <svg aria-hidden="true"><use href="#i-slides" /></svg>
                        Watch the System Overview
                    </Link>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Namespaced under .esoa-faq so nothing leaks into the surrounding app shell.
   The palette mirrors the System Overview page. */
.esoa-faq {
    --ov-red: #8c1420;
    --ov-red-dk: #5e0d16;
    --ov-red-deep: #43070e;
    --ov-gold: #c7962c;
    --ov-gold-lt: #e3be6b;
    --ov-ink: #2b2b2b;
    --ov-slate: #6b6b6b;
    --ov-card: #ffffff;
    --ov-soft: #f5e7e8;
    --ov-line: rgba(140, 20, 32, 0.12);
    --ov-shadow: rgba(80, 10, 18, 0.1);
}
html.dark .esoa-faq {
    --ov-ink: #ececec;
    --ov-slate: #a9a2a3;
    --ov-card: #1d1a1b;
    --ov-soft: rgba(140, 20, 32, 0.3);
    --ov-line: rgba(255, 255, 255, 0.1);
    --ov-shadow: rgba(0, 0, 0, 0.45);
}

.frame {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: clamp(16px, 2.4vw, 28px);
}

/* ---- hero ---- */
.hero {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    padding: clamp(24px, 4.4vw, 52px);
    background: linear-gradient(135deg, var(--ov-red-dk) 0%, var(--ov-red-deep) 100%);
    color: #fff;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
}
.hero-inner {
    position: relative;
    z-index: 2;
}
.orb {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    background: rgba(140, 20, 32, 0.55);
}
.orb.o1 {
    width: 38%;
    aspect-ratio: 1;
    right: -9%;
    top: -38%;
}
.orb.o2 {
    width: 22%;
    aspect-ratio: 1;
    right: 6%;
    bottom: -34%;
    background: rgba(199, 150, 44, 0.16);
}

.eyebrow {
    font-size: clamp(10px, 1vw, 13px);
    letter-spacing: 0.3em;
    font-weight: 700;
    color: var(--ov-gold);
    text-transform: uppercase;
}
.title {
    font-family: 'Georgia', 'Times New Roman', serif;
    font-weight: 700;
    line-height: 1.05;
    font-size: clamp(30px, 4.6vw, 56px);
    margin: 10px 0 8px;
}
.title span {
    color: var(--ov-gold-lt);
    font-style: italic;
}
.lead {
    font-size: clamp(13px, 1.35vw, 17px);
    line-height: 1.55;
    color: #ead9db;
    max-width: 62ch;
}

.searchbar {
    position: relative;
    display: flex;
    align-items: center;
    margin-top: clamp(16px, 2.2vw, 24px);
    background: #fff;
    border-radius: 14px;
    padding: 0 12px;
    box-shadow: 0 16px 34px rgba(0, 0, 0, 0.26);
    max-width: 620px;
}
.searchbar .s-ico {
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
    fill: var(--ov-red);
    opacity: 0.7;
}
.searchbar input {
    flex: 1 1 auto;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    padding: 13px 10px;
    font-size: 14px;
    color: #2b2b2b;
}
.searchbar input::placeholder {
    color: #9a9294;
}
.searchbar input::-webkit-search-cancel-button {
    display: none;
}
.s-clear {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    border: 0;
    border-radius: 50%;
    background: #f5e7e8;
    cursor: pointer;
}
.s-clear svg {
    width: 10px;
    height: 10px;
    fill: var(--ov-red);
}
.hero-meta {
    margin-top: 12px;
    font-size: 12px;
    color: #c9aeb0;
}
.hero-meta b {
    color: var(--ov-gold-lt);
}

/* ---- toolbar ---- */
.toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid var(--ov-line);
    background: var(--ov-card);
    color: var(--ov-ink);
    border-radius: 30px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition:
        background 0.18s,
        color 0.18s,
        border-color 0.18s;
}
.chip svg {
    width: 13px;
    height: 13px;
    fill: var(--ov-red);
}
html.dark .chip svg {
    fill: var(--ov-gold-lt);
}
.chip .count {
    font-size: 11px;
    font-weight: 700;
    color: var(--ov-slate);
    background: var(--ov-soft);
    border-radius: 20px;
    padding: 1px 7px;
}
.chip:hover {
    border-color: var(--ov-red);
}
.chip.on {
    background: var(--ov-red);
    border-color: var(--ov-red);
    color: #fff;
}
.chip.on svg {
    fill: var(--ov-gold-lt);
}
.chip.on .count {
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
}

.bulk {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}
.result-note {
    font-size: 12px;
    color: var(--ov-slate);
    margin-right: 4px;
}
.ghost {
    border: 1px solid var(--ov-line);
    background: transparent;
    color: var(--ov-slate);
    border-radius: 9px;
    padding: 6px 11px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition:
        color 0.18s,
        border-color 0.18s;
}
.ghost:hover {
    color: var(--ov-red);
    border-color: var(--ov-red);
}

/* ---- topic sections ---- */
.topic {
    scroll-margin-top: 90px;
}
.topic-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-bottom: 12px;
    margin-bottom: 12px;
    border-bottom: 1px solid var(--ov-line);
}
.chip-ico {
    flex: 0 0 auto;
    width: clamp(36px, 3.4vw, 44px);
    aspect-ratio: 1;
    border-radius: 50%;
    background: var(--ov-soft);
    display: grid;
    place-items: center;
}
.chip-ico svg {
    width: 46%;
    height: 46%;
    fill: var(--ov-red);
}
html.dark .chip-ico svg {
    fill: var(--ov-gold-lt);
}
.topic-text {
    min-width: 0;
}
.topic-head h2 {
    font-family: 'Georgia', 'Times New Roman', serif;
    font-weight: 700;
    font-size: clamp(18px, 2.1vw, 26px);
    color: var(--ov-red);
    line-height: 1.15;
}
html.dark .topic-head h2 {
    color: var(--ov-gold-lt);
}
.topic-head p {
    font-size: clamp(11px, 1.1vw, 13.5px);
    color: var(--ov-slate);
    margin-top: 2px;
}
.topic-count {
    margin-left: auto;
    flex: 0 0 auto;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: var(--ov-slate);
    border: 1px solid var(--ov-line);
    border-radius: 20px;
    padding: 3px 10px;
}

.qa-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.qa {
    background: var(--ov-card);
    border: 1px solid var(--ov-line);
    border-radius: 12px;
    overflow: hidden;
    scroll-margin-top: 90px;
    transition:
        box-shadow 0.2s,
        border-color 0.2s;
}
.qa:hover {
    border-color: rgba(140, 20, 32, 0.32);
}
.qa.open {
    border-color: rgba(140, 20, 32, 0.4);
    box-shadow: 0 12px 28px var(--ov-shadow);
}
.qa-h {
    margin: 0;
    font-size: inherit;
    font-weight: inherit;
}
.qa-btn {
    width: 100%;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    text-align: left;
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: clamp(12px, 1.5vw, 16px) clamp(13px, 1.6vw, 18px);
}
.qa-btn:focus-visible {
    outline: 2px solid var(--ov-red);
    outline-offset: -3px;
}
.no {
    flex: 0 0 auto;
    font-family: 'Georgia', serif;
    font-weight: 700;
    font-size: 13px;
    color: var(--ov-red);
    background: var(--ov-soft);
    border-radius: 7px;
    padding: 3px 8px;
    margin-top: 1px;
}
html.dark .no {
    color: var(--ov-gold-lt);
}
.qa-btn .q {
    flex: 1 1 auto;
    font-size: clamp(13px, 1.3vw, 16px);
    font-weight: 600;
    line-height: 1.4;
    color: var(--ov-ink);
}
.chev {
    flex: 0 0 auto;
    width: 13px;
    height: 13px;
    margin-top: 4px;
    fill: var(--ov-red);
    opacity: 0.6;
    transition: transform 0.28s ease;
}
html.dark .chev {
    fill: var(--ov-gold-lt);
}
.qa.open .chev {
    transform: rotate(180deg);
    opacity: 1;
}

/* grid-rows trick: animates to the answer's natural height, no JS measuring */
.answer-wrap {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.3s ease;
}
.qa.open .answer-wrap {
    grid-template-rows: 1fr;
}
.answer-wrap > .answer {
    overflow: hidden;
}
.answer > * {
    /* line the answer up under the question, past the number badge */
    padding-left: calc(clamp(13px, 1.6vw, 18px) + 42px);
    padding-right: clamp(13px, 1.6vw, 18px);
}
.answer p {
    font-size: clamp(12px, 1.2vw, 14.5px);
    line-height: 1.6;
    color: var(--ov-slate);
    padding-bottom: clamp(12px, 1.5vw, 16px);
}
.answer ul {
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: -6px;
    padding-bottom: clamp(12px, 1.5vw, 16px);
}
.answer li {
    font-size: clamp(11px, 1.1vw, 13px);
    font-weight: 600;
    color: var(--ov-ink);
    background: var(--ov-soft);
    border-radius: 20px;
    padding: 5px 12px;
}
.qa :deep(mark) {
    background: rgba(199, 150, 44, 0.34);
    color: inherit;
    border-radius: 3px;
    padding: 0 2px;
}

/* ---- empty state ---- */
.empty {
    text-align: center;
    background: var(--ov-card);
    border: 1px dashed var(--ov-line);
    border-radius: 16px;
    padding: clamp(26px, 4vw, 44px);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.empty h3 {
    font-family: 'Georgia', serif;
    font-size: clamp(16px, 1.8vw, 21px);
    color: var(--ov-red);
}
html.dark .empty h3 {
    color: var(--ov-gold-lt);
}
.empty p {
    font-size: 13px;
    color: var(--ov-slate);
}

/* ---- CTA ---- */
.cta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    border-radius: 16px;
    padding: clamp(18px, 2.6vw, 28px);
    background: var(--ov-card);
    border: 1px solid var(--ov-line);
    box-shadow: 0 14px 34px var(--ov-shadow);
}
.cta-text {
    max-width: 62ch;
}
.cta-text .eyebrow {
    color: var(--ov-red);
}
html.dark .cta-text .eyebrow {
    color: var(--ov-gold);
}
.cta-text h3 {
    font-family: 'Georgia', serif;
    font-weight: 700;
    font-size: clamp(17px, 1.9vw, 23px);
    color: var(--ov-red);
    margin: 6px 0;
}
html.dark .cta-text h3 {
    color: var(--ov-gold-lt);
}
.cta-text p {
    font-size: clamp(12px, 1.2vw, 14.5px);
    line-height: 1.55;
    color: var(--ov-slate);
}
.solid {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    border: 0;
    border-radius: 12px;
    background: var(--ov-red);
    color: #fff;
    font-size: 13.5px;
    font-weight: 600;
    padding: 11px 18px;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition:
        background 0.2s,
        transform 0.1s;
}
.solid:hover {
    background: var(--ov-red-dk);
}
.solid:active {
    transform: translateY(1px);
}
.solid svg {
    width: 15px;
    height: 15px;
    fill: currentColor;
}

kbd {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 5px;
    padding: 1px 6px;
    font-size: 11px;
    font-family: inherit;
    color: #fff;
}

@media (max-width: 640px) {
    .bulk {
        width: 100%;
        margin-left: 0;
    }
    .answer > * {
        padding-left: clamp(13px, 1.6vw, 18px);
    }
    .hero-meta kbd {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .answer-wrap,
    .chev,
    .qa {
        transition: none;
    }
}
</style>
