@extends('layouts.app')

@section('title', 'Chat with ' . $recipient->name)

@section('content')
<div class="chat-page">
    <div class="container">
        {{-- Single Alpine component wrapping both messages and input --}}
        <div class="chat-card" x-data="chatApp({{ Js::from($messages->map(fn ($m) => [
            'message' => [
                'id'           => $m->id,
                'body'         => $m->body,
                'created_at'   => $m->created_at->toIso8601String(),
                'is_encrypted' => $m->is_encrypted,
            ],
            'sender' => [
                'id'   => $m->sender_id,
                'name' => $m->sender?->name ?? '',
            ],
        ])) }}, '{{ $encryptionMode }}')">

            {{-- Chat header --}}
            <div class="chat-header">
                <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">← Back</a>
                <div class="user-avatar" style="width:36px;height:36px;font-size:.8rem">
                    {{ strtoupper(substr($recipient->name, 0, 1)) }}
                </div>
                <span class="chat-header-name">{{ $recipient->name }}</span>
                <span class="ws-status" id="recipient-status" title="Offline"></span>
                @if($encryptionMode === 'e2ee')
                    <span title="End-to-end encrypted" style="font-size:.8rem;color:#166534;background:#dcfce7;padding:.15rem .5rem;border-radius:999px">🔒 E2EE</span>
                @else
                    <span title="Standard encryption" style="font-size:.8rem;color:#6b7280;background:#f3f4f6;padding:.15rem .5rem;border-radius:999px">🔓 Standard</span>
                @endif
            </div>

            {{-- Decryption loading state --}}
            <div x-show="loading" style="text-align:center;padding:1rem;color:#6b7280;font-size:.9rem">
                Decrypting messages...
            </div>

            {{-- Messages list --}}
            <div id="chat-messages" class="chat-messages" x-show="!loading">
                {{-- Pre-loaded messages rendered via Alpine for unified E2EE/standard path --}}
                <template x-if="initialMessages.length === 0 && newMessages.length === 0">
                    <p class="text-muted text-center" id="empty-state" style="margin:auto">No messages yet. Say hi! 👋</p>
                </template>

                <template x-for="msg in initialMessages" :key="'init-' + msg.message.id">
                    <div class="msg-row" :class="parseInt(msg.sender.id) === {{ $authUser->id }} ? 'mine' : 'theirs'">
                        <div class="message-bubble" :class="parseInt(msg.sender.id) === {{ $authUser->id }} ? 'mine' : 'theirs'">
                            <span x-html="nl2br(msg.message.body)"></span>
                            <div class="message-meta" x-text="formatTime(msg.message.created_at)"></div>
                        </div>
                    </div>
                </template>

                {{-- Dynamically appended messages (via Alpine / WebSocket) --}}
                <template x-for="msg in newMessages" :key="msg.message.id">
                    <div class="msg-row" :class="parseInt(msg.sender.id) === {{ $authUser->id }} ? 'mine' : 'theirs'">
                        <div class="message-bubble" :class="parseInt(msg.sender.id) === {{ $authUser->id }} ? 'mine' : 'theirs'">
                            <span x-html="nl2br(msg.message.body)"></span>
                            <div class="message-meta" x-text="formatTime(msg.message.created_at)"></div>
                        </div>
                    </div>
                </template>

                {{-- Auto-scroll anchor --}}
                <div id="messages-end"></div>
            </div>

            {{-- Message input --}}
            <div class="chat-input-area">
                <form
                    style="display:contents"
                    @submit.prevent="sendMessage()"
                >
                    <textarea
                        class="form-control"
                        placeholder="Write a message..."
                        rows="1"
                        maxlength="30000"
                        x-model="body"
                        @keydown.enter.prevent.exact="sendMessage()"
                        @keydown.shift.enter.exact="body += '\n'"
                        x-ref="textarea"
                        style="flex:1; overflow-y:hidden"
                    ></textarea>
                    <button type="submit" class="btn-send" :disabled="sending" title="Send">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function chatApp(initialMessagesRaw, encryptionMode) {
    return {
        initialMessages: [],
        newMessages: [],
        body: '',
        sending: false,
        loading: encryptionMode === 'e2ee',

        async init() {

            this.scrollToBottom();

            // Auto-resize textarea on every body change
            this.$watch('body', () => {
                this.$nextTick(() => {
                    const ta = this.$refs.textarea;
                    if (!ta) return;
                    ta.style.height = 'auto';
                    ta.style.height = Math.min(ta.scrollHeight, 160) + 'px';
                });
            });

            // Presence
            const recipientId = {{ $recipient->id }};
            const ev = window.__app?.presenceEvents ?? {};
            this.updateRecipientStatus(window.__app?.onlineIds?.has(recipientId) ?? false);
            document.addEventListener(ev.updated ?? 'presence:updated', () => {
                this.updateRecipientStatus(window.__app?.onlineIds?.has(recipientId) ?? false);
            });
            document.addEventListener(ev.joining ?? 'presence:joining', (e) => {
                if (parseInt(e.detail.id) === recipientId) this.updateRecipientStatus(true);
            });
            document.addEventListener(ev.leaving ?? 'presence:leaving', (e) => {
                if (parseInt(e.detail.id) === recipientId) this.updateRecipientStatus(false);
            });

            // Decrypt initial messages after E2EE is ready
            if (encryptionMode === 'e2ee') {
                await this.waitForE2EE();
                this.initialMessages = await this.decryptMessages(initialMessagesRaw);
            } else {
                this.initialMessages = initialMessagesRaw;
            }

            this.loading = false;
            this.$nextTick(() => this.scrollToBottom());

            if (!window.Echo) {
                console.warn('[Chat] Echo not initialized — real-time disabled');
                return;
            }

            window.Echo.private('messages.{{ $authUser->id }}')
                .listen('.message.sent', async (event) => {

                    if (parseInt(event.sender.id) === recipientId) {
                        if (event.message.is_encrypted && encryptionMode === 'e2ee') {
                            const decrypted = await this.decryptSingleMessage(event);
                            if (decrypted) this.newMessages.push(decrypted);
                        } else {
                            this.newMessages.push(event);
                        }

                        this.$nextTick(() => this.scrollToBottom());

                        fetch('{{ route('messages.read', $recipient) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        });
                    }
                });
        },

        waitForE2EE() {
            return new Promise((resolve) => {
                if (window.__app?.e2ee?.ready) return resolve();
                document.addEventListener('e2ee:ready', resolve, { once: true });
            });
        },

        async getAESKeyForRecipient() {
            const e2ee = window.__app?.e2ee;
            if (!e2ee?.ready || !e2ee.privateKey) return null;

            const recipientPubKeyBase64 = await e2ee.getPublicKey({{ $recipient->id }});
            const recipientPubKey = await window.__app.crypto.importPublicKey(recipientPubKeyBase64);
            return window.__app.crypto.deriveAESKey(e2ee.privateKey, recipientPubKey);
        },

        async decryptMessages(messages) {
            let aesKey = null;
            try {
                aesKey = await this.getAESKeyForRecipient();
            } catch (err) {
                console.error('[Chat] Could not derive AES key for initial messages', err);
                return messages;
            }

            return Promise.all(messages.map(async (msg) => {
                if (!msg.message.is_encrypted || !aesKey) return msg;
                try {
                    const payload = JSON.parse(msg.message.body);
                    const plain = await window.__app.crypto.decryptMessage(aesKey, payload.ciphertext, payload.iv);
                    return { ...msg, message: { ...msg.message, body: plain } };
                } catch (err) {
                    console.error('[Chat] Failed to decrypt message', msg.message.id, err);
                    return { ...msg, message: { ...msg.message, body: '[Decryption failed]' } };
                }
            }));
        },

        async decryptSingleMessage(event) {
            try {
                const aesKey = await this.getAESKeyForRecipient();
                if (!aesKey) return event;
                const payload = JSON.parse(event.message.body);
                const plain = await window.__app.crypto.decryptMessage(aesKey, payload.ciphertext, payload.iv);
                return { ...event, message: { ...event.message, body: plain } };
            } catch (err) {
                console.error('[Chat] Failed to decrypt incoming message', err);
                return { ...event, message: { ...event.message, body: '[Decryption failed]' } };
            }
        },

        async sendMessage() {
            const text = this.body.trim();
            if (!text || this.sending) return;

            this.sending = true;

            try {
                let bodyToSend = text;

                if (encryptionMode === 'e2ee') {
                    const aesKey = await this.getAESKeyForRecipient();
                    if (!aesKey) {
                        console.error('[Chat] Cannot encrypt — AES key unavailable');
                        return;
                    }
                    const { ciphertext, iv } = await window.__app.crypto.encryptMessage(aesKey, text);
                    bodyToSend = JSON.stringify({ ciphertext, iv });
                }

                const response = await fetch('{{ route('messages.store', $recipient) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ body: bodyToSend }),
                });

                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    console.error('[Chat] Send failed', response.status, err);
                    return;
                }

                const data = await response.json();

                this.newMessages.push({
                    message: { id: data.id, body: text, created_at: data.created_at, is_encrypted: data.is_encrypted },
                    sender: { id: {{ $authUser->id }}, name: '{{ $authUser->name }}' },
                });

                this.body = '';
                this.$nextTick(() => this.scrollToBottom());
            } catch (err) {
                console.error('[Chat] Send error', err);
            } finally {
                this.sending = false;
            }
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const el = document.getElementById('messages-end');
                if (el) el.scrollIntoView({ behavior: 'smooth' });
            });
        },

        updateRecipientStatus(online) {
            const indicator = document.getElementById('recipient-status');
            if (indicator) {
                indicator.className = `ws-status ${online ? 'connected' : 'disconnected'}`;
                indicator.title = online ? 'Online' : 'Offline';
            }
        },

        formatTime(isoString) {
            if (!isoString) return '';
            const d = new Date(isoString);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        nl2br: (text) => window.__app.nl2br(text),
    };
}

window.chatApp = chatApp;
</script>
@endpush
