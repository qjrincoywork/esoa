<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  Card,
  CardContent,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import SoaActivitiesList from '@/components/forms/soas/SoaActivitiesList.vue';
import SoaConcernsList from '@/components/forms/soas/SoaConcernsList.vue';
import SoaAccountPaymentsList from '@/components/forms/soas/SoaAccountPaymentsList.vue';
import AmountManagementForm from '@/components/forms/soas/AmountManagementForm.vue';
import AccountBranchMembers from '@/components/forms/soas/AccountBranchMembers.vue';
import { Soa } from '@/types';
import { useModulePermissions } from '@/composables/useModulePermissions';
const { slug, hasPermission } = useModulePermissions();

const props = defineProps({
  soa: {
    type: Object as unknown as () => Soa,
    default: () => ({}),
  },
});
const page = usePage();
const localSoa = ref<Soa>({})
watch(
  () => props.soa,
  (p) => {
    localSoa.value = { ...(p as Soa) }
  },
  { immediate: true, deep: true },
)

function onAmountAdjusted(payload: { amount: string; amount_raw: number }) {
  localSoa.value.amount = payload.amount
  localSoa.value.amount_raw = payload.amount_raw
}

const activeTab = ref('details')
function fileBasename(path: string): string {
  const normalized = path.replace(/\\/g, '/')
  const segment = normalized.split('/').pop()
  return segment || path
}

/** Existing uploads (native file inputs cannot show these; we show name + link instead). */
const existingPdf = computed(() => {
  const id = localSoa.value?.id
  const path = localSoa.value?.file_pdf
  if (id == null || !path) return null
  return { name: fileBasename(String(path)), href: `/soas/${id}/attachment/pdf` }
})

const existingExcel = computed(() => {
  const id = localSoa.value?.id
  const path = localSoa.value?.file_xls
  if (id == null || !path) return null
  return { name: fileBasename(String(path)), href: `/soas/${id}/attachment/excel` }
})
</script>

<template>
  <div class="flex w-full flex-col">
    <Tabs v-model="activeTab" default-value="details">
      <TabsList>
        <TabsTrigger class="cursor-pointer" value="details">
          Details
        </TabsTrigger>
        <TabsTrigger class="cursor-pointer" value="amount_management" v-if="page.props.auth.user?.user_detail?.employee_no || page.props.auth.is_superadmin">
          Amount Management
        </TabsTrigger>
        <TabsTrigger v-if="hasPermission(`${slug}.account_branch_members`) && localSoa.billing_ref" class="cursor-pointer" value="members">
          Account / Branch Members
        </TabsTrigger>
        <TabsTrigger v-if="hasPermission(`${slug}.concerns`)"  class="cursor-pointer" value="concerns">
          Concerns
        </TabsTrigger>
        <TabsTrigger v-if="hasPermission(`${slug}.account_payments`)" class="cursor-pointer" value="remittance_advices">
          Remittance Advices
        </TabsTrigger>
        <TabsTrigger class="cursor-pointer" value="activities">
          Soa Activities
        </TabsTrigger>
      </TabsList>
      <TabsContent value="details">
        <Card>
          <CardContent class="grid gap-3">
            <ul class="my-6 ml-6 list-disc [&>li]:mt-2">
              <li>Account Name: <span class="font-bold">{{ localSoa.account_name }}</span></li>
              <li v-if="localSoa.account_code">Account Code: <span class="font-bold">{{ localSoa.account_code }}</span></li>
              <li v-if="localSoa.branch_name">Branch Name: <span class="font-bold">{{ localSoa.branch_name }}</span></li>
              <li>Billing Reference/s: <p class="font-bold text-wrap">{{ localSoa.billing_ref_names }}</p></li>
              <li>SOA Number: <span class="font-bold">{{ localSoa.soa_number }}</span></li>
              <li>Bill Type: <span class="font-bold">{{ localSoa.bill_type }}</span></li>
              <li>Status: <span :class="[
                  'px-2 py-1 rounded-md text-xs font-medium',
                  localSoa.status_color
                ]">{{ localSoa.status }}</span>
              </li>
              <li>Due Date:
                <span class="font-bold">{{ localSoa.due_date }}
                  <Badge variant="secondary">
                    {{ localSoa.due_in }}
                  </Badge>
                </span>
              </li>
              <li>Bill Date: <span class="font-bold">{{ localSoa.created_at }} </span></li>
              <li>Period Coverage: <span class="font-bold">{{ localSoa.period_coverage }}</span></li>
              <li>Amount: <span class="font-bold">{{ localSoa.amount }}</span></li>
              <li v-if="existingPdf">Uploaded PDF:
                <div class="grid gap-2 md:col-span-1">
                  <p
                    v-if="existingPdf"
                    class="mt-1 text-xs text-[var(--color-text-muted)]"
                  >
                    <TooltipProvider>
                      <Tooltip>
                        <TooltipTrigger>
                          <a
                            :href="existingPdf.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
                          >
                            {{ existingPdf.name }}
                          </a>
                        </TooltipTrigger>
                        <TooltipContent>
                          View PDF
                        </TooltipContent>
                      </Tooltip>
                    </TooltipProvider>
                  </p>
                </div>
              </li>
              <li v-if="existingExcel">Uploaded XLS/XLSX:
                <div class="grid gap-2 md:col-span-1">
                  <p
                    v-if="existingExcel"
                    class="mt-1 text-xs text-[var(--color-text-muted)]"
                  >
                    <TooltipProvider>
                      <Tooltip>
                        <TooltipTrigger>
                          <a
                            :href="existingExcel.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
                          >
                            {{ existingExcel.name }}
                          </a>
                        </TooltipTrigger>
                        <TooltipContent>
                          View XLS/XLSX
                        </TooltipContent>
                      </Tooltip>
                    </TooltipProvider>
                  </p>
                </div>
              </li>
            </ul>
          </CardContent>
        </Card>
      </TabsContent>
      <TabsContent v-if="hasPermission(`${slug}.concerns`)" value="concerns">
        <Card>
          <CardContent class="grid gap-6">
            <SoaConcernsList
              v-if="activeTab === 'concerns'"
              :soa-id="localSoa.id ?? null" />
          </CardContent>
        </Card>
      </TabsContent>
      <TabsContent v-if="hasPermission(`${slug}.account_payments`)" value="remittance_advices">
        <Card>
          <CardContent class="grid gap-6">
            <SoaAccountPaymentsList
              v-if="activeTab === 'remittance_advices'"
              :soa-id="localSoa.id ?? null" />
          </CardContent>
        </Card>
      </TabsContent>
      <TabsContent value="activities">
        <Card class="w-fit">
          <CardContent class="grid gap-6">
            <SoaActivitiesList
              v-if="activeTab === 'activities'"
              :soa-id="localSoa.id ?? null" />
          </CardContent>
        </Card>
      </TabsContent>
      <TabsContent value="amount_management">
        <Card>
          <CardContent class="grid gap-6">
            <AmountManagementForm
              v-if="activeTab === 'amount_management'"
              :soa="localSoa"
              @adjusted="onAmountAdjusted" />
          </CardContent>
        </Card>
      </TabsContent>
      <TabsContent v-if="hasPermission(`${slug}.account_branch_members`)" value="members">
        <Card>
          <CardContent class="grid gap-6">
            <AccountBranchMembers
              v-if="activeTab === 'members'"
              :account_code="localSoa.account_code ?? null"
              :branch_code="localSoa.branch_code ?? null"
              :soa="localSoa" />
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>
