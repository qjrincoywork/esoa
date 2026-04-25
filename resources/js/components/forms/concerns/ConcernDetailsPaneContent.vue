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
import { Button } from '@/components/ui/button'

type Concern = {
  id?: number
  billing_invoice?: string
  title?: string
  type?: string
  description?: string
  status?: string
  status_color?: string
  attachment?: string
  attachment_preview_token?: string
  created_by?: string
  created_at?: string
}

const props = defineProps({
  concern: {
    type: Object as unknown as () => Concern,
    default: () => ({}),
  },
});
const page = usePage();
const localConcern = ref<Concern>({})
watch(
  () => props.concern,
  (p) => {
    localConcern.value = { ...(p as Concern) }
  },
  { immediate: true, deep: true },
)

const activeTab = ref('details')
const openTab = () => {
  window.open(`/concerns/preview_file?token=${encodeURIComponent(localConcern.value?.attachment_preview_token)}`, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <div class="flex w-full flex-col">
    <Tabs v-model="activeTab" default-value="details">
      <TabsList>
        <TabsTrigger class="cursor-pointer" value="details">
          Details
        </TabsTrigger>
      </TabsList>
      <TabsContent value="details">
        <Card>
          <CardContent class="grid gap-3">
            <ul class="my-6 ml-6 list-disc [&>li]:mt-2">
              <li>Billing Invoice: <span class="font-bold">{{ localConcern.billing_invoice }}</span></li>
              <li>Title: <span class="font-bold">{{ localConcern.title }}</span></li>
              <li>Type: <span class="font-bold">{{ localConcern.type }}</span></li>
              <li>Description: <p class="leading-7 [&:not(:first-child)]:mt-6 whitespace-pre-line">{{ localConcern.description }}</p></li>
              <li>Status: <span :class="[
                  'px-2 py-1 rounded-md text-xs font-medium',
                  localConcern.status_color
                ]">{{ localConcern.status }}</span>
              </li>
              <li>Created By: <span class="font-bold">{{ localConcern.created_by }}</span></li>
              <li>Created At: <span class="font-bold">{{ localConcern.created_at }}</span></li>
              <li v-if="localConcern.attachment">
                <Button :onClick="openTab" class="cursor-pointer">View File {{ localConcern.attachment.split('/').pop() }}</Button>
              </li>
            </ul>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>
