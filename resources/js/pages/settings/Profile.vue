<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
const suffixes = page.props.suffixes;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Profile information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="first_name">First Name</Label>
                        <Input
                            id="first_name"
                            class="mt-1 block w-full"
                            name="first_name"
                            :default-value="user.first_name"
                            autocomplete="first_name"
                            placeholder="First Name"
                        />
                        <InputError class="mt-2" :message="errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="middle_name">Middle Name</Label>
                        <Input
                            id="middle_name"
                            class="mt-1 block w-full"
                            name="middle_name"
                            :default-value="user.middle_name"
                            autocomplete="middle_name"
                            placeholder="Middle Name"
                        />
                        <InputError class="mt-2" :message="errors.middle_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Last Name</Label>
                        <Input
                            id="last_name"
                            class="mt-1 block w-full"
                            name="last_name"
                            :default-value="user.last_name"
                            autocomplete="last_name"
                            placeholder="Last Name"
                        />
                        <InputError class="mt-2" :message="errors.last_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="suffix">Suffix</Label>
                        <Select
                            id="suffix"
                            class="mt-1 block w-full"
                        >
                            <SelectTrigger class="w-[580px]">
                                <SelectValue placeholder="Select a suffix" />
                            </SelectTrigger>
                            <SelectContent class="w-[580px]">
                                <SelectGroup>
                                    <SelectLabel>Suffix</SelectLabel>
                                    <!--  Dynamically loop suffix items -->
                                    <SelectItem
                                            v-for="suffix in suffixes"
                                            :key="suffix.id"
                                            :value="suffix.id"
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
                            :default-value="user.birthdate"
                            autocomplete="birthdate"
                            placeholder="Birth Date"
                        />
                        <InputError class="mt-2" :message="errors.birthdate" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="employee_no">Employee No</Label>
                        <Input
                            id="employee_no"
                            class="mt-1 block w-full"
                            name="employee_no"
                            :default-value="user.employee_no"
                            autocomplete="employee_no"
                            placeholder="Employee No"
                        />
                        <InputError class="mt-2" :message="errors.employee_no" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="username">Username</Label>
                        <Input
                            id="username"
                            class="mt-1 block w-full"
                            name="username"
                            :default-value="user.username"
                            required
                            autocomplete="username"
                            placeholder="Username"
                            readonly
                        />
                        <InputError class="mt-2" :message="errors.username" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                            readonly
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
