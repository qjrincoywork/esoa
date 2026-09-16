<script setup lang="ts">
import type { SelectRootEmits, SelectRootProps } from "reka-ui"
import { SelectRoot, useForwardPropsEmits } from "reka-ui"
import { useFormField } from '@/composables/useFormField'

const props = defineProps<SelectRootProps>()
const emits = defineEmits<SelectRootEmits>()

const forwarded = useForwardPropsEmits(props, emits)

/**
 * Null outside a form field — this select is just as often a listing filter.
 *
 * Reka does bubble a native `change` of its own, but only when the select is given a
 * `name` and sits inside a form; most of ours are bound by `v-model` alone, so the
 * field would otherwise go on showing a message for a value the reader has just chosen.
 */
const field = useFormField()
</script>

<template>
  <!--
    The listener merges with the one `forwarded` already carries rather than replacing
    it, so whatever the caller bound to `update:modelValue` still runs.
  -->
  <SelectRoot v-bind="forwarded" @update:model-value="field?.valueChanged()">
    <slot />
  </SelectRoot>
</template>
