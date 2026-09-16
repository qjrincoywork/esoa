import { inject, provide, type InjectionKey } from 'vue';

/**
 * The link between a field and the controls rendered inside it.
 *
 * A field drops its validation message as soon as the reader starts correcting it, and
 * for a native control that needs no wiring at all: `<input>`, `<textarea>` and
 * `<select>` fire `input`/`change`, which bubble up to the field wrapper on their own
 * ({@see \resources/js/components/FormField.vue}).
 *
 * Controls built out of buttons and popovers — a reka Select, the searchable combobox —
 * fire nothing a wrapper can hear, so their message used to sit there contradicting a
 * field the reader had already filled in. They announce it through this instead, which
 * says the same thing as the native event without pretending to be one: dispatching a
 * synthetic `change` from a `<button>` would reach any listener on the surrounding
 * form, and mean something that is not true of that element.
 */
export interface FormFieldContext {
    /** Tell the field the reader has just changed this control's value. */
    valueChanged: () => void;
}

const FORM_FIELD_KEY: InjectionKey<FormFieldContext> = Symbol('form-field');

/** Called by the field wrapper; every control inside it can then reach {@see useFormField}. */
export function provideFormField(context: FormFieldContext): void {
    provide(FORM_FIELD_KEY, context);
}

/**
 * The field this control is rendered inside, or null when there is none.
 *
 * Optional on purpose: these controls are shared UI primitives and are used outside a
 * field too — in listing filters and search bars, which have nothing to correct — so a
 * missing field is an ordinary case, not a mistake.
 */
export function useFormField(): FormFieldContext | null {
    return inject(FORM_FIELD_KEY, null);
}
