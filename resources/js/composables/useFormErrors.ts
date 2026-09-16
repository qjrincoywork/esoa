import { computed, nextTick, ref } from 'vue';

/**
 * Field-level validation messages for the form currently on screen.
 *
 * The server already says exactly which field it rejected and why; until now that
 * detail was thrown away and only the summary line reached the user as a toast, which
 * on a form of twenty fields does not say which one to go and fix. This holds the
 * per-field detail so the field itself can carry its own message.
 *
 * The bag is module-scoped, like {@see useModal} and {@see useLoader}: one form is
 * being filled in at a time, and every part of it — the request that fails, the fields
 * that render, the modal that opens the next one — needs the same answer without
 * having to be wired to each other. It is filled in centrally by {@see useAjax} from
 * whatever the server rejected, and cleared whenever a form is opened or closed.
 */

/** A rejected-field map: Laravel's `errors`, whichever response envelope carries it. */
export type ValidationErrorBag = Record<string, string[] | string>;

/** One field, or several whose messages should surface in the same place. */
export type FieldName = string | string[] | null | undefined;

export interface FieldLookupOptions {
    /**
     * Also match keys nested under the name, so a field standing for a repeating group
     * (`user_accounts`) surfaces `user_accounts.0.account_code` too.
     */
    nested?: boolean;
}

const errors = ref<Record<string, string[]>>({});

/**
 * Rewrite an input's `name` into the dotted key the server reports it under, so a
 * field can be named the way HTML wants and still find its own message:
 * `user_accounts[0][account_code]` and `user_accounts.0.account_code` are one key.
 */
const toFieldKey = (name: string): string =>
    name
        .trim()
        .replace(/\[([^\]]*)\]/g, (_match, segment: string) => (segment === '' ? '' : `.${segment}`));

/** Reduce whatever shape the server sent to one list of messages per field key. */
const normalize = (bag: ValidationErrorBag | null | undefined): Record<string, string[]> => {
    const normalized: Record<string, string[]> = {};

    for (const [field, messages] of Object.entries(bag ?? {})) {
        const list = (Array.isArray(messages) ? messages : [messages])
            .filter((message) => message !== null && message !== undefined && message !== '')
            .map((message) => String(message));

        if (list.length > 0) {
            normalized[toFieldKey(field)] = list;
        }
    }

    return normalized;
};

/** The names to look up, as dotted keys; a blank or absent name matches nothing. */
const toFieldKeys = (name: FieldName): string[] =>
    (Array.isArray(name) ? name : [name])
        .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
        .map(toFieldKey);

/**
 * Bring the first rejected field into view.
 *
 * A long form scrolls, so the field the server objected to is often below the fold and
 * the reader is left looking at an unchanged screen. Waiting a tick lets the fields
 * mark themselves invalid first, so there is something to scroll to.
 */
const revealFirstInvalidField = async (): Promise<void> => {
    if (typeof document === 'undefined') return;

    await nextTick();

    document
        .querySelector('[data-invalid="true"]')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

export function useFormErrors() {
    /** Replace the bag with what the server just rejected. */
    const setErrors = (bag: ValidationErrorBag | null | undefined): void => {
        errors.value = normalize(bag);

        if (Object.keys(errors.value).length > 0) {
            void revealFirstInvalidField();
        }
    };

    /** Forget every message — a new form is being opened, or the last one succeeded. */
    const clearErrors = (): void => {
        if (Object.keys(errors.value).length > 0) {
            errors.value = {};
        }
    };

    /**
     * Forget one field's messages, for when the reader starts correcting it.
     *
     * Rebuilt rather than mutated in place so the change is one reactive update for
     * every field reading the bag, not one per key removed.
     */
    const clearError = (name: FieldName): void => {
        const keys = toFieldKeys(name);

        if (keys.length === 0) return;

        const remaining = Object.fromEntries(
            Object.entries(errors.value).filter(([key]) => !keys.includes(key)),
        );

        if (Object.keys(remaining).length !== Object.keys(errors.value).length) {
            errors.value = remaining;
        }
    };

    /** Every message held against a field, in the order the server reported them. */
    const messagesFor = (name: FieldName, options: FieldLookupOptions = {}): string[] => {
        const keys = toFieldKeys(name);

        if (keys.length === 0) return [];

        return Object.entries(errors.value)
            .filter(([key]) =>
                keys.some((wanted) => key === wanted || (options.nested === true && key.startsWith(`${wanted}.`))),
            )
            .flatMap(([, messages]) => messages);
    };

    /**
     * The message a field shows.
     *
     * The first one only: a field holding several is showing several ways of saying the
     * same value is wrong, and a growing block of red under one input pushes the rest of
     * the form around as the reader types.
     */
    const errorFor = (name: FieldName, options: FieldLookupOptions = {}): string | undefined =>
        messagesFor(name, options)[0];

    const hasError = (name: FieldName, options: FieldLookupOptions = {}): boolean =>
        messagesFor(name, options).length > 0;

    /** Whether anything at all was rejected, for summaries above a form. */
    const hasErrors = computed<boolean>(() => Object.keys(errors.value).length > 0);

    return {
        errors,
        hasErrors,
        setErrors,
        clearErrors,
        clearError,
        messagesFor,
        errorFor,
        hasError,
    };
}
