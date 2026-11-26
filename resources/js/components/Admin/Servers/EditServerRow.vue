<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ADDRESS_HINT, inputClass, parseAddress, serverPayload, type AdminGame, type AdminServer } from './serverForm';

/** The inline edit form that replaces a server's row in the table. */
const props = defineProps<{ server: AdminServer; games: AdminGame[] }>();
const emit = defineEmits<{ done: [] }>();

const form = useForm({
    game_id: String(props.server.game.id),
    address: `${props.server.ip}:${props.server.port}`,
    query_port: props.server.query_port ? String(props.server.query_port) : '',
    display_address: props.server.display_address ?? '',
    name: props.server.name ?? '',
    is_active: props.server.is_active,
    is_visible: props.server.is_visible ?? true,
    tags: props.server.tags.join(', '),
});
const addressError = ref('');

function submit() {
    const at = parseAddress(form.address);
    addressError.value = at ? '' : ADDRESS_HINT;
    if (!at) return;

    form.transform((d) => ({ ...serverPayload(d, at), name: d.name || null, is_active: d.is_active }))
        .put(route('admin.servers.update', props.server.id), {
            onSuccess: () => emit('done'),
        });
}

const small = inputClass + ' !py-1.5 !text-xs';
const fieldId = (name: string) => `edit-server-${props.server.id}-${name}`;
</script>

<template>
    <tr class="border-b border-zinc-800/70 bg-zinc-900/40">
        <td colspan="7" class="px-4 py-4">
            <form class="flex flex-col gap-3" @submit.prevent="submit">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="flex flex-col gap-1">
                        <label :for="fieldId('game')" class="text-zinc-600 text-[11px]">Game</label>
                        <select :id="fieldId('game')" v-model="form.game_id" :class="small">
                            <option v-for="g in games" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label :for="fieldId('address')" class="text-zinc-600 text-[11px]">IP:PORT</label>
                        <input :id="fieldId('address')" v-model="form.address" type="text" placeholder="1.2.3.4:27015"
                            :class="[small, 'font-mono', addressError ? 'border-red-500' : '']" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label :for="fieldId('query')" class="text-zinc-600 text-[11px]">Query port</label>
                        <input :id="fieldId('query')" v-model="form.query_port" type="number" min="1" max="65535" placeholder="same as game" :class="[small, 'font-mono']" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label :for="fieldId('name')" class="text-zinc-600 text-[11px]">Name override</label>
                        <input :id="fieldId('name')" v-model="form.name" type="text" placeholder="Optional display name" :class="small" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label :for="fieldId('tags')" class="text-zinc-600 text-[11px]">Tags</label>
                        <input :id="fieldId('tags')" v-model="form.tags" type="text" placeholder="tag1, tag2" :class="small" />
                    </div>
                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label :for="fieldId('display')" class="text-zinc-600 text-[11px]">
                            Public connection address (optional)
                            <span class="text-zinc-700 font-normal ml-1">e.g. play.myserver.com</span>
                        </label>
                        <input :id="fieldId('display')" v-model="form.display_address" type="text" placeholder="play.myserver.com" :class="small" />
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer self-end pb-1.5">
                        <input v-model="form.is_visible" type="checkbox" class="w-4 h-4 rounded accent-blue-500" />
                        <span class="text-zinc-400 text-xs">Show in public server list</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer self-end pb-1.5">
                        <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded accent-blue-500" />
                        <span class="text-zinc-400 text-xs">Active (queried and listed)</span>
                    </label>
                </div>
                <p v-if="addressError" class="text-red-400 text-xs">{{ addressError }}</p>
                <p v-for="(message, key) in form.errors" :key="key" class="text-red-400 text-xs">{{ message }}</p>
                <div class="flex items-center gap-2 justify-end pt-1 border-t border-zinc-800/50">
                    <button type="button" class="px-3 py-1.5 text-xs text-zinc-500 hover:text-zinc-100 transition-colors" @click="emit('done')">
                        Cancel
                    </button>
                    <button type="submit" :disabled="form.processing"
                        class="px-4 py-1.5 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </td>
    </tr>
</template>
