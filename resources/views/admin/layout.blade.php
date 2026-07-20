<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Search Admin') — Search Administration Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.625rem 1rem; border-radius: 0.5rem;
            font-size: 0.875rem; font-weight: 500;
            transition: background-color 0.15s, color 0.15s;
            color: #d1d5db; text-decoration: none;
        }
        .sidebar-link:hover { background-color: #374151; color: #fff; }
        .sidebar-link.active { background-color: #2563eb; color: #fff; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 flex flex-col flex-shrink-0 overflow-y-auto">
        <div class="px-6 py-5 border-b border-gray-700">
            <div class="text-white font-bold text-lg">Search Admin</div>
            <div class="text-gray-400 text-xs mt-0.5">Administration Console</div>
        </div>

        @php
            $currentStoreId = (int) request('store_id', $stores->first()?->id ?? 1);
            $sp = '?store_id=' . $currentStoreId;
        @endphp

        @if(isset($stores) && count($stores) > 1)
        <div class="px-4 py-3 border-b border-gray-700">
            <label class="text-gray-400 text-xs uppercase tracking-wider">Store</label>
            <select id="store-selector"
                    class="mt-1 w-full bg-gray-800 text-gray-200 rounded px-2 py-1 text-sm border border-gray-600"
                    onchange="location.href=location.pathname+'?store_id='+this.value">
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" {{ $currentStoreId == $store->id ? 'selected' : '' }}>
                        {{ $store->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        <nav class="flex-1 px-3 py-4 space-y-1">
            <div class="text-gray-500 text-xs uppercase tracking-wider px-4 mb-2">Overview</div>
            <a href="{{ route('admin.dashboard') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                Dashboard
            </a>
            @can('view analytics')
            <a href="{{ route('admin.analytics') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.analytics*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Analytics
            </a>
            @endcan

            @canany(['manage query rules', 'manage synonyms', 'manage campaigns'])
            <div class="text-gray-500 text-xs uppercase tracking-wider px-4 mb-2 mt-4">Merchandising</div>
            @can('manage query rules')
            <a href="{{ route('admin.query-rules.index') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.query-rules*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Query Rules
            </a>
            @endcan
            @can('manage synonyms')
            <a href="{{ route('admin.synonyms.index') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.synonyms*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                </svg>
                Synonyms
            </a>
            @endcan
            @can('manage campaigns')
            <a href="{{ route('admin.campaigns.index') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.campaigns*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                Campaigns
            </a>
            @endcan
            @endcanany

            @canany(['manage users', 'manage stores'])
            <div class="text-gray-500 text-xs uppercase tracking-wider px-4 mb-2 mt-4">Administration</div>
            @can('manage users')
            <a href="{{ route('admin.users.index') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Users
            </a>
            @endcan
            @can('manage stores')
            <a href="{{ route('admin.stores.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.stores*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Stores
            </a>
            <a href="{{ route('admin.imports.index') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.imports*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import
            </a>
            @endcan
            @endcanany

            @canany(['view search preview', 'view audit log', 'manage stores'])
            <div class="text-gray-500 text-xs uppercase tracking-wider px-4 mb-2 mt-4">Tools</div>
            @can('view search preview')
            <a href="{{ route('admin.preview') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.preview*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Search Preview
            </a>
            <a href="{{ route('admin.ranking') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.ranking*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Ranking Explainer
            </a>
            @endcan
            @can('view audit log')
            <a href="{{ route('admin.audit-log') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.audit-log*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                Audit Log
            </a>
            @endcan
            @can('manage stores')
            <a href="{{ route('admin.benchmarks.index') . $sp }}"
               class="sidebar-link {{ request()->routeIs('admin.benchmarks*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Benchmarks
            </a>
            @endcan
            @endcanany
        </nav>

        <div class="px-4 py-3 border-t border-gray-700">
            <div class="text-gray-400 text-xs">{{ auth()->user()?->email }}</div>
            <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-red-400 text-xs hover:text-red-300 bg-transparent border-0 p-0 cursor-pointer">Sign out</button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
        <!-- Top Bar -->
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h1>
                @hasSection('breadcrumb')
                <nav class="text-sm text-gray-500 mt-0.5">@yield('breadcrumb')</nav>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @yield('header-actions')
            </div>
        </header>

        <!-- Alerts -->
        @if(session('success'))
        <div class="mx-6 mt-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            {{ session('error') }}
        </div>
        @endif

        <div class="p-6">
            @yield('content')
        </div>
    </main>
</div>

<script>
// Global AJAX setup
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const storeId = new URLSearchParams(location.search).get('store_id') || '1';

async function apiRequest(method, url, data = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    };
    if (data) opts.body = JSON.stringify(data);
    const res = await fetch(url, opts);
    if (!res.ok) throw new Error(await res.text());
    return res.status === 204 ? null : res.json();
}
</script>

@yield('scripts')
</body>
</html>
