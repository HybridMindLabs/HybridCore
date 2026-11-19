export interface GameFilter { id: number; slug: string; name: string }
export interface GameStat { slug: string; name: string; players: number; max_players: number }

export interface HomeServer {
    id: number; name: string; game_slug: string; game_name: string; game_id: number;
    address: string;
    tags: string[]; is_online: boolean; map: string; map_image: string | null; row_image: string | null;
    players: number; max_players: number; ping: number;
    is_favourited: boolean; show_route: string; connect_route: string;
}

export interface NewsCard {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number;
    published_at: string; category: { id: number; name: string; slug: string; color: string } | null;
    author: { id: number; name: string } | null;
}

export interface OnlineUser { id: number; name: string; username: string | null; avatar: string | null }
export interface Presence { users: OnlineUser[]; guests: number; bots: number }
export interface RecentMember { id: number; username: string | null; name: string; avatar: string | null; joined_at: string }

export interface NetworkStats { games: number; servers: number; members: number; online_servers: number }

const gameColors: Record<string, string> = {
    'cs2': '#f97316', 'csgo': '#f97316', 'css': '#eab308',
    'garrysmod': '#a78bfa', 'tf2': '#ef4444', 'minecraft': '#22c55e',
    'valheim': '#06b6d4', 'rust': '#b45309', 'dod': '#3b82f6',
};

export function gameColor(slug: string): string {
    return gameColors[slug] ?? '#3b82f6';
}

export function pingColor(ping: number, dark: boolean): string {
    if (ping < 50) return dark ? 'text-emerald-400' : 'text-emerald-800';
    if (ping < 100) return dark ? 'text-amber-400' : 'text-amber-700';
    return 'text-red-400';
}

/** Guards against an empty display name, which would throw on [0]. */
export function initial(name: string): string {
    return (name?.trim()?.[0] ?? '?').toUpperCase();
}
