<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'QuickMessage') }} — @yield('title', 'Chat')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar">
        <a href="{{ route('users.index') }}" class="brand">⚡ QuickMessage</a>
        <div class="nav-user">
            <span>{{ auth()->user()->name }}</span>
            <a href="{{ route('settings.index') }}" class="btn btn-sm btn-secondary">Settings</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="btn btn-sm btn-secondary">Logout</button>
            </form>
        </div>
    </nav>

    <div x-data="presenceApp()" style="display:none"></div>

    <main>
        @yield('content')
    </main>

    @auth
    <script>
    window.__app = window.__app ?? {};
    window.__app.e2eeEnabled = {{ auth()->user()->e2ee_enabled ? 'true' : 'false' }};
    window.__app.authUserId  = {{ auth()->id() }};
    </script>
    @vite(['resources/js/key-init.js'])
    @endauth

    @stack('scripts')

    <script>
    // App namespace — avoids polluting window with bare globals
    window.__app = window.__app ?? {};
    window.__app.onlineIds = new Set();

    // Shared utilities
    window.__app.nl2br = (text) => {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/\n/g, '<br>');
    };

    // Presence event name constants — single source of truth
    window.__app.presenceEvents = {
        updated: 'presence:updated',
        joining: 'presence:joining',
        leaving: 'presence:leaving',
    };

    function presenceApp() {
        return {
            init() {
                if (!window.Echo) return;

                const ev = window.__app.presenceEvents;

                window.Echo.join('presence.online')
                    .here((members) => {
                        window.__app.onlineIds = new Set(members.map(m => m.id));
                        document.dispatchEvent(new CustomEvent(ev.updated));
                    })
                    .joining((member) => {
                        window.__app.onlineIds.add(member.id);
                        document.dispatchEvent(new CustomEvent(ev.joining, { detail: member }));
                    })
                    .leaving((member) => {
                        window.__app.onlineIds.delete(member.id);
                        document.dispatchEvent(new CustomEvent(ev.leaving, { detail: member }));
                    });
            },
        };
    }
    </script>
</body>
</html>
