@extends('admin.layout')

@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div x-data="usersPage()" x-init="init()">

    <div class="flex justify-end mb-4">
        <button @click="showModal = true; editing = null; resetForm()"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + New User
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <template x-for="role in roleCounts" :key="role.name">
            <div class="bg-white rounded-lg p-4 shadow-sm">
                <div class="text-xs text-gray-500 capitalize" x-text="role.name.replace('_', ' ')"></div>
                <div class="text-2xl font-bold text-gray-800 mt-1" x-text="role.count"></div>
            </div>
        </template>
    </div>

    <!-- User Table -->
    <div class="bg-white rounded-lg shadow-sm">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-3">
            <input x-model="search" type="text" placeholder="Filter by name or email…"
                   class="border rounded px-3 py-1.5 text-sm w-64 focus:outline-none focus:ring-1 focus:ring-blue-500">
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">User</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Role</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Joined</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="user in filteredUsers" :key="user.id">
                    <tr class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800" x-text="user.name"></div>
                            <div class="text-xs text-gray-400" x-text="user.email"></div>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="roleClass(user.role)"
                                  class="px-2 py-0.5 rounded text-xs font-medium capitalize"
                                  x-text="(user.role || 'none').replace('_', ' ')"></span>
                        </td>
                        <td class="px-4 py-3 text-gray-500" x-text="user.created_at"></td>
                        <td class="px-4 py-3 flex gap-2 justify-end">
                            <button @click="editUser(user)" class="text-blue-600 text-xs hover:underline">Edit</button>
                            <button @click="deleteUser(user)" class="text-red-500 text-xs hover:underline">Delete</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="filteredUsers.length === 0">
                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">No users found.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-4" x-text="editing ? 'Edit User' : 'New User'"></h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input x-model="form.name" type="text" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input x-model="form.email" type="email" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Password <span x-show="editing" class="text-gray-400 font-normal">(leave blank to keep current)</span>
                    </label>
                    <input x-model="form.password" type="password" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select x-model="form.role" class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">— Select role —</option>
                        <template x-for="role in roles" :key="role">
                            <option :value="role" x-text="role.replace('_', ' ')"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div x-show="error" class="mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-red-600 text-sm" x-text="error"></div>

            <div class="flex gap-3 mt-6">
                <button @click="saveUser()" :disabled="saving"
                        class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm disabled:opacity-50 hover:bg-blue-700">
                    <span x-text="saving ? 'Saving…' : 'Save'"></span>
                </button>
                <button @click="closeModal()" class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function usersPage() {
    return {
        users: [], roles: [], search: '',
        showModal: false, editing: null, saving: false, error: '',
        form: { name: '', email: '', password: '', role: '' },

        get filteredUsers() {
            const q = this.search.toLowerCase();
            return q
                ? this.users.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
                : this.users;
        },

        get roleCounts() {
            return this.roles.map(r => ({
                name: r,
                count: this.users.filter(u => u.role === r).length,
            }));
        },

        roleClass(role) {
            return {
                'system_admin': 'bg-purple-100 text-purple-700',
                'search_admin': 'bg-blue-100 text-blue-700',
                'analyst':      'bg-green-100 text-green-700',
                'read_only':    'bg-gray-100 text-gray-600',
            }[role] ?? 'bg-gray-100 text-gray-500';
        },

        async init() {
            await Promise.all([this.fetchUsers(), this.fetchRoles()]);
        },

        async fetchUsers() {
            const res = await fetch('/api/admin/users', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
            const data = await res.json();
            this.users = data.data || [];
        },

        async fetchRoles() {
            const res = await fetch('/api/admin/users/roles', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
            const data = await res.json();
            this.roles = data.roles || [];
        },

        editUser(user) {
            this.editing = user;
            this.form = { name: user.name, email: user.email, password: '', role: user.role || '' };
            this.error = '';
            this.showModal = true;
        },

        resetForm() {
            this.form = { name: '', email: '', password: '', role: '' };
            this.error = '';
        },

        closeModal() {
            this.showModal = false;
            this.editing = null;
            this.resetForm();
        },

        async saveUser() {
            this.saving = true;
            this.error = '';
            try {
                if (this.editing) {
                    await apiRequest('PUT', `/api/admin/users/${this.editing.id}`, this.form);
                } else {
                    await apiRequest('POST', '/api/admin/users', this.form);
                }
                this.closeModal();
                await this.fetchUsers();
            } catch (e) {
                try { this.error = Object.values(JSON.parse(e.message).errors)[0][0]; }
                catch { this.error = 'Something went wrong. Please try again.'; }
            } finally {
                this.saving = false;
            }
        },

        async deleteUser(user) {
            if (!confirm(`Delete ${user.name}? This cannot be undone.`)) return;
            try {
                await apiRequest('DELETE', `/api/admin/users/${user.id}`);
                await this.fetchUsers();
            } catch (e) {
                alert('Could not delete user.');
            }
        },
    };
}
</script>
@endsection
