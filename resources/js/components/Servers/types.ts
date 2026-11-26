export interface ServerStatus {
    is_online: boolean; map: string | null; players_online: number;
    players_max: number; ping: number | null;
    is_password_protected: boolean; vac_secured: boolean;
}

export interface GameServer {
    id: number; ip: string; port: number; address: string; name: string;
    country_code: string | null; row_image: string | null;
    tags: string[]; is_favourited: boolean;
    status: ServerStatus | null;
}

export interface GameInfo {
    id: number; name: string; slug: string; icon: string;
    color: string; cover_url: string | null;
}

export interface InsightRange {
    history: { t: string; players: number | null }[];
    peak: number;
    average: number;
    uptime: number;
    samples: number;
}

export interface Insights {
    updated_at: string;
    ranges: Record<'24h' | '7d' | '30d', InsightRange>;
    maps: { map: string; share: number }[];
}

export interface Review {
    id: number; rating: number; body: string | null; created_at: string; is_mine: boolean;
    user: { username: string | null; name: string; avatar: string | null };
}

export type ServerFilter = 'all' | 'online' | 'offline' | 'empty' | 'hasplayers' | 'full';

/** Which servers a filter chip keeps. */
export function matchesFilter(server: GameServer, filter: ServerFilter): boolean {
    const s = server.status;
    switch (filter) {
        case 'online': return !!s?.is_online;
        case 'offline': return !s?.is_online;
        case 'empty': return !!s?.is_online && s.players_online === 0;
        case 'hasplayers': return !!s?.is_online && s.players_online > 0;
        case 'full': return !!s?.is_online && s.players_max > 0 && s.players_online >= s.players_max;
        default: return true;
    }
}

/** Green with room, amber filling up, red (nearly) full. */
export function playerBarColor(s: ServerStatus | null): string {
    if (!s || s.players_max === 0) return '#52525b';
    const pct = s.players_online / s.players_max;
    if (pct >= 0.9) return '#ef4444';
    if (pct >= 0.6) return '#f59e0b';
    return '#22c55e';
}

/** Tailwind classes rather than a raw hex, so ping keeps its contrast in both themes. */
export function pingClass(s: ServerStatus | null, dark: boolean): string {
    if (s?.ping == null) return 'text-zinc-500';
    if (s.ping < 50) return dark ? 'text-emerald-400' : 'text-emerald-800';
    if (s.ping < 100) return dark ? 'text-amber-400' : 'text-amber-700';
    return dark ? 'text-red-400' : 'text-red-600';
}

/** A URL for a CSS url(), quoted so a stray quote or bracket can't break out. */
export function cssUrl(url: string): string {
    return `url(${JSON.stringify(url)})`;
}
