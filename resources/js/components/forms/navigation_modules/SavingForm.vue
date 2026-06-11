<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectTrigger, SelectContent, SelectGroup,
    SelectItem, SelectValue, SelectLabel,
} from '@/components/ui/select';

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
        <div class="grid gap-2">
            <Label for="nm-name">Name <span class="text-red-500">*</span></Label>
            <Input
                id="nm-name"
                name="name"
                placeholder="e.g. Edit Users"
                :default-value="navigationModule?.name"
                @input="onNameInput"
            />
        </div>

        <!-- Slug -->
        <div class="grid gap-2">
            <Label for="nm-slug">
                Slug <span class="text-red-500">*</span>
                <span class="text-xs text-[var(--color-text-muted)] ml-1">(e.g. users.edit)</span>
            </Label>
            <Input
                id="nm-slug"
                name="slug"
                placeholder="navigation.action"
                v-model="slugValue"
            />
        </div>

        <!-- Navigation (required) -->
        <div class="grid gap-2">
            <Label for="nm-navigation">Navigation <span class="text-red-500">*</span></Label>
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
        </div>

        <!-- Permission (optional) -->
        <div class="grid gap-2">
            <Label for="nm-permission">Permission</Label>
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
        </div>

        <!-- URL -->
        <div class="grid gap-2">
            <Label for="nm-url">URL</Label>
            <Input
                id="nm-url"
                name="url"
                placeholder="/path/to/page"
                :default-value="navigationModule?.url"
            />
        </div>

        <!-- Icon -->
        <div class="grid gap-2">
            <Label for="nm-icon">Icon</Label>
            <Input
                id="nm-icon"
                name="icon"
                placeholder="e.g. LayoutDashboard"
                :default-value="navigationModule?.icon"
            />
        </div>

        <!-- Parent Module (ref_id, optional) -->
        <div class="grid gap-2">
            <Label for="nm-ref">Parent Module</Label>
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
        </div>

        <!-- Status -->
        <div class="grid gap-2">
            <Label for="nm-status">Status</Label>
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
        </div>

        <!-- Color -->
        <div class="grid gap-2">
            <Label for="nm-color">Color</Label>
            <Input
                id="nm-color"
                name="color"
                placeholder="e.g. blue"
                :default-value="navigationModule?.color"
            />
        </div>

        <!-- Order Number -->
        <div class="grid gap-2">
            <Label for="nm-order">Order</Label>
            <Input
                id="nm-order"
                type="number"
                name="order_number"
                placeholder="0"
                min="0"
                :default-value="navigationModule?.order_number ?? 0"
            />
        </div>
    </form>
</template>
