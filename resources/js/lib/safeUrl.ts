/**
 * A link target safe to put in an href: relative paths, http(s), mailto and
 * fragments pass; javascript:, data: and anything else become '#'. Menu items
 * and banners are typed in by staff, so one bad paste must not run script for
 * every visitor.
 */
export function safeUrl(url: string | null | undefined): string {
    const value = (url ?? '').trim();
    if (value === '') return '#';
    if (/^(\/(?!\/)|#|\?|\.{0,2}\/)/.test(value)) return value;
    if (/^(https?:|mailto:)/i.test(value)) return value;
    // Protocol-relative (//host) or no scheme at all, e.g. "news/1".
    if (value.startsWith('//')) return 'https:' + value;
    if (!/^[a-z][a-z0-9+.-]*:/i.test(value)) return value;

    return '#';
}
