export interface Category { id: number; name: string; slug: string; color: string }

export interface ArticleSummary {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number;
    category: Category | null; published_at: string;
}

export interface ArticleAuthor { id: number; name: string; username: string | null; avatar: string | null }

export interface Article {
    id: number; title: string; slug: string; excerpt: string | null;
    body: string; headings: { id: string; text: string; level: number }[];
    format: string; featured_image_url: string | null;
    reading_time: number; views: number; word_count: number;
    category: Category | null;
    author: ArticleAuthor | null;
    tags: { id: number; name: string; slug: string }[];
    published_at: string; published_at_iso: string;
    meta_title: string; meta_description: string | null; og_image_url: string | null;
    canonical: string;
}

export interface ArticleComment {
    id: number; body: string; created_at_iso: string; is_mine: boolean; can_delete: boolean;
    user: { username: string | null; name: string; avatar: string | null };
}

export interface CommentPage { data: ArticleComment[]; current_page: number; last_page: number; total: number }

/** Up to two initials for an avatar placeholder. */
export function initials(name: string | null | undefined): string {
    return (name ?? '?').split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?';
}

export function avatarBg(name: string): string {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) || 0) % colors.length];
}
