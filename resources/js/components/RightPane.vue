<script setup="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';

const width = ref(320)
const resizing = ref(false)

function startResize() {
  resizing.value = true
}

function stopResize() {
  resizing.value = false
}

function resize(e) {
  if (!resizing.value) return
  width.value = window.innerWidth - e.clientX
}

onMounted(() => {
  window.addEventListener('mousemove', resize)
  window.addEventListener('mouseup', stopResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('mousemove', resize)
  window.removeEventListener('mouseup', stopResize)
})
const open = ref(true)
</script>

<template>
  <div class="container">
    <main class="content">
      <button @click="open = !open">
        Toggle Right Pane
      </button>
      <p>Main content here</p>
    </main>

    <Transition name="modal" :duration="150">
      <button @click="open = !open">
        Toggle Right Pane
      </button>
      <aside v-if="open" class="right-pane">
        Floating Right Pane
      </aside>
    </Transition>
  </div>
</template>

<style scoped>
.container {
  position: relative;
  height: 100vh;
}

.content {
  padding: 16px;
}

/* animation */
.slide-right-enter-active,
.slide-right-leave-active {
  transition: transform 0.25s ease;
}
.slide-right-enter-from,
.slide-right-leave-to {
  transform: translateX(100%);
}
.right-pane {
  position: fixed;
  top: 0;
  right: 0;
  height: 100vh;
  background: #fff;
  border-left: 1px solid #ddd;
  box-shadow: -4px 0 12px rgba(0,0,0,0.1);
}

.resize-handle {
  position: absolute;
  left: 0;
  top: 0;
  width: 6px;
  height: 100%;
  cursor: ew-resize;
}
</style>
