@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="container">
    <div class="card" x-data="usersApp()">
        <h1 class="card-title">All Users</h1>

        @if ($users->isEmpty())
            <p class="text-muted">No other users registered yet.</p>
        @else
            <ul class="user-list" id="user-list">
                @foreach ($users as $user)
                    @php $count = $unreadCounts[$user->id] ?? 0; @endphp
                    <li onclick="location.href='{{ route('messages.show', $user) }}'">
                        <div class="user-info">
                            <div class="user-avatar" style="position:relative">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                <span class="online-dot" id="dot-{{ $user->id }}" style="display:none"></span>
                            </div>
                            <span class="user-name">{{ $user->name }}</span>
                            <span class="badge" id="badge-{{ $user->id }}"
                                @if ($count === 0) style="display:none" @endif
                            >{{ $count }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function usersApp() {
    return {
        totalUnread: 0,

        init() {
            // Seed total from server-rendered badges (avoids DOM scan on every update)
            this.totalUnread = Array.from(document.querySelectorAll('.badge'))
                .reduce((sum, el) => sum + (parseInt(el.textContent) || 0), 0);
            this.updateTitle();

            // Apply initial presence state (in case presenceApp already loaded)
            this.applyPresence();

            // Listen for presence updates from the global presenceApp
            const ev = window.__app?.presenceEvents ?? {};
            document.addEventListener(ev.updated ?? 'presence:updated', () => this.applyPresence());
            document.addEventListener(ev.joining ?? 'presence:joining', (e) => this.setDot(e.detail.id, true));
            document.addEventListener(ev.leaving ?? 'presence:leaving', (e) => this.setDot(e.detail.id, false));

            if (!window.Echo) {
                console.warn('[Users] Echo not initialized — live badges disabled');
                return;
            }

            window.Echo.private('messages.{{ auth()->id() }}')
                .listen('.message.sent', (event) => {
                    console.debug('[Users] New message received', event);

                    const senderId = event.sender?.id;
                    if (!senderId) return;

                    const badge = document.getElementById('badge-' + senderId);
                    if (badge) {
                        const current = parseInt(badge.textContent) || 0;
                        badge.textContent = current + 1;
                        badge.style.display = '';
                    }

                    this.totalUnread++;
                    this.updateTitle();
                });
        },

        applyPresence() {
            document.querySelectorAll('[id^="dot-"]').forEach(dot => {
                const userId = parseInt(dot.id.replace('dot-', ''));
                this.setDot(userId, window.__app?.onlineIds?.has(userId) ?? false);
            });
        },

        setDot(userId, online) {
            const dot = document.getElementById('dot-' + userId);
            if (dot) dot.style.display = online ? '' : 'none';
        },

        updateTitle() {
            document.title = this.totalUnread > 0
                ? `(${this.totalUnread}) QuickMessage — Users`
                : 'QuickMessage — Users';
        },
    };
}
</script>
@endpush
