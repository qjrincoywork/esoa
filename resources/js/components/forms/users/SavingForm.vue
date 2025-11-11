<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

type Suffix = { id: string | number; name: string }
type Gender = { id: string | number; name: string }
type CivilStatus = { id: string | number; name: string }
type Citizenship = { id: string | number; name: string }
type Department = { id: string | number; name: string }
type Position = { id: string | number; name: string }
type UserDetail = {
  gender_id?: number
  civil_status_id?: number
  citizenship_id?: number
  department_id?: number
  position_id?: number
  first_name?: string
  middle_name?: string
  last_name?: string
  suffix?: string | number
  birthdate?: string
  employee_no?: string
}
type UserBasic = {
  id?: number
  username?: string | number
  email?: string | number
  user_detail?: UserDetail
  userDetail?: UserDetail
}

const props = defineProps({
  user: {
    type: Object as unknown as () => UserBasic,
    default: () => [],
  },
  suffixes: {
    type: Array as unknown as () => Suffix[],
    required: true,
    default: () => [],
  },
  genders: {
    type: Array as unknown as () => Gender[],
    required: true,
    default: () => [],
  },
  civil_statuses: {
    type: Array as unknown as () => CivilStatus[],
    required: true,
    default: () => [],
  },
  citizenships: {
    type: Array as unknown as () => Citizenship[],
    required: true,
    default: () => [],
  },
  departments: {
    type: Array as unknown as () => Department[],
    required: true,
    default: () => [],
  },
  positions: {
    type: Array as unknown as () => Position[],
    required: true,
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

// Prefer nested user_detail if present; fallback to user for backward compatibility
const user = computed<UserBasic>(() => props.user as UserBasic)
const detail = computed<UserDetail>(() => (user.value?.user_detail ?? user.value?.userDetail ?? {}) as UserDetail)
const genders = computed<Gender[]>(() => props.genders as Gender[]);
const suffixes = computed<Suffix[]>(() => props.suffixes as Suffix[]);
const civil_statuses = computed<Gender[]>(() => props.civil_statuses as CivilStatus[]);
const citizenships = computed<Gender[]>(() => props.citizenships as Citizenship[]);
const departments = computed<Gender[]>(() => props.departments as Department[]);
const positions = computed<Gender[]>(() => props.positions as Position[]);

// Expose a form ref so parent components can access without document.getElementById
const userEditForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!userEditForm.value) return null
    return new FormData(userEditForm.value)
}

defineExpose({
    userEditForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: userEditForm.value })
    }
})
</script>

<template>
  <form ref="userEditForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div class="md:col-span-2 hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="user?.id"
        />
    </div>
    <div class="grid gap-2 md:col-span-1">
        <Label for="first_name">First Name</Label>
        <Input
            id="first_name"
            class="mt-1 block w-full"
            name="first_name"
            :default-value="detail?.first_name"
            autocomplete="first_name"
            placeholder="First Name"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="middle_name">Middle Name</Label>
        <Input
            id="middle_name"
            class="mt-1 block w-full"
            name="middle_name"
            :default-value="detail?.middle_name"
            autocomplete="middle_name"
            placeholder="Middle Name"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="last_name">Last Name</Label>
        <Input
            id="last_name"
            class="mt-1 block w-full"
            name="last_name"
            :default-value="detail?.last_name"
            autocomplete="last_name"
            placeholder="Last Name"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="suffix">Suffix</Label>
        <Input
            id="suffix"
            class="mt-1 block w-full"
            name="suffix"
            :default-value="detail?.suffix"
            autocomplete="suffix"
            placeholder="Suffix"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="gender">Gender</Label>
        <Select
            id="gender"
            class="mt-1 block w-full"
            name="gender_id"
            :default-value="detail?.gender_id ? String(detail?.gender_id) : undefined"
        >
          <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a gender" />
          </SelectTrigger>
          <SelectContent class="w-full">
              <SelectGroup>
                  <SelectLabel>Gender</SelectLabel>
                  <SelectItem
                          v-for="gender in genders"
                          :key="gender.id"
                          :value="String(gender.id)"
                  >
                  {{ gender.name }}
                  </SelectItem>
              </SelectGroup>
          </SelectContent>
        </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="civil_status">Civil Status</Label>
        <Select
            id="civil_status"
            class="mt-1 block w-full"
            name="civil_status_id"
            :default-value="detail?.civil_status_id ? String(detail?.civil_status_id) : undefined"
        >
          <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a civil_status" />
          </SelectTrigger>
          <SelectContent class="w-full">
              <SelectGroup>
                  <SelectLabel>Civil Status</SelectLabel>
                  <SelectItem
                          v-for="civil_status in civil_statuses"
                          :key="civil_status.id"
                          :value="String(civil_status.id)"
                  >
                  {{ civil_status.name }}
                  </SelectItem>
              </SelectGroup>
          </SelectContent>
        </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="citizenship">Citizenship</Label>
        <Select
            id="citizenship"
            class="mt-1 block w-full"
            name="citizenship_id"
            :default-value="detail?.citizenship_id ? String(detail?.citizenship_id) : undefined"
        >
          <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a citizenship" />
          </SelectTrigger>
          <SelectContent class="w-full">
              <SelectGroup>
                  <SelectLabel>Citizenship</SelectLabel>
                  <SelectItem
                          v-for="citizenship in citizenships"
                          :key="citizenship.id"
                          :value="String(citizenship.id)"
                  >
                  {{ citizenship.name }}
                  </SelectItem>
              </SelectGroup>
          </SelectContent>
        </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="department">Department</Label>
        <Select
            id="department"
            class="mt-1 block w-full"
            name="department_id"
            :default-value="detail?.department_id ? String(detail?.department_id) : undefined"
        >
          <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a department" />
          </SelectTrigger>
          <SelectContent class="w-full">
              <SelectGroup>
                  <SelectLabel>Department</SelectLabel>
                  <SelectItem
                          v-for="department in departments"
                          :key="department.id"
                          :value="String(department.id)"
                  >
                  {{ department.name }}
                  </SelectItem>
              </SelectGroup>
          </SelectContent>
        </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="position">Position</Label>
        <Select
            id="position"
            class="mt-1 block w-full"
            name="position_id"
            :default-value="detail?.position_id ? String(detail?.position_id) : undefined"
        >
          <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a position" />
          </SelectTrigger>
          <SelectContent class="w-full">
              <SelectGroup>
                  <SelectLabel>Position</SelectLabel>
                  <SelectItem
                          v-for="position in positions"
                          :key="position.id"
                          :value="String(position.id)"
                  >
                  {{ position.name }}
                  </SelectItem>
              </SelectGroup>
          </SelectContent>
        </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="birthdate">Birth Date</Label>
        <Input
            id="birthdate"
            type="date"
            class="mt-1 block w-full"
            name="birthdate"
            :default-value="detail?.birthdate"
            autocomplete="birthdate"
            placeholder="Birth Date"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="employee_no">Employee No</Label>
        <Input
            id="employee_no"
            class="mt-1 block w-full"
            name="employee_no"
            :default-value="detail?.employee_no"
            autocomplete="employee_no"
            placeholder="Employee No"
        />
    </div>
    <div class="grid gap-2 md:col-span-1">
        <Label for="username">Username</Label>
        <Input
            id="username"
            class="mt-1 block w-full"
            name="username"
            :default-value="user?.username"
            autocomplete="username"
            placeholder="Username"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="email">Email</Label>
        <Input
            id="email"
            class="mt-1 block w-full"
            name="email"
            :default-value="user?.email"
            autocomplete="email"
            placeholder="Email"
        />
    </div>
  </form>
</template>
