<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <!--
        Rendered only when there is something to say. It used to be hidden rather than
        removed, which left an empty node for `aria-describedby` to point at and an
        empty row inside the grid layouts that fields are built from.
    -->
    <div v-if="message" role="alert" aria-live="polite">
        <p class="text-sm text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
