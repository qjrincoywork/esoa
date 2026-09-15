<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select, SelectTrigger, SelectContent, SelectGroup,
    SelectItem, SelectValue, SelectLabel,
} from '@/components/ui/select';
import FormField from '@/components/FormField.vue';

type Status       = { value: number; name: string };
type Navigation   = { id: number; name: string };
type Permission   = { id: number; name: string };
type ParentModule = { id: number; name: string; navigation_id: number; navigation?: Navigation };
type NavigationModule = {
    id?: number;
    name?: string;
    slug?: string;
    url?: string;
    icon?: string;
    navigation_id?: number | null;
    permission_id?: number | null;
    color?: string;
    ref_id?: number | null;
    order_number?: number | null;
    status?: number;
};

const props = defineProps<{
    navigationModule?: NavigationModule;
    navigations: Navigation[];
    permissions: Permission[];
    parentModules: ParentModule[];
    statuses: Status[];
    onReady: (api: { getFormData: () => FormData | null }) => void;
}>();

const formRef = ref<HTMLFormElement | null>(null);

// Reactive select values — Shadcn Select v-model requires consistent types
const selectedNavigationId = ref<number | null>(props.navigationModule?.navigation_id ?? null);
const selectedPermissionId = ref<number | null>(props.navigationModule?.permission_id ?? null);
const selectedRefId        = ref<number | null>(props.navigationModule?.ref_id ?? null);
const selectedStatus       = ref<number>(props.navigationModule?.status ?? 1);

// Auto-generate slug from name on new records only
const slugValue  = ref(props.navigationModule?.slug ?? '');
const isNewRecord = !props.navigationModule?.id;

function onNameInput(e: Event) {
    if (!isNewRecord) return;
    const name = (e.target as HTMLInputElement).value;
    slugValue.value = name.toLowerCase()
        .replace(/\s+/g, '_')
        .replace(/[^a-z0-9_.]/g, '');
}

function getFormData(): FormData | null {
    if (!formRef.value) return null;

    const fd = new FormData(formRef.value);

    // Overwrite with reactive select values (Shadcn Select hidden inputs may lag)
    fd.set('navigation_id', selectedNavigationId.value !== null ? String(selectedNavigationId.value) : '');
    fd.set('status',        String(selectedStatus.value));
    fd.set('slug',          slugValue.value);

    if (selectedPermissionId.value !== null) {
        fd.set('permission_id', String(selectedPermissionId.value));
    } else {
        fd.delete('permission_id');
    }

    if (selectedRefId.value !== null) {
        fd.set('ref_id', String(selectedRefId.value));
    } else {
        fd.delete('ref_id');
    }

    return fd;
}

onMounted(() => props.onReady({ getFormData }));
</script>

<template>
    <form ref="formRef" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Hidden id for update -->
        <input v-if="navigationModule?.id" type="hidden" name="id" :value="navigationModule.id" />

        <!-- Name -->
        <FormField name="name" label="Name" for="nm-name" required>
            <Input
                id="nm-name"
                name="name"
                placeholder="e.g. Edit Users"
                :default-value="navigationModule?.name"
                @input="onNameInput"
            />
        </FormField>

        <!-- Slug -->
        <FormField name="slug" for="nm-slug">
            <template #label>
                Slug <span class="text-red-500">*</span>
                <span class="text-xs text-[var(--color-text-muted)] ml-1">(e.g. users.edit)</span>
            </template>
            <Input
                id="nm-slug"
                name="slug"
                placeholder="navigation.action"
                v-model="slugValue"
            />
        </FormField>

        <!-- Navigation (required) -->
        <FormField name="navigation_id" label="Navigation" for="nm-navigation" required>
            <Select v-model="selectedNavigationId">
                <SelectTrigger id="nm-navigation" class="w-full">
                    <SelectValue placeholder="Select navigation" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Navigation</SelectLabel>
                        <SelectItem
                            v-for="nav in navigations"
                            :key="nav.id"
                            :value="nav.id"
                        >
                            {{ nav.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </FormField>

        <!-- Permission (optional) -->
        <FormField name="permission_id" label="Permission" for="nm-permission">
            <Select v-model="selectedPermissionId">
                <SelectTrigger id="nm-permission" class="w-full">
                    <SelectValue placeholder="None (public)" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Permission</SelectLabel>
                        <SelectItem :value="null">None</SelectItem>
                        <SelectItem
                            v-for="perm in permissions"
                            :key="perm.id"
                            :value="perm.id"
                        >
                            {{ perm.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </FormField>

        <!-- URL -->
        <FormField name="url" label="URL" for="nm-url">
            <Input
                id="nm-url"
                name="url"
                placeholder="/path/to/page"
                :default-value="navigationModule?.url"
            />
        </FormField>

        <!-- Icon -->
        <FormField name="icon" label="Icon" for="nm-icon">
            <Input
                id="nm-icon"
                name="icon"
                placeholder="e.g. LayoutDashboard"
                :default-value="navigationModule?.icon"
            />
        </FormField>

        <!-- Parent Module (ref_id, optional) -->
        <FormField name="ref_id" label="Parent Module" for="nm-ref">
            <Select v-model="selectedRefId">
                <SelectTrigger id="nm-ref" class="w-full">
                    <SelectValue placeholder="None (top-level)" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Parent Module</SelectLabel>
                        <SelectItem :value="null">None (top-level)</SelectItem>
                        <SelectItem
                            v-for="mod in parentModules"
                            :key="mod.id"
                            :value="mod.id"
                        >
                            {{ mod.navigation?.name }} — {{ mod.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </FormField>

        <!-- Status -->
        <FormField name="status" label="Status" for="nm-status">
            <Select v-model="selectedStatus">
                <SelectTrigger id="nm-status" class="w-full">
                    <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>Status</SelectLabel>
                        <SelectItem
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
        </FormField>

        <!-- Color -->
        <FormField name="color" label="Color" for="nm-color">
            <Input
                id="nm-color"
                name="color"
                placeholder="e.g. blue"
                :default-value="navigationModule?.color"
            />
        </FormField>

        <!-- Order Number -->
        <FormField name="order_number" label="Order" for="nm-order">
            <Input
                id="nm-order"
                type="number"
                name="order_number"
                placeholder="0"
                min="0"
                :default-value="navigationModule?.order_number ?? 0"
            />
        </FormField>
    </form>
</template>
