<script setup lang="ts">
import { computed, ref } from 'vue';
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
  amount?: number
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
const soa = computed<Soa>(() => props.soa as Soa)
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
      </TabsList>
      <TabsContent value="details">
        <Card>
          <!-- <CardHeader>
            <CardTitle>Account</CardTitle>
            <CardDescription>
              Make changes to your account here. Click save when you're
              done.
            </CardDescription>
          </CardHeader> -->
          <CardContent class="grid gap-3">
            <ul class="my-6 ml-6 list-disc [&>li]:mt-2">
              <li>Account Name: <span class="font-bold">{{ soa.account_name }}</span></li>
              <li v-if="soa.branch_name">Branch Name: <span class="font-bold">{{ soa.branch_name }}</span></li>
              <li>Billing Reference: <span class="font-bold">{{ soa.billing_ref }}</span></li>
              <li>SOA Number: <span class="font-bold">{{ soa.soa_number }}</span></li>
              <li>Bill Type: <span class="font-bold">{{ soa.bill_type }}</span></li>
              <li>Status: <span :class="[
                  'px-2 py-1 rounded-md text-xs font-medium',
                  soa.status_color
                ]">{{ soa.status }}</span>
              </li>
              <li>Due Date:
                <span class="font-bold">{{ soa.due_date }}
                  <Badge variant="secondary">
                    {{ soa.due_in }}
                  </Badge>
                </span>
              </li>
              <li>Bill Date: <span class="font-bold">{{ soa.created_at }} </span></li>
              <li>Period Coverage: <span class="font-bold">{{ soa.period_coverage }}</span></li>
              <li>Amount: <span class="font-bold">{{ soa.amount }}</span></li>
            </ul>
          </CardContent>
          <!-- <CardFooter>
            <Button>Save changes</Button>
          </CardFooter> -->
        </Card>
      </TabsContent>
      <TabsContent value="activities">
        <Card>
          <CardContent class="grid gap-6">
            <!-- On-demand: component mounts only when Activities tab is selected -->
            <SoaActivitiesList
              v-if="activeTab === 'activities'"
              :soa-id="soa.id ?? null" />
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>
