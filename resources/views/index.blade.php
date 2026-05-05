@php
    $parentLayout = $logscopeInstalled && view()->exists('logscope::layout')
        ? 'logscope::layout'
        : 'logscope-guard::layout';
@endphp

@extends($parentLayout)

@section('content')
<div x-data="guardManagement('{{ $apiBase }}')" x-init="init()" class="min-h-screen" style="background-color: var(--surface-0);">

    {{-- Header --}}
    <header style="background-color: var(--surface-1); border-bottom: 1px solid var(--border);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                @if($logscopeInstalled && $logscopeUrl)
                <a href="{{ $logscopeUrl }}" style="color: var(--text-muted);" class="hover:opacity-75 transition-opacity">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                @endif
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span class="font-semibold text-sm" style="color: var(--text-primary);">IP Blacklist</span>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-mono" style="background-color: var(--surface-3); color: var(--text-muted);" x-text="total + ' active'"></span>
            </div>

            <div class="flex items-center gap-2">
                {{-- Search --}}
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="search" @input.debounce.300ms="currentPage = 1"
                        placeholder="Search IP..."
                        class="h-9 pl-9 pr-4 rounded-lg text-sm border focus:outline-none focus:ring-2"
                        style="background-color: var(--surface-2); border-color: var(--border); color: var(--text-primary); --tw-ring-color: rgba(var(--accent-rgb), 0.5);">
                </div>

                {{-- Source filter --}}
                <select x-model="sourceFilter" @change="currentPage = 1"
                    class="h-9 px-3 rounded-lg text-sm border focus:outline-none"
                    style="background-color: var(--surface-2); border-color: var(--border); color: var(--text-secondary);">
                    <option value="">All sources</option>
                    <option value="manual">Manual</option>
                    <option value="auto">Auto</option>
                    <option value="sync">Sync</option>
                </select>

                {{-- Dark mode toggle --}}
                <button @click="darkMode = !darkMode" class="h-9 w-9 flex items-center justify-center rounded-lg transition-colors"
                    style="color: var(--text-muted);" onmouseover="this.style.backgroundColor='var(--surface-2)'" onmouseout="this.style.backgroundColor=''">
                    <svg x-show="darkMode" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg x-show="!darkMode" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                {{-- Block IP button --}}
                <button @click="openBlockModal()"
                    class="h-9 px-4 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors"
                    style="background-color: var(--accent); color: white;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Block IP
                </button>
            </div>
        </div>
    </header>

    {{-- Table --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

        {{-- Empty state --}}
        <div x-show="!loading && filteredBlocks.length === 0" class="text-center py-20">
            <svg class="w-12 h-12 mx-auto mb-4" style="color: var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <p class="text-sm font-medium" style="color: var(--text-secondary);" x-text="search ? 'No IPs match your search.' : 'No blocked IPs.'"></p>
            <p class="text-xs mt-1" style="color: var(--text-muted);" x-show="!search">Block an IP using the button above.</p>
        </div>

        {{-- Loading --}}
        <div x-show="loading" class="text-center py-20">
            <svg class="w-6 h-6 mx-auto animate-spin" style="color: var(--accent);" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        {{-- Table --}}
        <div x-show="!loading && filteredBlocks.length > 0" class="rounded-xl overflow-hidden border" style="border-color: var(--border);">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background-color: var(--surface-2); border-bottom: 1px solid var(--border);">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Reason</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Blocked By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Expires</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Blocked At</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(block, i) in paginatedBlocks" :key="block.id">
                        <tr :style="i % 2 === 0 ? 'background-color: var(--surface-1)' : 'background-color: var(--surface-0)'"
                            style="border-bottom: 1px solid var(--border);">
                            <td class="px-4 py-3 font-mono font-medium" style="color: var(--text-primary);" x-text="block.ip"></td>
                            <td class="px-4 py-3 max-w-xs truncate" style="color: var(--text-secondary);" :title="block.reason" x-text="block.reason || '—'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="{
                                        'bg-blue-500/15 text-blue-400':   block.source === 'manual',
                                        'bg-amber-500/15 text-amber-400': block.source === 'auto',
                                        'bg-purple-500/15 text-purple-400': block.source === 'sync'
                                    }"
                                    x-text="block.source">
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted);" x-text="block.blocked_by || '—'"></td>
                            <td class="px-4 py-3 text-xs font-mono" style="color: var(--text-muted);">
                                <span x-show="!block.expires_at" class="text-green-500">Permanent</span>
                                <span x-show="block.expires_at" x-text="formatExpiry(block.expires_at)"></span>
                            </td>
                            <td class="px-4 py-3 text-xs font-mono" style="color: var(--text-muted);" x-text="formatDate(block.created_at)"></td>
                            <td class="px-4 py-3 text-right">
                                <button @click="confirmUnblock(block)"
                                    class="text-xs px-3 py-1.5 rounded-lg transition-colors"
                                    style="color: var(--text-muted); border: 1px solid var(--border);"
                                    onmouseover="this.style.color='#f87171'; this.style.borderColor='rgba(239,68,68,0.4)'"
                                    onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border)'">
                                    Unblock
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div x-show="totalPages > 1" class="mt-4 flex items-center justify-between">
            <p class="text-xs" style="color: var(--text-muted);">
                Showing <span x-text="((currentPage - 1) * perPage) + 1"></span>–<span x-text="Math.min(currentPage * perPage, filteredBlocks.length)"></span> of <span x-text="filteredBlocks.length"></span>
            </p>
            <div class="flex items-center gap-1">
                <button @click="currentPage--" :disabled="currentPage === 1"
                    class="h-8 w-8 flex items-center justify-center rounded-lg text-sm transition-colors disabled:opacity-30"
                    style="color: var(--text-muted);" onmouseover="this.style.backgroundColor='var(--surface-2)'" onmouseout="this.style.backgroundColor=''">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <span class="text-sm px-3" style="color: var(--text-secondary);">
                    <span x-text="currentPage"></span> / <span x-text="totalPages"></span>
                </span>
                <button @click="currentPage++" :disabled="currentPage === totalPages"
                    class="h-8 w-8 flex items-center justify-center rounded-lg text-sm transition-colors disabled:opacity-30"
                    style="color: var(--text-muted);" onmouseover="this.style.backgroundColor='var(--surface-2)'" onmouseout="this.style.backgroundColor=''">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Block IP Modal --}}
    <div x-show="showBlockModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background-color: rgba(0,0,0,0.7);"
        @keydown.escape.window="showBlockModal = false">
        <div @click.away="showBlockModal = false"
            class="w-full max-w-md rounded-xl border p-6 shadow-2xl"
            style="background-color: var(--surface-1); border-color: var(--border);"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">

            <div class="flex items-center justify-between mb-5">
                <h2 class="font-semibold text-base" style="color: var(--text-primary);">Block IP Address</h2>
                <button @click="showBlockModal = false" style="color: var(--text-muted);" class="hover:opacity-75">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">IP Address <span style="color: #f87171;">*</span></label>
                    <input type="text" x-model="blockForm.ip" placeholder="1.2.3.4"
                        class="w-full h-9 px-3 rounded-lg text-sm border font-mono focus:outline-none focus:ring-2"
                        style="background-color: var(--surface-2); border-color: var(--border); color: var(--text-primary); --tw-ring-color: rgba(var(--accent-rgb), 0.5);">
                    <p x-show="blockError" class="text-xs mt-1" style="color: #f87171;" x-text="blockError"></p>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">Reason <span style="color: var(--text-muted);">(optional)</span></label>
                    <input type="text" x-model="blockForm.reason" placeholder="e.g. Repeated 404 scanning"
                        class="w-full h-9 px-3 rounded-lg text-sm border focus:outline-none focus:ring-2"
                        style="background-color: var(--surface-2); border-color: var(--border); color: var(--text-primary); --tw-ring-color: rgba(var(--accent-rgb), 0.5);">
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">Duration</label>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="opt in durationOptions" :key="opt.value">
                            <button @click="blockForm.duration = opt.value"
                                class="h-9 rounded-lg text-xs font-medium border transition-colors"
                                :style="blockForm.duration === opt.value
                                    ? 'background-color: rgba(var(--accent-rgb), 0.15); border-color: var(--accent); color: var(--accent);'
                                    : 'background-color: var(--surface-2); border-color: var(--border); color: var(--text-secondary);'"
                                x-text="opt.label">
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button @click="showBlockModal = false"
                    class="flex-1 h-9 rounded-lg text-sm font-medium border transition-colors"
                    style="border-color: var(--border); color: var(--text-secondary);"
                    onmouseover="this.style.backgroundColor='var(--surface-2)'" onmouseout="this.style.backgroundColor=''">
                    Cancel
                </button>
                <button @click="submitBlock()" :disabled="blocking"
                    class="flex-1 h-9 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                    style="background-color: var(--accent); color: white;">
                    <span x-show="!blocking">Block IP</span>
                    <span x-show="blocking">Blocking...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Unblock confirmation --}}
    <div x-show="showUnblockConfirm" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background-color: rgba(0,0,0,0.7);"
        @keydown.escape.window="showUnblockConfirm = false">
        <div @click.away="showUnblockConfirm = false"
            class="w-full max-w-sm rounded-xl border p-6 shadow-2xl"
            style="background-color: var(--surface-1); border-color: var(--border);"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">

            <h2 class="font-semibold text-base mb-2" style="color: var(--text-primary);">Unblock IP?</h2>
            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                Remove <span class="font-mono" style="color: var(--text-primary);" x-text="unblockTarget?.ip"></span> from the blacklist?
            </p>

            <div class="flex gap-3">
                <button @click="showUnblockConfirm = false"
                    class="flex-1 h-9 rounded-lg text-sm font-medium border"
                    style="border-color: var(--border); color: var(--text-secondary);"
                    onmouseover="this.style.backgroundColor='var(--surface-2)'" onmouseout="this.style.backgroundColor=''">
                    Cancel
                </button>
                <button @click="submitUnblock()" :disabled="unblocking"
                    class="flex-1 h-9 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                    style="background-color: #ef4444; color: white;">
                    <span x-show="!unblocking">Unblock</span>
                    <span x-show="unblocking">Removing...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast.show" x-cloak x-transition
        class="fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg text-sm font-medium shadow-xl"
        :style="toast.type === 'success'
            ? 'background-color: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #34d399;'
            : 'background-color: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #f87171;'"
        x-text="toast.message">
    </div>
</div>

<script>
function guardManagement(apiBase) {
    return {
        apiBase,
        blocks: [],
        loading: true,
        search: '',
        sourceFilter: '',
        currentPage: 1,
        perPage: 20,

        showBlockModal: false,
        blockForm: { ip: '', reason: '', duration: 'permanent' },
        blockError: '',
        blocking: false,

        showUnblockConfirm: false,
        unblockTarget: null,
        unblocking: false,

        toast: { show: false, message: '', type: 'success' },

        durationOptions: [
            { label: '1 hour',    value: '1h' },
            { label: '6 hours',   value: '6h' },
            { label: '24 hours',  value: '24h' },
            { label: '7 days',    value: '7d' },
            { label: '30 days',   value: '30d' },
            { label: 'Permanent', value: 'permanent' },
        ],

        async init() {
            await this.fetchBlocks();
        },

        async fetchBlocks() {
            this.loading = true;
            try {
                const res = await fetch(`${this.apiBase}/blocks`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                this.blocks = data.data ?? [];
            } catch (e) {
                this.showToast('Failed to load blacklist.', 'error');
            } finally {
                this.loading = false;
            }
        },

        get total() {
            return this.blocks.length;
        },

        get filteredBlocks() {
            return this.blocks.filter(b => {
                const matchSearch = !this.search || b.ip.includes(this.search.trim());
                const matchSource = !this.sourceFilter || b.source === this.sourceFilter;
                return matchSearch && matchSource;
            });
        },

        get paginatedBlocks() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredBlocks.slice(start, start + this.perPage);
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredBlocks.length / this.perPage));
        },

        openBlockModal() {
            this.blockForm = { ip: '', reason: '', duration: 'permanent' };
            this.blockError = '';
            this.showBlockModal = true;
        },

        durationToExpiry(duration) {
            if (duration === 'permanent') return null;
            const map = { '1h': 60, '6h': 360, '24h': 1440, '7d': 10080, '30d': 43200 };
            const minutes = map[duration] ?? null;
            if (!minutes) return null;
            const d = new Date(Date.now() + minutes * 60 * 1000);
            return d.toISOString();
        },

        async submitBlock() {
            this.blockError = '';
            if (!this.blockForm.ip.trim()) {
                this.blockError = 'IP address is required.';
                return;
            }

            this.blocking = true;
            try {
                const payload = {
                    ip: this.blockForm.ip.trim(),
                    reason: this.blockForm.reason || null,
                    expires_at: this.durationToExpiry(this.blockForm.duration),
                };

                const res = await fetch(`${this.apiBase}/block`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.blockError = data.error ?? data.message ?? 'Failed to block IP.';
                    return;
                }

                this.showBlockModal = false;
                this.showToast(`${payload.ip} has been blocked.`, 'success');
                await this.fetchBlocks();
            } catch (e) {
                this.blockError = 'Request failed. Please try again.';
            } finally {
                this.blocking = false;
            }
        },

        confirmUnblock(block) {
            this.unblockTarget = block;
            this.showUnblockConfirm = true;
        },

        async submitUnblock() {
            this.unblocking = true;
            try {
                const ip = encodeURIComponent(this.unblockTarget.ip);
                const res = await fetch(`${this.apiBase}/block/${ip}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!res.ok) {
                    this.showToast('Failed to unblock IP.', 'error');
                    return;
                }

                this.showUnblockConfirm = false;
                this.showToast(`${this.unblockTarget.ip} has been unblocked.`, 'success');
                await this.fetchBlocks();
            } catch (e) {
                this.showToast('Request failed. Please try again.', 'error');
            } finally {
                this.unblocking = false;
            }
        },

        formatDate(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        formatExpiry(iso) {
            if (!iso) return 'Permanent';
            const d = new Date(iso);
            const now = new Date();
            const diff = d - now;
            if (diff < 0) return 'Expired';
            if (diff < 3600000) return `${Math.ceil(diff / 60000)}m`;
            if (diff < 86400000) return `${Math.ceil(diff / 3600000)}h`;
            return `${Math.ceil(diff / 86400000)}d`;
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3000);
        },
    };
}
</script>
@endsection
