/**
 * A link target safe to put in an href: relative paths, http(s), mailto and
 * fragments pass; javascript:, data: and anything else become '#'. Menu items
 * and banners are typed in by staff, so one bad paste must not run script for
 * every visitor.
 */
export function safeUrl(url: string | null | undefined): string {
    // Browsers drop tabs and newlines anywhere in a URL and trim control
    // characters at the ends, so "java\tscript:" still means javascript:.
    // Normalise the same way before looking at the scheme.
    const value = Array.from(url ?? '')
        .filter((ch) => ch.charCodeAt(0) > 0x1f && ch.charCodeAt(0) !== 0x7f)
        .join('')
        .trim();
    if (value === '') return '#';

    // Protocol-relative (//host) or a backslash variant a browser reads as one.
    if (/^[\\/]{2}/.test(value)) return 'https://' + value.replace(/^[\\/]+/, '');

    const scheme = value.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase();
    if (scheme === undefined) return value; // relative: "/news", "news/1", "#top", "?page=2"

    return ['http', 'https', 'mailto'].includes(scheme) ? value : '#';
}
