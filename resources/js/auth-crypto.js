import { deriveKeyFromPassword } from './crypto.js';

/**
 * Intercept login/register form submission, derive the PBKDF2 key from
 * password + email, store in sessionStorage, then submit the form normally.
 */
async function interceptAuthForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email    = form.querySelector('input[name="email"]')?.value ?? '';
        const password = form.querySelector('input[name="password"]')?.value ?? '';

        try {

            const aesKey = await deriveKeyFromPassword(password, email);

            // Export raw key bytes and store as base64 in sessionStorage
            const rawKey = await crypto.subtle.exportKey('raw', aesKey);
            const base64 = btoa(String.fromCharCode(...new Uint8Array(rawKey)));
            sessionStorage.setItem('e2ee_derived_key', base64);

        } catch (err) {
            console.error('[AuthCrypto] Key derivation failed', err);
            // Do not block login on crypto failure
        }

        // Submit form programmatically (bypasses JS listeners, goes straight to server)
        form.submit();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    interceptAuthForm('login-form');
    interceptAuthForm('register-form');
});
