<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { SearchableCombobox } from '@/components/ui/searchable-combobox';
import { Auth, User } from '@/types';
import { debounce } from 'lodash-es';
import { useModulePermissions } from '@/composables/useModulePermissions';

const { slug, hasPermission } = useModulePermissions();
type SoaRelation = {
  id: string | number;
  soa_number?: string;
  account_code?: string;
  branch_code?: string;
};
type Concern = {
  id?: number
  user_id?: number
  soa_ids?: Array<number> | string
  soas?: SoaRelation[]
  type?: string
  type_value?: number
  title?: string
  description?: string
  status?: string
  status_value?: number
  attachment?: string
  attachment_preview_token?: string
}
type BillingInvoice = { value: string | number; name: string; }

/** Format a SOA relation the same way `/soas/list` search results are formatted. */
function toBillingInvoiceOption(soa: SoaRelation): BillingInvoice {
  return {
    value: soa.id,
    name: `${soa.soa_number ?? ''} - ${soa.account_code ?? ''}${soa.branch_code ? ` (${soa.branch_code})` : ''}`,
  };
}

const props = defineProps({
  auth: {
    type: Object as unknown as () => Auth,
    default: () => ({}),
  },
  user: {
    type: Object as unknown as () => User,
    default: () => ({}),
  },
  concern: {
    type: Object as unknown as () => Concern,
    default: () => ({}),
  },
  concern_types: {
    type: Array as unknown as () => { value: string | number; name: string }[],
    default: () => [],
  },
  ticket_statuses: {
    type: Array as unknown as () => { value: string | number; name: string }[],
    default: () => [],
  },
  /**
   * SOA ids supplied by the calling context (e.g. the SOA details pane). When set,
   * the billing-invoice picker is replaced by a read-only summary and these ids are
   * the ones posted, so the concern is always linked to the SOA it was raised from.
   */
  locked_soa_ids: {
    type: Array as unknown as () => (string | number)[],
    default: () => [],
  },
  /** Human-readable billing invoice shown in place of the picker. */
  locked_soa_label: {
    type: String,
    default: '',
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});
const concern = computed<Concern>(() => props.concern as Concern)

const form = ref({
  title: '',
  description: '',
});

const user = computed(() => props.auth?.user as User);
const userRoles = computed<string[]>(() => user.value?.roles ?? []);
/**
 * account_branch_admin / group_account_admin only manage their own tickets end-to-end
 * (no internal triage step), so "In Progress" is not a valid state for them to set.
 */
const isRestrictedStatusRole = computed(() =>
  userRoles.value.includes('account_branch_admin') || userRoles.value.includes('group_account_admin')
);
/**
 * billing_admin only triages existing tickets — mirrors UpdateRequest's billing_admin
 * branch (App\Http\Requests\Concern\UpdateRequest), which accepts just id + status from
 * this role. Doesn't apply to creating a new concern: CreateRequest has no such carve-out.
 */
const isBillingAdminEditingExisting = computed(() =>
  userRoles.value.includes('billing_admin') && concern.value?.id != null
);
/** Whether the full concern form (vs. the status-only view) should be shown. */
const showFullForm = computed(() =>
  !isBillingAdminEditingExisting.value && (hasPermission(slug.value + '.edit') || concern.value?.id == null)
);
const selectedStatus = ref<string>('1');
const selectedType = ref<string>('1');
const types = computed(() => props.concern_types || []); // Assuming concern_types is passed as a prop
const statuses = computed(() => props.ticket_statuses || []); // Assuming ticket_statuses is passed as a prop
const RESTRICTED_STATUS_NAMES = ['Open', 'Resolved', 'Closed'];
const filteredStatuses = computed(() => {
  if (isRestrictedStatusRole.value) {
    return statuses.value?.filter(s => RESTRICTED_STATUS_NAMES.includes(s.name));
  }
  return statuses.value;
});
const concernForm = ref<HTMLFormElement | null>(null);
const soa_ids = ref<BillingInvoice[]>([])
const billingInvoicePage = ref(1)
const billingInvoiceLastPage = ref(1)
const billingInvoicesLoadingMore = ref(false)
const searchedBillingInvoice = ref('')
/** The SOA is fixed by the calling context — no picker, no `/soas/list` lookup. */
const isSoaLocked = computed(() => (props.locked_soa_ids?.length ?? 0) > 0)
const billingInvoice = ref<(string | number)[]>([...(props.locked_soa_ids ?? [])])
const hasMoreBillingInvoices = computed(() => billingInvoicePage.value < billingInvoiceLastPage.value)

function getFormData(): FormData | null {
  if (!concernForm.value) return null;
  return new FormData(concernForm.value);
}

/**
 * Fetch and search SOAs (billing invoices) by name and optional page.
 * Replaces or appends results based on the `append` flag.
 */
const searchBillingInvoicesByParams = async (name = '', page = 1, append = false) => {
  if (append) {
    billingInvoicesLoadingMore.value = true;
  }

  try {
    const params: Record<string, any> = {
      soanum: name,
      page,
    };

    const response = await fetch(`/soas/list?${new URLSearchParams(params).toString()}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch SOAs: ${response.statusText}`);
    }

    const result = await response.json();
    const data = result?.data ?? [];

    // Transform SOA data to BillingInvoice format
    const transformed = data.map((soa: any) => toBillingInvoiceOption(soa));

    if (append) {
      soa_ids.value = [...(soa_ids.value ?? []), ...transformed];
    } else {
      soa_ids.value = transformed;
    }

    billingInvoicePage.value = result?.current_page ?? page;
    billingInvoiceLastPage.value = result?.last_page ?? page;
  } catch (error) {
    console.error('Error fetching SOAs:', error);
    if (!append) {
      soa_ids.value = [];
    }
  } finally {
    billingInvoicesLoadingMore.value = false;
  }
}

/**
 * Debounced wrapper to avoid excessive API calls on every keystroke.
 */
const debouncedGetBillingInvoices: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchBillingInvoicesByParams(name, 1, false);
}, 2000);

onMounted(() => {
  // Load initial SOAs list — skipped when the SOA is already fixed by the caller.
  if (!isSoaLocked.value) {
    void searchBillingInvoicesByParams('', 1, false);
  }

  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: concernForm.value });
  }
});

/**
 * Populate the editable fields from the concern prop whenever it's set or replaced,
 * so the same form instance stays correct if the parent swaps concerns without
 * remounting (e.g. the modal is reused across successive edits).
 */
watch(
  () => props.concern,
  (newConcern: Concern | undefined) => {
    const source = newConcern ?? {};
    form.value.title = source.title ?? '';
    form.value.description = source.description ?? '';
    selectedType.value = String(source.type_value ?? source.type ?? '1');
    selectedStatus.value = String(source.status_value ?? source.status ?? '1');

    // Seed the combobox options with the concern's own linked SOAs so they render
    // with their real name even when they fall outside the general search's page.
    if (source.soas?.length) {
      const knownIds = new Set(soa_ids.value.map((option: BillingInvoice) => option.value));
      const missing = source.soas
        .filter(soa => !knownIds.has(soa.id))
        .map(toBillingInvoiceOption);
      if (missing.length) {
        soa_ids.value = [...soa_ids.value, ...missing];
      }
    }
  },
  { immediate: true }
);

/**
 * Sync billingInvoice from concern data when component initializes or concern changes.
 * A caller-supplied SOA always wins, so the context link can never be overwritten.
 */
watch(
  () => props.concern?.soa_ids,
  (soa_ids) => {
    if (isSoaLocked.value) {
      billingInvoice.value = [...(props.locked_soa_ids ?? [])];
      return;
    }

    if (!soa_ids) {
      billingInvoice.value = [];
      return;
    }

    // Handle both array and comma-separated string formats
    if (Array.isArray(soa_ids)) {
      billingInvoice.value = soa_ids;
    } else if (typeof soa_ids === 'string') {
      billingInvoice.value = soa_ids.split(',').filter(Boolean).map(id => {
        const num = parseInt(id, 10);
        return isNaN(num) ? id : num;
      });
    }
  },
  { immediate: true }
);

/**
 * Sync search input to debounced search function.
 */
watch(searchedBillingInvoice, (newVal) => {
  debouncedGetBillingInvoices(newVal);
});

/**
 * Handle pagination load-more events.
 */
function loadMoreData(input: string) {
  switch (input) {
    case 'soas':
      if (!hasMoreBillingInvoices.value || billingInvoicesLoadingMore.value) return;
      void searchBillingInvoicesByParams(searchedBillingInvoice.value, billingInvoicePage.value + 1, true);
      break;
  }
}

/**
 * Open attachment preview in new tab.
 */
const openTab = () => {
  window.open(
    `/concerns/preview_file?token=${encodeURIComponent(concern.value?.attachment_preview_token ?? '')}`,
    '_blank',
    'noopener,noreferrer'
  )
}
</script>

<template>
  <form ref="concernForm" class="grid grid-cols-1 md:grid-cols-1 gap-3" enctype="multipart/form-data">
    <div v-if="showFullForm">
      <div class="md:col-span-2 hidden">
        <!-- Use native hidden inputs so FormData always reflects latest reactive values -->
        <input v-if="concern?.id" type="hidden" name="id" :value="concern?.id" />
        <input type="hidden" name="type" :value="selectedType" />
        <input type="hidden" name="status" :value="selectedStatus" />
        <template v-for="id in billingInvoice" :key="id">
          <input type="hidden" name="soa_ids[]" :value="id" />
        </template>
      </div>
      <div v-if="isSoaLocked" class="grid gap-2 md:col-span-1 mb-2">
        <Label for="locked_billing_invoice">Billing Invoice</Label>
        <Input
          id="locked_billing_invoice"
          class="mt-1 block w-full"
          :model-value="locked_soa_label || 'Linked to this billing invoice'"
          disabled
        />
      </div>
      <div v-else class="grid gap-2 md:col-span-1 mb-2">
        <SearchableCombobox
          id="soa_ids"
          label="Billing Invoices"
          v-model="billingInvoice"
          v-model:search="searchedBillingInvoice"
          :required="true"
          :items="soa_ids"
          placeholder="Select Billing Invoices..."
          search-placeholder="Search Billing Invoices..."
          empty-text="No Billing Invoices found."
          :has-more="hasMoreBillingInvoices"
          :loading-more="billingInvoicesLoadingMore"
          :multiple="true"
          @load-more="loadMoreData('soas')"
        />
      </div>
      <div class="grid gap-2 md:col-span-1 mb-2">
        <Label for="type">Type<span class="text-red-400">*</span></Label>
        <Select
          class="mt-1 block w-full"
          v-model="selectedType"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select type" />
          </SelectTrigger>
          <SelectContent class="w-full">
            <SelectItem
              v-for="type in types"
              :key="type.value"
              :value="String(type.value)"
            >
              {{ type.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="grid gap-2 md:col-span-1 mb-2">
        <Label for="title">Title<span class="text-red-400">*</span></Label>
        <Input
          class="mt-1 block w-full"
          id="title"
          name="title"
          v-model="form.title"
        />
      </div>
      <div class="grid gap-2 md:col-span-1 mb-2">
        <Label for="description">Description<span class="text-red-400">*</span></Label>
        <Textarea
          placeholder="Type the description here."
          class="mt-1 block w-full"
          id="description"
          name="description"
          v-model="form.description"
        />
      </div>
      <div v-if="auth?.is_superadmin || hasPermission(slug + '.edit')" class="grid gap-2 md:col-span-1 mb-2">
        <Label for="status">Status<span class="text-red-400">*</span></Label>
        <Select
          class="mt-1 block w-full"
          v-model="selectedStatus"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select status" />
          </SelectTrigger>
          <SelectContent class="w-full">
            <SelectItem
              v-for="status in filteredStatuses"
              :key="status.value"
              :value="String(status.value)"
            >
              {{ status.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="grid gap-2 md:col-span-1">
        <Label for="attachment">Attachment</Label>
        <p
          v-if="concern.attachment"
          class="mt-1 text-xs text-[var(--color-text-muted)]"
        >
          Current:
          <a
            :onClick="openTab"
            target="_blank"
            rel="noopener noreferrer"
            class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
          >
            {{ concern.attachment.split('/').pop() }}
          </a>
        </p>
        <Input
          class="mt-1 block w-full"
          id="attachment"
          name="attachment"
          type="file"
        />
      </div>
    </div>
    <div v-else>
      <div class="md:col-span-2 hidden">
        <!-- Use native hidden inputs so FormData always reflects latest reactive values -->
        <input v-if="concern?.id" type="hidden" name="id" :value="concern?.id" />
        <input type="hidden" name="type" :value="selectedType" />
        <input type="hidden" name="status" :value="selectedStatus" />
        <template v-for="id in billingInvoice" :key="id">
          <input type="hidden" name="soa_ids[]" :value="id" />
        </template>
      </div>
      <div class="grid gap-2 md:col-span-1 mb-2">
        <Label for="status">Status<span class="text-red-400">*</span></Label>
        <Select
          class="mt-1 block w-full"
          v-model="selectedStatus"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select status" />
          </SelectTrigger>
          <SelectContent class="w-full">
            <SelectItem
              v-for="status in filteredStatuses"
              :key="status.value"
              :value="String(status.value)"
            >
              {{ status.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>
  </form>
</template>
