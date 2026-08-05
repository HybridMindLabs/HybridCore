/**
 * Bridges the browser's WebAuthn API (which speaks ArrayBuffer) and the
 * server (which speaks base64url JSON, per lbuchs/webauthn's encoding mode)
 * for both ceremonies: registering a new passkey and verifying one at login.
 */

function base64urlToBuffer(value: string): ArrayBuffer {
    const padded = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(value.length + ((4 - (value.length % 4)) % 4), '=');
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
}

function bufferToBase64url(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function webauthnSupported(): boolean {
    return typeof window !== 'undefined' && !!window.PublicKeyCredential;
}

/** Walks the server's getCreateArgs() JSON back into real ArrayBuffers, calls navigator.credentials.create(), and returns the base64url pieces the server needs back. */
export async function createPasskey(options: any): Promise<{ clientDataJSON: string; attestationObject: string }> {
    const publicKey = options.publicKey;
    publicKey.challenge = base64urlToBuffer(publicKey.challenge);
    publicKey.user.id = base64urlToBuffer(publicKey.user.id);
    if (Array.isArray(publicKey.excludeCredentials)) {
        publicKey.excludeCredentials = publicKey.excludeCredentials.map((c: any) => ({ ...c, id: base64urlToBuffer(c.id) }));
    }

    const credential = (await navigator.credentials.create({ publicKey })) as PublicKeyCredential | null;
    if (!credential) throw new Error('No credential returned');

    const response = credential.response as AuthenticatorAttestationResponse;

    return {
        clientDataJSON: bufferToBase64url(response.clientDataJSON),
        attestationObject: bufferToBase64url(response.attestationObject),
    };
}

/** Same idea for the login ceremony: navigator.credentials.get() against the server's getGetArgs() challenge. */
export async function getPasskeyAssertion(
    options: any,
): Promise<{ id: string; clientDataJSON: string; authenticatorData: string; signature: string }> {
    const publicKey = options.publicKey;
    publicKey.challenge = base64urlToBuffer(publicKey.challenge);
    if (Array.isArray(publicKey.allowCredentials)) {
        publicKey.allowCredentials = publicKey.allowCredentials.map((c: any) => ({ ...c, id: base64urlToBuffer(c.id) }));
    }

    const credential = (await navigator.credentials.get({ publicKey })) as PublicKeyCredential | null;
    if (!credential) throw new Error('No credential returned');

    const response = credential.response as AuthenticatorAssertionResponse;

    return {
        id: bufferToBase64url(credential.rawId),
        clientDataJSON: bufferToBase64url(response.clientDataJSON),
        authenticatorData: bufferToBase64url(response.authenticatorData),
        signature: bufferToBase64url(response.signature),
    };
}
