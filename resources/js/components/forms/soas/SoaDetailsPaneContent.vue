<script setup lang="ts">
import { ref, watch } from 'vue';
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
import SoaActivitiesList from '@/components/forms/soas/SoaActivitiesList.vue';
import AmountManagementForm from '@/components/forms/soas/AmountManagementForm.vue';

type SoaActivity = {
  id?: number
  user_id?: number
  name?: string
  event?: string
  from?: unknown
  to?: unknown
  created_at?: string
}

type Soa = {
  id?: number
  user_id?: number
  soa_number?: string
  account_name?: string
  account_code?: string
  branch_name?: string
  branch_code?: string
  billing_ref?: string
  bill_type?: number
  status?: number
  status_color?: string
  created_at?: string
  due_date?: string
  due_in?: string
  period_date_from?: string
  period_date_to?: string
  period_coverage?: string
  amount?: string | number
  amount_raw?: number
  file_pdf?: string
  file_xls?: string
  soa_activities?: SoaActivity[]
}
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
</script>

<template>
  <div class="flex w-full flex-col">
    <Tabs v-model="activeTab" default-value="details">
      <TabsList>
        <TabsTrigger value="details">
          Details
        </TabsTrigger>
        <TabsTrigger value="activities">
          Soa Activities
        </TabsTrigger>
        <TabsTrigger value="amount_management" v-if="page.props.auth.user?.user_detail?.employee_no || page.props.auth.is_superadmin">
          Amount Management
        </TabsTrigger>
      </TabsList>
      <TabsContent value="details">
        <Card>
          <CardContent class="grid gap-3">
            <ul class="my-6 ml-6 list-disc [&>li]:mt-2">
              <li>Account Name: <span class="font-bold">{{ localSoa.account_name }}</span></li>
              <li v-if="localSoa.branch_name">Branch Name: <span class="font-bold">{{ localSoa.branch_name }}</span></li>
              <li>Billing Reference: <span class="font-bold">{{ localSoa.billing_ref }}</span></li>
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
            </ul>
          </CardContent>
        </Card>
      </TabsContent>
      <TabsContent value="activities">
        <Card>
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
    </Tabs>
  </div>
</template>
