<script setup lang="ts">
import * as XLSX from "xlsx"
import { ref, onMounted } from "vue"

const props = defineProps<{
  url: string
}>()

const headers = ref<string[]>([])
const rows = ref<any[][]>([])
const loading = ref(true)

onMounted(async () => {
  const response = await fetch(props.url)
  const arrayBuffer = await response.arrayBuffer()

  const workbook = XLSX.read(arrayBuffer, { type: "array" })
  const sheet = workbook.Sheets[workbook.SheetNames[0]]
  const data = XLSX.utils.sheet_to_json(sheet, { header: 1 })

  headers.value = data[0] as string[]
  rows.value = data.slice(1)
  loading.value = false
})
</script>

<template>
  <div class="border rounded-lg overflow-auto max-h-[600px]">
    <div v-if="loading" class="p-4 text-muted-foreground">
      Loading preview…
    </div>

    <table v-else class="w-full text-sm">
      <thead class="sticky top-0 bg-background">
        <tr>
          <th
            v-for="h in headers"
            :key="h"
            class="border px-2 py-1 text-left font-medium"
          >
            {{ h }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="(row, i) in rows" :key="i">
          <td
            v-for="(cell, j) in row"
            :key="j"
            class="border px-2 py-1"
          >
            {{ cell }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
