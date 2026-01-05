<script setup lang="ts">
import { ref, onMounted } from "vue"
import pdfjsLib from "@/lib/pdfjs"

const props = defineProps<{
  url: string
}>()

const canvasRef = ref<HTMLCanvasElement | null>(null)

onMounted(async () => {
  const pdf = await pdfjsLib.getDocument(props.url).promise
  const page = await pdf.getPage(1)

  const viewport = page.getViewport({ scale: 1.5 })
  const canvas = canvasRef.value!
  const context = canvas.getContext("2d")!

  canvas.width = viewport.width
  canvas.height = viewport.height

  await page.render({
    canvasContext: context,
    viewport
  }).promise
})
</script>

<template>
  <div class="border rounded-lg p-2">
    <canvas ref="canvasRef" />
  </div>
</template>
