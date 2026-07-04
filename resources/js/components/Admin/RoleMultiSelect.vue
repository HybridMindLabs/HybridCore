<script setup lang="ts">
import { ROLE_ICONS } from '@/constants/icons';

interface RoleOption { id: number; name: string; slug: string; color: string; icon: string }

const props = defineProps<{
    roles: RoleOption[];
    modelValue: string[];
    primary: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [string[]];
    'update:primary': [string | null];
}>();

function isChecked(slug: string): boolean {
    return props.modelValue.includes(slug);
}

function toggle(slug: string) {
    if (isChecked(slug)) {
        const next = props.modelValue.filter((s) => s !== slug);
        emit('update:modelValue', next);
        if (props.primary === slug) {
            emit('update:primary', next[0] ?? null);
        }
    } else {
        emit('update:modelValue', [...props.modelValue, slug]);
        if (! props.primary) {
            emit('update:primary', slug);
        }
    }
}

function setPrimary(slug: string) {
    emit('update:primary', slug);
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <label
            v-for="role in roles"
            :key="role.id"
            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-zinc-900/60 cursor-pointer select-none"
        >
            <input
                type="checkbox"
                class="w-4 h-4 rounded border border-zinc-800/70 bg-zinc-900/60 accent-blue-500"
                :checked="isChecked(role.slug)"
                @change="toggle(role.slug)"
            />
            <span
                class="w-6 h-6 rounded-md flex items-center justify-center shrink-0"
                :style="{ backgroundColor: role.color + '1a', color: role.color }"
            >
                <component :is="ROLE_ICONS[role.icon]" :size="13" :stroke-width="1.75" />
            </span>
            <span class="text-zinc-100 text-sm flex-1">{{ role.name }}</span>
            <button
                v-if="isChecked(role.slug)"
                type="button"
                class="flex items-center gap-1.5 text-xs px-2 py-1 rounded-md transition-colors"
                :class="primary === role.slug
                    ? 'bg-blue-500/15 text-blue-400'
                    : 'text-zinc-600 hover:text-zinc-400'"
                @click.stop.prevent="setPrimary(role.slug)"
            >
                {{ primary === role.slug ? '★ Primary' : 'Set as primary' }}
            </button>
        </label>
    </div>
</template>
