@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="container" style="max-width:600px;margin:2rem auto">
    <h2 style="margin-bottom:1.5rem">Settings</h2>

    <div class="auth-card" style="padding:1.5rem">
        <h3 style="margin-bottom:.5rem">End-to-End Encryption</h3>
        <p style="color:#888;font-size:.9rem;margin-bottom:1.25rem">
            Messages are encrypted end-to-end only when <strong>both</strong> participants have E2EE enabled.
            Enabling E2EE generates a key pair in your browser — your private key never leaves your device unencrypted.
        </p>

        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem">
            <label style="font-weight:500">E2EE Status:</label>
            <span style="
                padding:.25rem .75rem;
                border-radius:999px;
                font-size:.85rem;
                font-weight:600;
                background:{{ $user->e2ee_enabled ? '#dcfce7' : '#f3f4f6' }};
                color:{{ $user->e2ee_enabled ? '#166534' : '#6b7280' }};
            ">
                {{ $user->e2ee_enabled ? '🔒 Enabled' : '🔓 Disabled' }}
            </span>
        </div>

        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem">
            <label style="font-weight:500">Key:</label>
            <span id="key-status" style="color:{{ $user->public_key ? '#166534' : '#6b7280' }};font-size:.9rem">
                {{ $user->public_key ? '✅ Active' : '⚠️ Not configured' }}
            </span>
        </div>

        <div style="display:flex;gap:.75rem">
            <button
                id="btn-enable-e2ee"
                class="btn btn-primary"
                style="{{ $user->e2ee_enabled ? 'display:none' : '' }}"
                onclick="toggleE2EE(true)"
            >
                Enable E2EE
            </button>
            <button
                id="btn-disable-e2ee"
                class="btn btn-secondary"
                style="{{ $user->e2ee_enabled ? '' : 'display:none' }}"
                onclick="toggleE2EE(false)"
            >
                Disable E2EE
            </button>
        </div>

        <p id="e2ee-message" style="margin-top:1rem;font-size:.9rem;color:#6b7280"></p>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Update key badge once E2EE initialises (handles the case where key was just generated)
document.addEventListener('e2ee:ready', () => {
    const e2ee = window.__app?.e2ee;
    const el = document.getElementById('key-status');
    if (!el) return;
    if (e2ee?.ready && e2ee?.publicKeyBase64) {
        el.textContent = '✅ Active';
        el.style.color = '#166534';
    }
});

async function toggleE2EE(enable) {
    const message = document.getElementById('e2ee-message');
    const btnEnable = document.getElementById('btn-enable-e2ee');
    const btnDisable = document.getElementById('btn-disable-e2ee');

    message.textContent = enable ? 'Setting up E2EE...' : 'Disabling E2EE...';
    btnEnable.disabled = true;
    btnDisable.disabled = true;

    try {
        if (enable) {
            await window.__app.e2ee.setup();
        }

        const res = await fetch('{{ route('settings.e2ee') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: JSON.stringify({ enabled: enable }),
        });

        if (!res.ok) throw new Error('Server error: ' + res.status);

        window.location.reload();
    } catch (err) {
        console.error('[Settings] E2EE toggle failed', err);
        message.textContent = 'Something went wrong. Please try again.';
        btnEnable.disabled = false;
        btnDisable.disabled = false;
    }
}
</script>
@endpush
