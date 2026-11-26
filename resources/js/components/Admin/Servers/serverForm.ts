export interface AdminGame { id: number; name: string; color?: string; icon?: string; default_port?: number | null; default_query_port?: number | null }

export interface CommandRow {
    id: number; command: string; source: string;
    status: 'pending' | 'delivered' | 'acked' | 'expired' | 'cancelled';
    attempts: number;
    created_at: string | null; delivered_at: string | null; acked_at: string | null; expires_at: string | null;
}

export interface AdminServer {
    id: number; ip: string; port: number; query_port: number | null; address: string; display_address: string | null; name: string | null;
    country_code: string | null; tags: string[]; is_active: boolean; is_visible: boolean;
    last_queried_at: string | null;
    bridge: { enabled: boolean; last_seen: string | null; online: boolean; pending_count: number };
    game: { id: number; name: string; slug: string; color: string; icon: string };
    status: { is_online: boolean; failure_reason: string | null; players_online: number; players_max: number; map: string | null } | null;
    commands: CommandRow[];
}

export interface ServerFormData {
    game_id: string;
    address: string;
    query_port: string;
    display_address: string;
    is_visible: boolean;
    tags: string;
}

export const ADDRESS_HINT = 'Use format IP:PORT — e.g. 192.168.1.1:27015';

/**
 * "host:port" split into its parts, or null when it is not that shape. The
 * port must be a whole number in range; the host is checked by the server.
 */
export function parseAddress(address: string): { ip: string; port: number } | null {
    const match = address.trim().match(/^([^\s:]+):(\d{1,5})$/);
    if (!match) return null;

    const port = Number(match[2]);
    return port >= 1 && port <= 65535 ? { ip: match[1], port } : null;
}

/** The fields both the add and the edit form send, from what was typed. */
export function serverPayload(data: ServerFormData, at: { ip: string; port: number }) {
    return {
        game_id: data.game_id,
        ip: at.ip,
        port: at.port,
        query_port: data.query_port ? Number(data.query_port) : null,
        display_address: data.display_address ? data.display_address.trim() : null,
        is_visible: Boolean(data.is_visible),
        tags: data.tags ? data.tags.split(',').map((t) => t.trim()).filter(Boolean) : [],
    };
}

export const inputClass = 'bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 w-full';
