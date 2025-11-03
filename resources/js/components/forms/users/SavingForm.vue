<script setup>
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
  suffixes: {
    type: Array,
    required: true,
    default: () => [],
  },
});

// Prefer nested user_detail if present; fallback to user for backward compatibility
const detail = computed(() => props.user?.user_detail ?? props.user?.userDetail ?? props.user ?? {});
// const suffixes = computed(() => props.suffixes ?? {});
const suffixes = props.suffixes;
</script>

<template>
  <form id="user-edit-form" class="space-y-3">
    <div class="grid gap-2">
        <Label for="first_name">First Name</Label>
        <Input
            id="first_name"
            class="mt-1 block w-full"
            name="first_name"
            :default-value="detail.first_name"
            autocomplete="first_name"
            placeholder="First Name"
        />
    </div>

    <div class="grid gap-2">
        <Label for="middle_name">Middle Name</Label>
        <Input
            id="middle_name"
            class="mt-1 block w-full"
            name="middle_name"
            :default-value="detail.middle_name"
            autocomplete="middle_name"
            placeholder="Middle Name"
        />
    </div>

    <div class="grid gap-2">
        <Label for="last_name">Last Name</Label>
        <Input
            id="last_name"
            class="mt-1 block w-full"
            name="last_name"
            :default-value="detail.last_name"
            autocomplete="last_name"
            placeholder="Last Name"
        />
    </div>

    <div class="grid gap-2">
        <Label for="suffix">Suffix</Label>
        <Select
            id="suffix"
            class="mt-1 block w-full"
            name="suffix_id"
            :default-value="detail.suffix_id ? String(detail.suffix_id) : undefined"
        >
          <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a suffix" />
          </SelectTrigger>
          <SelectContent class="w-full">
              <SelectGroup>
                  <SelectLabel>Suffix</SelectLabel>
                  <!--  Dynamically loop suffix items -->
                  <SelectItem
                          v-for="suffix in suffixes"
                          :key="suffix.id"
                          :value="String(suffix.id)"
                  >
                  {{ suffix.name }}
                  </SelectItem>
              </SelectGroup>
          </SelectContent>
        </Select>
    </div>

    <div class="grid gap-2">
        <Label for="birthdate">Birth Date</Label>
        <Input
            id="birthdate"
            type="date"
            class="mt-1 block w-full"
            name="birthdate"
            :default-value="detail.birthdate"
            autocomplete="birthdate"
            placeholder="Birth Date"
        />
    </div>

    <div class="grid gap-2">
        <Label for="employee_no">Employee No</Label>
        <Input
            id="employee_no"
            class="mt-1 block w-full"
            name="employee_no"
            :default-value="detail.employee_no"
            autocomplete="employee_no"
            placeholder="Employee No"
        />
    </div>
    <div class="grid gap-2">
        <Label for="username">Username</Label>
        <Input
            id="username"
            class="mt-1 block w-full"
            name="username"
            :default-value="user.username"
            autocomplete="username"
            placeholder="Username"
        />
    </div>

    <div class="grid gap-2">
        <Label for="email">Email</Label>
        <Input
            id="email"
            class="mt-1 block w-full"
            name="email"
            :default-value="user.email"
            autocomplete="email"
            placeholder="Email"
        />
    </div>
  </form>
</template>
