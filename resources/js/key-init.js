import {
    generateKeyPair,
    exportPublicKey,
    encryptPrivateKey,
    decryptPrivateKey,
    saveKeyPairToIDB,
    loadPrivateKeyFromIDB,
    importPublicKey,
    deriveAESKey,
    encryptMessage,
    decryptMessage,
} from './crypto.js';

const PUBLIC_KEY_CACHE_MAX = 200;
const publicKeyCache = new Map();

async function importAesKeyFromSession() {
    const base64 = sessionStorage.getItem('e2ee_derived_key');
    if (!base64) return null;
    const raw = Uint8Array.from(atob(base64), c => c.charCodeAt(0));
    return crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
}

async function uploadPublicKey(publicKeyBase64, privateKey, aesKey) {
    const payload = { public_key: publicKeyBase64 };
    if (aesKey) {
        payload.encrypted_private_key = await encryptPrivateKey(privateKey, aesKey);
    }
    await fetch('/keys', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
        body: JSON.stringify(payload),
    });
}

async function initE2EE() {
    if (!window.__app?.e2eeEnabled) {
        window.__app.e2ee.ready = false;
        window.__app.e2ee.enabled = false;
        document.dispatchEvent(new CustomEvent('e2ee:ready'));
        return;
    }

    const userId = window.__app?.authUserId;
    const aesKey = await importAesKeyFromSession();

    try {
        // Step 1: try IndexedDB
        const idbResult = await loadPrivateKeyFromIDB(userId);
        if (idbResult) {

            // Re-upload public key if it was cleared on the server (e.g. after disable)
            const serverRes = await fetch('/keys/me', { headers: { Accept: 'application/json' } });
            const serverData = serverRes.ok ? await serverRes.json() : null;
            if (!serverData?.public_key) {
                await uploadPublicKey(idbResult.publicKeyBase64, idbResult.privateKey, aesKey);
            }

            window.__app.e2ee.ready = true;
            window.__app.e2ee.enabled = true;
            window.__app.e2ee.privateKey = idbResult.privateKey;
            window.__app.e2ee.publicKeyBase64 = idbResult.publicKeyBase64;
            document.dispatchEvent(new CustomEvent('e2ee:ready'));
            return;
        }

        // Step 2: try server restore (existing key on server, new device)
        if (aesKey) {
            const res = await fetch('/keys/me', { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                if (data.encrypted_private_key && data.public_key) {
                    const privateKey = await decryptPrivateKey(data.encrypted_private_key, aesKey);
                    await saveKeyPairToIDB(data.public_key, privateKey, userId);
                    window.__app.e2ee.ready = true;
                    window.__app.e2ee.enabled = true;
                    window.__app.e2ee.privateKey = privateKey;
                    window.__app.e2ee.publicKeyBase64 = data.public_key;
                    document.dispatchEvent(new CustomEvent('e2ee:ready'));
                    return;
                }
            }
        }

        // Step 3: generate new key pair
        const keyPair = await generateKeyPair();
        const publicKeyBase64 = await exportPublicKey(keyPair.publicKey);
        await saveKeyPairToIDB(publicKeyBase64, keyPair.privateKey, userId);
        await uploadPublicKey(publicKeyBase64, keyPair.privateKey, aesKey);

        window.__app.e2ee.ready = true;
        window.__app.e2ee.enabled = true;
        window.__app.e2ee.privateKey = keyPair.privateKey;
        window.__app.e2ee.publicKeyBase64 = publicKeyBase64;
        document.dispatchEvent(new CustomEvent('e2ee:ready'));

    } catch (err) {
        console.error('[E2EE] Initialization failed', err);
        window.__app.e2ee.ready = false;
        window.__app.e2ee.enabled = false;
        window.__app.e2ee.error = err;
        document.dispatchEvent(new CustomEvent('e2ee:ready'));
    }
}

/**
 * Called from settings page when user enables E2EE.
 * Re-runs init to generate/restore keys.
 */
async function setup() {
    window.__app.e2eeEnabled = true;
    await initE2EE();
}

/**
 * Fetch and cache the public key for a given user ID.
 */
async function getPublicKey(userId) {
    if (publicKeyCache.has(userId)) {
        return publicKeyCache.get(userId);
    }

    const res = await fetch(`/keys/${userId}`, {
        headers: { Accept: 'application/json' },
    });

    if (!res.ok) {
        throw new Error(`[E2EE] Failed to fetch public key for user ${userId}: ${res.status}`);
    }

    const data = await res.json();
    if (publicKeyCache.size >= PUBLIC_KEY_CACHE_MAX) {
        publicKeyCache.delete(publicKeyCache.keys().next().value);
    }
    publicKeyCache.set(userId, data.public_key);
    return data.public_key;
}

// Expose on window.__app — create the object once, mutate it in place from here on
window.__app = window.__app ?? {};
window.__app.e2ee = { ready: false, enabled: false, setup, getPublicKey };

// Expose crypto primitives for non-module scripts (e.g. Blade views)
window.__app.crypto = { importPublicKey, deriveAESKey, encryptMessage, decryptMessage };

// Auto-init on page load
document.addEventListener('DOMContentLoaded', initE2EE);
