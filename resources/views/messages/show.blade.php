@extends('layouts.app')

@section('title', 'Chat with ' . $recipient->name)

@section('content')
<div class="chat-page">
    <div class="container">
        {{-- Single Alpine component wrapping both messages and input --}}
        <div class="chat-card" x-data="chatApp()">

            {{-- Chat header --}}
            <div class="chat-header">
                <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">← Back</a>
                <div class="user-avatar" style="width:36px;height:36px;font-size:.8rem">
                    {{ strtoupper(substr($recipient->name, 0, 1)) }}
                </div>
                <span class="chat-header-name">{{ $recipient->name }}</span>
                <span class="ws-status" id="recipient-status" title="Offline"></span>
            </div>

            {{-- Messages list --}}
            <div id="chat-messages" class="chat-messages">
                {{-- Pre-loaded messages from DB --}}
                @forelse ($messages as $message)
                    <div class="msg-row {{ $message->sender_id === $authUser->id ? 'mine' : 'theirs' }}">
                        <div class="message-bubble {{ $message->sender_id === $authUser->id ? 'mine' : 'theirs' }}">
                            {!! nl2br(e($message->body)) !!}
                            <div class="message-meta">{{ $message->created_at->format('H:i') }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center" id="empty-state" style="margin:auto">No messages yet. Say hi! 👋</p>
                @endforelse

                {{-- Dynamically appended messages (via Alpine) --}}
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
                        maxlength="5000"
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
function chatApp() {
    return {
        newMessages: [],
        body: '',
        sending: false,

        init() {
            console.debug('[Chat] Initializing chat for recipient {{ $recipient->id }}');

            this.scrollToBottom();

            // Auto-resize textarea on every body change (typing, Shift+Enter, paste, clear)
            this.$watch('body', () => {
                this.$nextTick(() => {
                    const ta = this.$refs.textarea;
                    if (!ta) return;
                    ta.style.height = 'auto';
                    ta.style.height = Math.min(ta.scrollHeight, 160) + 'px';
                });
            });

            // Presence: apply initial state + listen for changes
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

            if (!window.Echo) {
                console.warn('[Chat] Echo not initialized — real-time disabled');
                return;
            }

            window.Echo.private('messages.{{ $authUser->id }}')
                .listen('.message.sent', (event) => {
                    console.debug('[Chat] Received message.sent event', event);

                    if (parseInt(event.sender.id) === recipientId) {
                        this.newMessages.push(event);
                        this.removeEmptyState();
                        this.$nextTick(() => this.scrollToBottom());

                        // Mark as read immediately — user is viewing this chat
                        fetch('{{ route('messages.read', $recipient) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        });
                    }
                });

        },

        async sendMessage() {
            const text = this.body.trim();
            if (!text || this.sending) return;

            this.sending = true;

            try {
                const response = await fetch('{{ route('messages.store', $recipient) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ body: text }),
                });

                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    console.error('[Chat] Send failed', response.status, err);
                    return;
                }

                const data = await response.json();

                this.newMessages.push({
                    message: { id: data.id, body: text, created_at: data.created_at },
                    sender: { id: {{ $authUser->id }}, name: '{{ $authUser->name }}' },
                });

                this.removeEmptyState();
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

        removeEmptyState() {
            const el = document.getElementById('empty-state');
            if (el) el.remove();
        },

        formatTime(isoString) {
            if (!isoString) return '';
            const d = new Date(isoString);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        nl2br: (text) => window.__app.nl2br(text),
    };
}
</script>
@endpush
