/**
 * Desktop (browser) notifications for live in-app events. Permission is
 * requested lazily on first use, never on page load — browsers ignore or
 * penalize unsolicited permission prompts.
 */

interface NotifData {
    type?: string;
    message?: string;
    sender_username?: string;
    preview?: string;
    [key: string]: unknown;
}

export function useDesktopNotifications() {
    function isSupported(): boolean {
        return typeof window !== 'undefined' && 'Notification' in window;
    }

    async function ensurePermission(): Promise<boolean> {
        if (!isSupported()) return false;
        if (Notification.permission === 'granted') return true;
        if (Notification.permission === 'denied') return false;

        const result = await Notification.requestPermission();
        return result === 'granted';
    }

    /** Only worth showing a desktop popup when the user isn't already looking at the tab. */
    function shouldNotify(): boolean {
        return typeof document !== 'undefined' && (document.hidden || !document.hasFocus());
    }

    function notifyFromData(data: NotifData) {
        if (!isSupported() || Notification.permission !== 'granted' || !shouldNotify()) return;

        const title = data.type === 'new_message'
            ? `New message from ${data.sender_username ?? 'someone'}`
            : 'New notification';
        const body = data.preview ?? data.message ?? '';

        const n = new Notification(title, { body, tag: 'hybridcore-notification' });
        n.onclick = () => {
            window.focus();
            n.close();
        };
    }

    return { isSupported, ensurePermission, notifyFromData };
}
