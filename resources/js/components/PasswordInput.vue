<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, type HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const isPasswordVisible = ref(false);
</script>

<template>
    <div :class="cn('relative', props.class)">
        <Input
            v-bind="$attrs"
            :type="isPasswordVisible ? 'text' : 'password'"
            class="pr-10"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center rounded-md px-3 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :aria-label="isPasswordVisible ? 'Hide password' : 'Show password'"
            :aria-pressed="isPasswordVisible"
            @click="isPasswordVisible = !isPasswordVisible"
        >
            <component
                :is="isPasswordVisible ? EyeOff : Eye"
                class="size-4"
                aria-hidden="true"
            />
        </button>
    </div>
</template>
