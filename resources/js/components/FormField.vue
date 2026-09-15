<script setup lang="ts">
/**
 * One labelled form field that carries its own validation message.
 *
 * Replaces the `<div class="grid gap-2"><Label/>…</div>` every form was already
 * repeating, and adds the part that was missing: when the server rejects this field,
 * the message appears under it and the control inside is highlighted.
 *
 * The highlight is not applied to the control here — it cannot be, because the control
 * is whatever the caller slots in (an `Input`, a reka `Select` trigger, a combobox
 * button, a file input). Instead the wrapper marks itself `data-invalid`, and one rule
 * in `app.css` styles whichever control is inside it. That is what lets a field of any
 * shape highlight without every form having to bind a class to every control.
 */
import type { HTMLAttributes } from 'vue';
import { computed, nextTick, ref, useId, watch } from 'vue';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useFormErrors, type FieldName } from '@/composables/useFormErrors';

const props = withDefaults(
    defineProps<{
        /** Validation key(s) this field answers for — the input's `name`, in any notation. */
        name?: FieldName;
        /** Label text; omitted for controls that draw their own (e.g. SearchableCombobox). */
        label?: string;
        /** Id of the control the label points at. Defaults to the first name. */
        for?: string;
        /** Show the required asterisk. Presentational only — the server decides. */
        required?: boolean;
        /** Also surface messages nested under the name, e.g. `user_accounts.0.branch_code`. */
        nested?: boolean;
        /** Extra classes for the wrapper, typically grid placement (`md:col-span-2`). */
        class?: HTMLAttributes['class'];
    }>(),
    {
        required: false,
        nested: false,
    },
);

const { errorFor, clearError } = useFormErrors();

const message = computed<string | undefined>(() => errorFor(props.name, { nested: props.nested }));
const invalid = computed<boolean>(() => message.value !== undefined);

const messageId = `field-error-${useId()}`;
const labelFor = computed<string | undefined>(() => {
    if (props.for) return props.for;

    return Array.isArray(props.name) ? props.name[0] : props.name ?? undefined;
});

const root = ref<HTMLElement | null>(null);

/**
 * Controls that can hold the invalid state, in the order a field would use them.
 * Hidden inputs are skipped — they carry a value but nobody can focus one to fix it.
 */
const CONTROL_SELECTOR = [
    'input:not([type="hidden"])',
    'textarea',
    'select',
    '[role="combobox"]',
    '[data-slot="select-trigger"]',
].join(', ');

/**
 * Tell assistive technology what the highlight says, by marking the slotted control
 * itself rather than only the wrapper around it.
 *
 * Reached through the DOM because the control belongs to the caller's slot: a wrapper
 * cannot bind attributes onto content it did not render. The visible highlight comes
 * from CSS and does not depend on this, so a field whose control this does not
 * recognise still looks and reads correctly — it just loses the announcement.
 */
watch(
    [message, root],
    async () => {
        await nextTick();

        const control = root.value?.querySelector(CONTROL_SELECTOR);

        if (!control) return;

        if (message.value !== undefined) {
            control.setAttribute('aria-invalid', 'true');
            control.setAttribute('aria-describedby', messageId);
            return;
        }

        control.removeAttribute('aria-invalid');
        control.removeAttribute('aria-describedby');
    },
    { immediate: true },
);

/**
 * Drop the message as soon as the reader starts correcting the field.
 *
 * Listened for on the wrapper rather than the control, so this works for whatever was
 * slotted in without the form having to wire an event per field — `input` and `change`
 * both bubble. Controls that emit neither (a reka Select, a combobox) keep their
 * message until the next submit answers for them, which is the truthful thing to show
 * in the meantime.
 */
const handleCorrection = (): void => {
    if (invalid.value) clearError(props.name);
};
</script>

<template>
    <div
        ref="root"
        class="grid gap-2"
        :class="props.class"
        :data-invalid="invalid ? 'true' : undefined"
        @input="handleCorrection"
        @change="handleCorrection">
        <!--
            A `label` slot owns the whole label, asterisk included — labels that need one
            carry a hint or an extra mark after the field name, and appending the asterisk
            after that would read as though it belonged to the hint.
        -->
        <Label v-if="label || $slots.label" :for="labelFor">
            <template v-if="$slots.label">
                <slot name="label" />
            </template>
            <template v-else>
                {{ label }}<span v-if="required" class="text-red-400">*</span>
            </template>
        </Label>

        <slot :invalid="invalid" :message="message" />

        <InputError :id="messageId" :message="message" />
    </div>
</template>
