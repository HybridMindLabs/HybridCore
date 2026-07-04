<script setup lang="ts">
import { ref, computed } from 'vue';
import { Search, ChevronDown, ChevronUp } from '@lucide/vue';

interface PermissionItem { slug: string; name: string }
interface PermissionGroup { group: string; permissions: PermissionItem[] }

const props = defineProps<{ groups: PermissionGroup[]; modelValue: string[] }>();
const emit = defineEmits<{ 'update:modelValue': [string[]] }>();

const search = ref('');
const collapsed = ref<Record<string, boolean>>({});

const filteredGroups = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.groups;
    return props.groups
        .map((g) => ({ ...g, permissions: g.permissions.filter((p) => p.name.toLowerCase().includes(q) || p.slug.toLowerCase().includes(q)) }))
        .filter((g) => g.permissions.length > 0);
});

const totalSelected = computed(() => props.modelValue.length);
const totalCount = computed(() => props.groups.reduce((n, g) => n + g.permissions.length, 0));

function isChecked(slug: string) { return props.modelValue.includes(slug); }

function toggle(slug: string) {
    emit('update:modelValue', isChecked(slug)
        ? props.modelValue.filter((s) => s !== slug)
        : [...props.modelValue, slug]);
}

function groupSelected(group: PermissionGroup) {
    return group.permissions.filter((p) => isChecked(p.slug)).length;
}

function isGroupFull(group: PermissionGroup) {
    return group.permissions.every((p) => isChecked(p.slug));
}

function toggleGroup(group: PermissionGroup) {
    const slugs = group.permissions.map((p) => p.slug);
    emit('update:modelValue', isGroupFull(group)
        ? props.modelValue.filter((s) => !slugs.includes(s))
        : [...new Set([...props.modelValue, ...slugs])]);
}

function selectAll() {
    emit('update:modelValue', props.groups.flatMap((g) => g.permissions.map((p) => p.slug)));
}

function clearAll() { emit('update:modelValue', []); }

function toggleCollapse(group: string) {
    collapsed.value[group] = !collapsed.value[group];
}
</script>

<template>
    <div v-if="groups.length === 0" class="text-zinc-600 text-sm py-6 text-center">
        No permissions registered.
    </div>

    <div v-else class="flex flex-col gap-3">

        <!-- Toolbar -->
        <div class="flex items-center gap-3">
            <div class="relative flex-1">
                <Search :size="12" :stroke-width="1.75" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-600" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search permissions…"
                    class="w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg pl-8 pr-3 py-1.5 text-xs
                           placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                />
            </div>
            <span class="text-zinc-600 text-xs shrink-0">
                <span :class="totalSelected > 0 ? 'text-blue-400' : ''">{{ totalSelected }}</span> / {{ totalCount }}
            </span>
            <button type="button" class="text-blue-400 text-xs hover:text-[#67e8f9] transition-colors shrink-0" @click="selectAll">All</button>
            <button type="button" class="text-zinc-600 text-xs hover:text-zinc-400 transition-colors shrink-0" @click="clearAll">None</button>
        </div>

        <!-- No search results -->
        <div v-if="filteredGroups.length === 0" class="text-zinc-600 text-xs text-center py-4">
            No permissions match "{{ search }}".
        </div>

        <!-- Groups -->
        <div v-for="group in filteredGroups" :key="group.group" class="border border-zinc-800/70 rounded-lg overflow-hidden">

            <!-- Group header -->
            <div class="flex items-center bg-[#0d1420] px-3 py-2 border-b border-zinc-800/70 gap-2">
                <button
                    type="button"
                    class="flex items-center gap-2 flex-1 min-w-0 text-left"
                    @click="toggleCollapse(group.group)"
                >
                    <component
                        :is="collapsed[group.group] ? ChevronDown : ChevronUp"
                        :size="12"
                        :stroke-width="2"
                        class="text-zinc-600 shrink-0"
                    />
                    <span class="text-zinc-100 text-xs font-semibold uppercase tracking-wide truncate">{{ group.group }}</span>
                    <span
                        class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full font-medium shrink-0"
                        :class="groupSelected(group) === group.permissions.length
                            ? 'bg-blue-500/15 text-blue-400'
                            : groupSelected(group) > 0
                                ? 'bg-[#f59e0b]/15 text-[#f59e0b]'
                                : 'bg-zinc-800 text-zinc-600'"
                    >
                        {{ groupSelected(group) }}/{{ group.permissions.length }}
                    </span>
                </button>
                <button
                    type="button"
                    class="text-blue-400 text-[11px] hover:text-[#67e8f9] transition-colors shrink-0"
                    @click="toggleGroup(group)"
                >
                    {{ isGroupFull(group) ? 'Deselect all' : 'Select all' }}
                </button>
            </div>

            <!-- Permissions grid -->
            <div v-show="!collapsed[group.group]" class="grid grid-cols-1 sm:grid-cols-2 gap-0.5 p-2">
                <label
                    v-for="perm in group.permissions"
                    :key="perm.slug"
                    class="flex items-start gap-2 px-2 py-1.5 rounded-md hover:bg-zinc-900/60 cursor-pointer select-none"
                >
                    <input
                        type="checkbox"
                        class="w-3.5 h-3.5 mt-0.5 rounded border border-zinc-800/70 bg-zinc-900/60 accent-blue-500 shrink-0"
                        :checked="isChecked(perm.slug)"
                        @change="toggle(perm.slug)"
                    />
                    <div class="min-w-0">
                        <p class="text-zinc-400 text-xs leading-snug">{{ perm.name }}</p>
                        <p class="text-zinc-600 text-[10px] font-mono leading-snug truncate">{{ perm.slug }}</p>
                    </div>
                </label>
            </div>
        </div>
    </div>
</template>
