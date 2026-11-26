<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { ADDRESS_HINT, inputClass, parseAddress, serverPayload, type AdminGame } from './serverForm';

const props = defineProps<{ games: AdminGame[] }>();
const emit = defineEmits<{ close: [] }>();

const form = useForm({
    game_id: '',
    address: '',
    query_port: '',
    display_address: '',
    is_visible: true,
    tags: '',
});
const addressError = ref('');
// The server validates the transformed payload, so ip/port errors come back under those names.
const sentErrors = computed(() => form.errors as Record<string, string | undefined>);

// When a game is picked, offer its default ports so the operator doesn't have
// to know that Rust queries on +2 or ARK on 27015. Only fills empty fields, so
// anything typed by hand is left alone.
watch(() => form.game_id, (id) => {
    const game = props.games.find((g) => String(g.id) === String(id));
    if (!game) return;

    if (!form.address && game.default_port) form.address = `:${game.default_port}`;
    if (!form.query_port && game.default_query_port) form.query_port = String(game.default_query_port);
});

function submit() {
    const at = parseAddress(form.address);
    addressError.value = at ? '' : ADDRESS_HINT;
    if (!at) return;

    form.transform((d) => serverPayload(d, at)).post(route('admin.servers.store'), {
        onSuccess: () => { form.reset(); emit('close'); },
    });
}
</script>

<template>
    <div class="hc-hero-in mb-5 bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-800/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                <Plus :size="14" :stroke-width="2" class="text-blue-400" /> Add New Server
            </h3>
            <button type="button" class="text-zinc-600 hover:text-zinc-300 transition-colors" aria-label="Close" @click="emit('close')">
                <X :size="14" :stroke-width="1.75" />
            </button>
        </div>
        <form class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1.5">
                <label for="add-server-game" class="text-zinc-500 text-xs font-medium">Game <span class="text-red-400">*</span></label>
                <select id="add-server-game" v-model="form.game_id" :class="inputClass">
                    <option value="">Select game…</option>
                    <option v-for="g in games" :key="g.id" :value="g.id">{{ g.name }}</option>
                </select>
                <p v-if="form.errors.game_id" class="text-red-400 text-xs">{{ form.errors.game_id }}</p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="add-server-address" class="text-zinc-500 text-xs font-medium">
                    IP:PORT <span class="text-red-400">*</span>
                    <span class="text-zinc-700 font-normal ml-1">e.g. 192.168.1.1:27015</span>
                </label>
                <input id="add-server-address" v-model="form.address" type="text" placeholder="192.168.1.1:27015"
                    :class="[inputClass, 'font-mono', addressError ? 'border-red-500' : '']" />
                <p v-if="addressError" class="text-red-400 text-xs">{{ addressError }}</p>
                <p v-else-if="sentErrors.ip" class="text-red-400 text-xs">{{ sentErrors.ip }}</p>
                <p v-else-if="sentErrors.port" class="text-red-400 text-xs">{{ sentErrors.port }}</p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="add-server-query" class="text-zinc-500 text-xs font-medium">
                    Query port
                    <span class="text-zinc-700 font-normal ml-1">only if different from the game port — Rust +2, ARK 27015</span>
                </label>
                <input id="add-server-query" v-model="form.query_port" type="number" min="1" max="65535" placeholder="same as game port" :class="[inputClass, 'font-mono']" />
                <p v-if="form.errors.query_port" class="text-red-400 text-xs">{{ form.errors.query_port }}</p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="add-server-tags" class="text-zinc-500 text-xs font-medium">Tags <span class="text-zinc-700 font-normal">comma separated</span></label>
                <input id="add-server-tags" v-model="form.tags" type="text" placeholder="competitive, ranked" :class="inputClass" />
            </div>
            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <label for="add-server-display" class="text-zinc-500 text-xs font-medium">
                    Public connection address (optional)
                    <span class="text-zinc-700 font-normal ml-1">e.g. play.myserver.com — useful if the server is behind BungeeCord/Velocity or NAT</span>
                </label>
                <input id="add-server-display" v-model="form.display_address" type="text" placeholder="play.myserver.com" :class="inputClass" />
                <p v-if="form.errors.display_address" class="text-red-400 text-xs">{{ form.errors.display_address }}</p>
            </div>
            <div class="flex flex-col gap-1.5 justify-end">
                <label class="flex items-center gap-2 cursor-pointer py-2">
                    <input v-model="form.is_visible" type="checkbox" class="w-4 h-4 rounded accent-blue-500" />
                    <span class="text-zinc-300 text-xs font-medium">Show in public server list</span>
                </label>
                <p v-if="form.errors.is_visible" class="text-red-400 text-xs">{{ form.errors.is_visible }}</p>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" :disabled="form.processing"
                    class="flex-1 bg-blue-500 text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-blue-400 transition-colors disabled:opacity-50">
                    {{ form.processing ? 'Adding…' : 'Add & Query' }}
                </button>
            </div>
        </form>
    </div>
</template>
