/**
 * Headers for fetch() calls that change data.
 *
 * Laravel refreshes the XSRF-TOKEN cookie on every response, while the
 * <meta name="csrf-token"> tag is only written on a full page load. After an
 * in-app login (the session token is regenerated) the meta value is stale and
 * would earn a 419, so the cookie wins and the meta tag is only a fallback.
 */
export function csrfHeaders(): Record<string, string> {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    if (cookie) return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) };

    const meta = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
    return { 'X-CSRF-TOKEN': meta };
}
