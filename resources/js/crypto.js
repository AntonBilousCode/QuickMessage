/**
 * E2EE crypto module — Web Crypto API (ECDH P-256 + AES-GCM + PBKDF2)
 * Pure functions, no side effects. Import and use as needed.
 */

const IDB_NAME = 'quickmessage-e2ee';
const IDB_STORE = 'e2ee-keys';
const IDB_KEY = 'keypair';

// ─── ECDH Key Pair ───────────────────────────────────────────────────────────

export async function generateKeyPair() {
    const keyPair = await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey'],
    );
    return keyPair;
}

export async function exportPublicKey(publicKey) {
    const raw = await crypto.subtle.exportKey('spki', publicKey);
    return btoa(String.fromCharCode(...new Uint8Array(raw)));
}

export async function importPublicKey(base64) {
    const raw = Uint8Array.from(atob(base64), c => c.charCodeAt(0));
    return crypto.subtle.importKey(
        'spki',
        raw,
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        [],
    );
}

export async function importPrivateKey(base64) {
    const raw = Uint8Array.from(atob(base64), c => c.charCodeAt(0));
    return crypto.subtle.importKey(
        'pkcs8',
        raw,
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        ['deriveKey'],
    );
}

// ─── Message Encryption ──────────────────────────────────────────────────────

export async function deriveAESKey(privateKey, publicKey) {
    return crypto.subtle.deriveKey(
        { name: 'ECDH', public: publicKey },
        privateKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

export async function encryptMessage(aesKey, plaintext) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const encoded = new TextEncoder().encode(plaintext);
    const encrypted = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, encoded);
    return {
        ciphertext: btoa(String.fromCharCode(...new Uint8Array(encrypted))),
        iv: btoa(String.fromCharCode(...iv)),
    };
}

export async function decryptMessage(aesKey, ciphertext, iv) {
    const encryptedBytes = Uint8Array.from(atob(ciphertext), c => c.charCodeAt(0));
    const ivBytes = Uint8Array.from(atob(iv), c => c.charCodeAt(0));
    const decrypted = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: ivBytes }, aesKey, encryptedBytes);
    return new TextDecoder().decode(decrypted);
}

// ─── Private Key Protection (password-based) ─────────────────────────────────

export async function deriveKeyFromPassword(password, email) {
    const passwordKey = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(password),
        'PBKDF2',
        false,
        ['deriveKey'],
    );
    const salt = new TextEncoder().encode(email);
    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations: 100000, hash: 'SHA-256' },
        passwordKey,
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt'],
    );
}

export async function encryptPrivateKey(privateKey, aesKey) {
    const exported = await crypto.subtle.exportKey('pkcs8', privateKey);
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const encrypted = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, exported);
    // Store iv + ciphertext together as base64(iv_12bytes + ciphertext)
    const combined = new Uint8Array(12 + encrypted.byteLength);
    combined.set(iv, 0);
    combined.set(new Uint8Array(encrypted), 12);
    return btoa(String.fromCharCode(...combined));
}

export async function decryptPrivateKey(encryptedBlob, aesKey) {
    const combined = Uint8Array.from(atob(encryptedBlob), c => c.charCodeAt(0));
    const iv = combined.slice(0, 12);
    const ciphertext = combined.slice(12);
    const decrypted = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, aesKey, ciphertext);
    return crypto.subtle.importKey(
        'pkcs8',
        decrypted,
        { name: 'ECDH', namedCurve: 'P-256' },
        false,
        ['deriveKey'],
    );
}

// ─── IndexedDB ───────────────────────────────────────────────────────────────

function openIDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(IDB_NAME, 1);
        req.onupgradeneeded = () => {
            req.result.createObjectStore(IDB_STORE);
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export async function saveKeyPairToIDB(publicKeyBase64, privateKey, userId) {
    const idbKey = userId ? `${IDB_KEY}-${userId}` : IDB_KEY;
    const db = await openIDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(IDB_STORE, 'readwrite');
        tx.objectStore(IDB_STORE).put({ publicKeyBase64, privateKey }, idbKey);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

export async function loadPrivateKeyFromIDB(userId) {
    const idbKey = userId ? `${IDB_KEY}-${userId}` : IDB_KEY;
    const db = await openIDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(IDB_STORE, 'readonly');
        const req = tx.objectStore(IDB_STORE).get(idbKey);
        req.onsuccess = () => {
            if (req.result) {
                resolve(req.result);
            } else {
                resolve(null);
            }
        };
        req.onerror = () => reject(req.error);
    });
}
