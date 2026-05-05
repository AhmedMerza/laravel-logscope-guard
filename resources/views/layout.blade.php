<!DOCTYPE html>
<html lang="en"
    x-data="{ darkMode: localStorage.getItem('guard-dark') !== null ? localStorage.getItem('guard-dark') === 'true' : true }"
    x-init="$watch('darkMode', val => localStorage.setItem('guard-dark', val))"
    :class="darkMode ? 'dark' : 'light'">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guard — IP Blacklist</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --surface-0: #0a0a0b;
            --surface-1: #111113;
            --surface-2: #18181b;
            --surface-3: #27272a;
            --border: #3f3f46;
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --accent: #10b981;
            --accent-rgb: 16, 185, 129;
            --accent-glow: rgba(16, 185, 129, 0.4);
            --font-sans: 'Outfit', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, monospace;
        }
        .light {
            --surface-0: #ffffff;
            --surface-1: #f8fafc;
            --surface-2: #f1f5f9;
            --surface-3: #e2e8f0;
            --border: #cbd5e1;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
        }
        * { scrollbar-width: thin; scrollbar-color: var(--border) transparent; }
        *::-webkit-scrollbar { width: 6px; height: 6px; }
        *::-webkit-scrollbar-track { background: transparent; }
        *::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        *::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
    </style>
</head>
<body class="h-full antialiased" style="font-family: var(--font-sans); background-color: var(--surface-0); color: var(--text-primary);">
    @yield('content')
</body>
</html>
