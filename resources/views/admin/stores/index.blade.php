@extends('admin.layout')

@section('title', 'Stores')
@section('page-title', 'Store Management')

@section('content')
<div x-data="storesPage()" x-init="init()">

    <div class="flex justify-end mb-6">
        <button @click="openCreate()"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + Add Store
        </button>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm border-l-4 border-green-500">
            <div class="text-xs text-gray-500">Active Stores</div>
            <div class="text-2xl font-bold text-gray-800 mt-1"
                 x-text="stores.filter(s => s.is_active && !s.deleted_at).length"></div>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border-l-4 border-yellow-400">
            <div class="text-xs text-gray-500">Inactive Stores</div>
            <div class="text-2xl font-bold text-gray-800 mt-1"
                 x-text="stores.filter(s => !s.is_active && !s.deleted_at).length"></div>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border-l-4 border-gray-400">
            <div class="text-xs text-gray-500">Deleted</div>
            <div class="text-2xl font-bold text-gray-800 mt-1"
                 x-text="stores.filter(s => s.deleted_at).length"></div>
        </div>
    </div>

    <!-- Store table -->
    <div class="bg-white rounded-lg shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Name</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Code</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Region</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Locale</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Currency</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Products</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="store in stores" :key="store.id">
                    <tr class="border-t border-gray-50 hover:bg-gray-50"
                        :class="store.deleted_at ? 'opacity-50' : ''">
                        <td class="px-4 py-3 font-medium text-gray-800" x-text="store.name"></td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600 bg-gray-50 rounded" x-text="store.code"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="store.region || '—'"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="store.locale"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="store.currency"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="store.store_products_count ?? 0"></td>
                        <td class="px-4 py-3">
                            <template x-if="store.deleted_at">
                                <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Deleted</span>
                            </template>
                            <template x-if="!store.deleted_at">
                                <span :class="store.is_active ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                                      class="px-2 py-0.5 rounded text-xs"
                                      x-text="store.is_active ? 'Active' : 'Inactive'"></span>
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2 justify-end">
                                <template x-if="store.deleted_at">
                                    <button @click="restoreStore(store)"
                                            class="text-green-600 text-xs hover:underline">Restore</button>
                                </template>
                                <template x-if="!store.deleted_at">
                                    <a :href="`/admin/stores/${store.id}/categories`"
                                       class="text-indigo-600 text-xs hover:underline">Categories</a>
                                </template>
                                <template x-if="!store.deleted_at">
                                    <button @click="openTokens(store)"
                                            class="text-purple-600 text-xs hover:underline">Tokens</button>
                                </template>
                                <template x-if="!store.deleted_at">
                                    <button @click="openEdit(store)"
                                            class="text-blue-600 text-xs hover:underline">Edit</button>
                                </template>
                                <template x-if="!store.deleted_at">
                                    <button @click="deleteStore(store)"
                                            class="text-red-500 text-xs hover:underline">Delete</button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="stores.length === 0">
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">No stores found.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Create / Edit store modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-5" x-text="editing ? 'Edit Store' : 'Add Store'"></h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input x-model="form.name" type="text" placeholder="North America Store"
                           class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Code <span class="text-red-500">*</span>
                        <span class="font-normal text-gray-400 text-xs ml-1">lowercase, letters/numbers/hyphens</span>
                    </label>
                    <input x-model="form.code" type="text" placeholder="us-main"
                           :disabled="!!editing"
                           :class="editing ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                           class="w-full border rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <p x-show="editing" class="text-xs text-gray-400 mt-1">Store code cannot be changed after creation.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                        <input x-model="form.region" type="text" placeholder="US"
                               class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Locale</label>
                        <input x-model="form.locale" type="text" placeholder="en_US"
                               class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                    <input x-model="form.currency" type="text" placeholder="USD" maxlength="3"
                           class="w-full border rounded px-3 py-2 text-sm uppercase focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" x-model="form.is_active" class="rounded">
                    Active
                </label>
            </div>

            <div x-show="error" class="mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-red-600 text-sm"
                 x-text="error"></div>

            <div class="flex gap-3 mt-6">
                <button @click="save()" :disabled="saving"
                        class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                    <span x-text="saving ? 'Saving…' : 'Save'"></span>
                </button>
                <button @click="closeModal()"
                        class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Token management modal -->
    <div x-show="showTokenModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-semibold">
                    API Tokens — <span class="text-blue-600 font-mono text-base" x-text="tokenStore?.name"></span>
                </h2>
                <button @click="closeTokenModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>

            <!-- Token list -->
            <div class="mb-5">
                <template x-if="tokens.length === 0 && !tokensLoading">
                    <p class="text-sm text-gray-400 text-center py-4">No tokens yet. Create one below.</p>
                </template>
                <template x-if="tokensLoading">
                    <p class="text-sm text-gray-400 text-center py-4">Loading…</p>
                </template>
                <div class="space-y-2" x-show="tokens.length > 0">
                    <template x-for="tok in tokens" :key="tok.id">
                        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded border border-gray-100">
                            <div>
                                <div class="text-sm font-medium text-gray-800" x-text="tok.name"></div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    Created <span x-text="formatDate(tok.created_at)"></span>
                                    <template x-if="tok.last_used_at">
                                        <span> · Last used <span x-text="formatDate(tok.last_used_at)"></span></span>
                                    </template>
                                    <template x-if="!tok.last_used_at">
                                        <span> · Never used</span>
                                    </template>
                                </div>
                            </div>
                            <button @click="revokeToken(tok)"
                                    class="text-red-500 text-xs hover:underline ml-4 shrink-0">Revoke</button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Create token form -->
            <div class="border-t border-gray-100 pt-4">
                <p class="text-sm font-medium text-gray-700 mb-2">Create new token</p>
                <div class="flex gap-2">
                    <input x-model="newTokenName" type="text" placeholder="Token label (e.g. Production App)"
                           class="flex-1 border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-purple-500">
                    <button @click="createToken()" :disabled="tokenCreating || !newTokenName.trim()"
                            class="bg-purple-600 text-white px-4 py-2 rounded text-sm hover:bg-purple-700 disabled:opacity-50 shrink-0">
                        <span x-text="tokenCreating ? 'Creating…' : 'Create'"></span>
                    </button>
                </div>
                <div x-show="tokenError" class="mt-2 text-red-600 text-xs" x-text="tokenError"></div>
            </div>
        </div>
    </div>

    <!-- New token reveal modal -->
    <div x-show="showTokenReveal" x-cloak class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-2">Token Created</h2>
            <p class="text-sm text-gray-500 mb-4">
                Copy this token now. It will <strong>not</strong> be shown again.
            </p>
            <div class="flex gap-2 items-center bg-gray-50 border border-gray-200 rounded px-3 py-2 mb-4">
                <code class="flex-1 text-xs font-mono text-gray-800 break-all" x-text="newTokenValue"></code>
                <button @click="copyToken()"
                        class="shrink-0 text-xs text-blue-600 hover:underline ml-2"
                        x-text="copied ? 'Copied!' : 'Copy'"></button>
            </div>
            <button @click="closeTokenReveal()"
                    class="w-full bg-gray-800 text-white py-2 rounded-lg text-sm hover:bg-gray-900">
                Done, I've copied it
            </button>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function storesPage() {
    return {
        stores: [],
        showModal: false,
        editing: null,
        saving: false,
        error: '',
        form: { name: '', code: '', region: '', locale: 'en_US', currency: 'USD', is_active: true },

        // token modal state
        showTokenModal: false,
        tokenStore: null,
        tokens: [],
        tokensLoading: false,
        newTokenName: '',
        tokenCreating: false,
        tokenError: '',

        // token reveal state
        showTokenReveal: false,
        newTokenValue: '',
        copied: false,

        async init() {
            await this.fetchStores();
        },

        async fetchStores() {
            const res = await fetch('/api/admin/stores', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            this.stores = await res.json();
        },

        openCreate() {
            this.editing = null;
            this.form = { name: '', code: '', region: '', locale: 'en_US', currency: 'USD', is_active: true };
            this.error = '';
            this.showModal = true;
        },

        openEdit(store) {
            this.editing = store;
            this.form = {
                name: store.name,
                code: store.code,
                region: store.region || '',
                locale: store.locale,
                currency: store.currency,
                is_active: store.is_active,
            };
            this.error = '';
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.editing = null;
            this.error = '';
        },

        async save() {
            this.saving = true;
            this.error = '';
            try {
                const url    = this.editing ? `/api/admin/stores/${this.editing.id}` : '/api/admin/stores';
                const method = this.editing ? 'PUT' : 'POST';
                await apiRequest(method, url, this.form);
                this.closeModal();
                await this.fetchStores();
            } catch (e) {
                try { this.error = Object.values(JSON.parse(e.message).errors)[0][0]; }
                catch { this.error = e.message || 'Something went wrong.'; }
            } finally {
                this.saving = false;
            }
        },

        async deleteStore(store) {
            const hasProducts = (store.store_products_count ?? 0) > 0;
            const msg = hasProducts
                ? `Delete "${store.name}"? It has ${store.store_products_count} product(s) linked. The store will be soft-deleted and can be restored.`
                : `Delete "${store.name}"?`;
            if (!confirm(msg)) return;
            try {
                await apiRequest('DELETE', `/api/admin/stores/${store.id}`);
                await this.fetchStores();
            } catch (e) {
                try { alert(JSON.parse(e.message).message || 'Could not delete store.'); }
                catch { alert('Could not delete store.'); }
            }
        },

        async restoreStore(store) {
            if (!confirm(`Restore "${store.name}"?`)) return;
            await apiRequest('POST', `/api/admin/stores/${store.id}/restore`);
            await this.fetchStores();
        },

        // --- token management ---

        async openTokens(store) {
            this.tokenStore = store;
            this.tokens = [];
            this.newTokenName = '';
            this.tokenError = '';
            this.showTokenModal = true;
            await this.fetchTokens();
        },

        closeTokenModal() {
            this.showTokenModal = false;
            this.tokenStore = null;
            this.tokens = [];
            this.tokenError = '';
        },

        async fetchTokens() {
            this.tokensLoading = true;
            try {
                const res = await fetch(`/api/admin/stores/${this.tokenStore.id}/tokens`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                this.tokens = await res.json();
            } finally {
                this.tokensLoading = false;
            }
        },

        async createToken() {
            if (!this.newTokenName.trim()) return;
            this.tokenCreating = true;
            this.tokenError = '';
            try {
                const res = await fetch(`/api/admin/stores/${this.tokenStore.id}/tokens`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ name: this.newTokenName.trim() }),
                });
                if (!res.ok) {
                    const data = await res.json();
                    this.tokenError = data.message || 'Could not create token.';
                    return;
                }
                const data = await res.json();
                this.newTokenName = '';
                await this.fetchTokens();
                this.newTokenValue = data.token;
                this.copied = false;
                this.showTokenReveal = true;
            } catch (e) {
                this.tokenError = 'Could not create token.';
            } finally {
                this.tokenCreating = false;
            }
        },

        async revokeToken(tok) {
            if (!confirm(`Revoke token "${tok.name}"? This cannot be undone.`)) return;
            try {
                await apiRequest('DELETE', `/api/admin/stores/${this.tokenStore.id}/tokens/${tok.id}`);
                await this.fetchTokens();
            } catch (e) {
                alert('Could not revoke token.');
            }
        },

        async copyToken() {
            try {
                await navigator.clipboard.writeText(this.newTokenValue);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            } catch {
                this.copied = false;
            }
        },

        closeTokenReveal() {
            this.showTokenReveal = false;
            this.newTokenValue = '';
        },

        formatDate(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        },
    };
}
</script>
@endsection
