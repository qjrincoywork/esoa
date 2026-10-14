<script setup lang="ts">
/**
 * Which email notifications users receive, one setting per notification.
 *
 * A single user is shown each notification as it stands, as a switch. A selection mixes
 * users with different settings, so each notification starts at "Keep" and only the
 * ones turned On or Off are sent — the rest stay as each user has them. The list itself
 * is the server's (`EmailNotification::list()`), so a new notification appears here with
 * no change to this form.
 */
import { computed, onMounted, ref } from 'vue';
import { Info } from 'lucide-vue-next';
import Switch from '@/components/ui/switch/Switch.vue';
import type {
    EmailNotificationOption,
    EmailNotificationSettings,
    User,
    UserEmailNotification,
} from '@/composables/users';

const props = defineProps<{
    users: User[];
    notifications: EmailNotificationOption[];
    onReady: (api: { getSettings: () => EmailNotificationSettings }) => void;
}>();

const MAX_SHOWN = 5;

const single = computed(() => props.users.length === 1);
const displayedUsers = computed(() => props.users.slice(0, MAX_SHOWN));
const hiddenCount = computed(() => Math.max(0, props.users.length - MAX_SHOWN));

/** What a single user receives today; anything the row does not mention counts as on. */
const currentlyEnabled = (value: string): boolean => {
    const rows = (props.users[0]?.email_notifications ?? []) as UserEmailNotification[];

    return rows.find((row) => row.value === value)?.enabled ?? true;
};

/** Per notification: on, off, or (bulk only) null for "keep as each user has it". */
const choices = ref<Record<string, boolean | null>>(
    Object.fromEntries(props.notifications.map(({ value }) => [value, single.value ? currentlyEnabled(value) : null])),
);

const BULK_OPTIONS: { value: boolean | null; label: string }[] = [
    { value: null, label: 'Keep' },
    { value: true, label: 'On' },
    { value: false, label: 'Off' },
];

const getSettings = (): EmailNotificationSettings =>
    Object.fromEntries(
        Object.entries(choices.value).filter((entry): entry is [string, boolean] => entry[1] !== null),
    );

onMounted(() => props.onReady({ getSettings }));
</script>

<template>
    <div class="space-y-4">
        <div v-if="!single">
            <p class="mb-2 text-sm text-muted-foreground">Selected users:</p>
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="user in displayedUsers"
                    :key="user.id"
                    class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">
                    {{ user.username || user.email || user.id }}
                </span>
                <span
                    v-if="hiddenCount > 0"
                    class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                    +{{ hiddenCount }} more
                </span>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                Only the notifications you turn on or off are changed; "Keep" leaves each user's setting as it is.
            </p>
        </div>

        <ul class="divide-y rounded-md border">
            <li
                v-for="notification in notifications"
                :key="notification.value"
                class="flex flex-col gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <p :id="`email-notification-${notification.value}`" class="text-sm font-medium">{{ notification.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ notification.description }}</p>
                </div>

                <Switch
                    v-if="single"
                    v-model="choices[notification.value]"
                    class="shrink-0"
                    :aria-labelledby="`email-notification-${notification.value}`" />

                <div
                    v-else
                    role="radiogroup"
                    :aria-labelledby="`email-notification-${notification.value}`"
                    class="inline-flex shrink-0 self-start rounded-md border p-0.5 sm:self-auto">
                    <button
                        v-for="option in BULK_OPTIONS"
                        :key="option.label"
                        type="button"
                        role="radio"
                        :aria-checked="choices[notification.value] === option.value"
                        class="cursor-pointer rounded px-2.5 py-1 text-xs font-medium transition-colors"
                        :class="choices[notification.value] === option.value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                        @click="choices[notification.value] = option.value">
                        {{ option.label }}
                    </button>
                </div>
            </li>
        </ul>

        <p class="flex items-start gap-1.5 text-xs text-muted-foreground">
            <Info class="mt-px size-3.5 shrink-0" aria-hidden="true" />
            Login credentials and password reset emails are always sent and cannot be turned off.
        </p>
    </div>
</template>
