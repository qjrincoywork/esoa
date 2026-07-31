<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/components/ui/tooltip';
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

const page = usePage();
const { slug } = useModulePermissions();

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: slug.value,
  },
];
/** Shape of App\Http\Resources\SoaAgingCountResource. */
interface SoaAgingCount {
  type: 'aging' | 'status';
  value: number;
  count: number;
  label: string;
  color: string;
  href: string;
}

/** One row of tiles inside a section (e.g. the past-due buckets). */
interface BucketGroup {
  key: string;
  /** Sub-heading, or null when the section needs no second level. */
  title: string | null;
  gridClass: string;
  buckets: SoaAgingCount[];
}

/** A titled card holding one lens over the same invoices: status, or aging. */
interface BucketSection {
  key: string;
  title: string;
  description: string;
  groups: BucketGroup[];
  total: number;
}

/**
 * Display order for the status tiles — the states that need action first, settled last.
 * Mirrors App\Enums\SoaStatus (1 Unpaid, 2 Endorsed, 4 Disputed, 3 Paid); the server returns
 * these in its own order and this is presentation only.
 */
const STATUS_ORDER = [1, 2, 4, 3];

/**
 * Aging buckets that are not overdue yet — App\Enums\SoaAging::NOT_YET_DUE and
 * DUE_CURRENT_MONTH, i.e. the PHP-side SoaAging::isPastDue() exclusion list.
 */
const CURRENT_AGING_VALUES = [1, 2];

const soaAgings = computed(() => ((page.props as any).soa_agings?.data ?? []) as SoaAgingCount[]);

const statusBuckets = computed(() =>
  soaAgings.value
    .filter((bucket) => bucket.type === 'status')
    .slice()
    .sort((a, b) => STATUS_ORDER.indexOf(a.value) - STATUS_ORDER.indexOf(b.value)),
);

const agingBuckets = computed(() => soaAgings.value.filter((bucket) => bucket.type === 'aging'));

const currentAgingBuckets = computed(() =>
  agingBuckets.value.filter((bucket) => CURRENT_AGING_VALUES.includes(bucket.value)),
);

const pastDueBuckets = computed(() =>
  agingBuckets.value.filter((bucket) => !CURRENT_AGING_VALUES.includes(bucket.value)),
);

const sumCounts = (buckets: SoaAgingCount[]) =>
  buckets.reduce((total, bucket) => total + bucket.count, 0);

/**
 * The two lenses, rendered as one card each so the reader sees two groups of related
 * numbers instead of eleven equally-weighted tiles. Aging keeps a second level because
 * "still on time" and "already overdue" are read very differently.
 */
const sections = computed<BucketSection[]>(() => [
  {
    key: 'status',
    title: 'By status',
    description: 'Where each statement of account stands in the billing cycle.',
    total: sumCounts(statusBuckets.value),
    groups: [
      {
        key: 'status',
        title: null,
        gridClass: 'grid gap-3 sm:grid-cols-2 xl:grid-cols-4',
        buckets: statusBuckets.value,
      },
    ],
  },
  {
    key: 'aging',
    title: 'By aging',
    description: 'How far each statement is from — or past — its due date.',
    total: sumCounts(agingBuckets.value),
    groups: [
      {
        key: 'current',
        title: 'Still on time',
        gridClass: 'grid gap-3 sm:grid-cols-2',
        buckets: currentAgingBuckets.value,
      },
      {
        key: 'past-due',
        title: 'Past due',
        gridClass: 'grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
        buckets: pastDueBuckets.value,
      },
    ],
  },
]);

/**
 * Keep only the color utilities from the server-provided class string: SoaStatus::color()
 * bakes in its own padding and rounding, which would fight the shared tile geometry, while
 * SoaAging::color() does not. Filtering here puts every tile on the same layout without
 * touching the enums.
 */
const accentClasses = (bucket: SoaAgingCount) =>
  (bucket.color ?? '')
    .split(/\s+/)
    .filter((token) => /^(bg|text|border)-/.test(token))
    .join(' ');

const redirectToSoaList = (href: string) => {
  router.get(href);
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Dashboard" />

    <TooltipProvider :delay-duration="150">
      <div class="flex flex-1 flex-col gap-4 p-4">
        <Card v-for="section in sections" :key="section.key">
          <CardHeader>
            <CardTitle>{{ section.title }}</CardTitle>
            <CardDescription>{{ section.description }}</CardDescription>
            <CardAction>
              <Badge variant="secondary">{{ section.total }} record(s)</Badge>
            </CardAction>
          </CardHeader>

          <CardContent class="flex flex-col gap-5">
            <div
              v-for="group in section.groups.filter((g) => g.buckets.length)"
              :key="group.key"
              class="flex flex-col gap-3"
            >
              <div v-if="group.title" class="flex items-center gap-3">
                <h4 class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  {{ group.title }}
                </h4>
                <span class="h-px flex-1 bg-border"></span>
                <span class="text-xs text-muted-foreground tabular-nums">
                  {{ sumCounts(group.buckets) }}
                </span>
              </div>

              <div :class="group.gridClass">
                <Tooltip
                  v-for="bucket in group.buckets"
                  :key="`${bucket.type}-${bucket.value}`"
                >
                  <TooltipTrigger as-child>
                    <button
                      type="button"
                      :disabled="bucket.count === 0"
                      :aria-label="`${bucket.label}: ${bucket.count} record(s)`"
                      class="flex h-full w-full flex-col items-start gap-2 rounded-lg border p-4 text-left transition-shadow outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 enabled:cursor-pointer enabled:hover:shadow-md disabled:opacity-60"
                      :class="accentClasses(bucket)"
                      @click="redirectToSoaList(bucket.href)"
                    >
                      <span class="text-sm leading-snug font-medium">{{ bucket.label }}</span>
                      <span class="text-3xl leading-none font-semibold tracking-tight tabular-nums">
                        {{ bucket.count }}
                      </span>
                    </button>
                  </TooltipTrigger>
                  <TooltipContent v-if="bucket.count > 0">
                    <p>Click to view list</p>
                  </TooltipContent>
                </Tooltip>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </TooltipProvider>
  </AppLayout>
</template>
