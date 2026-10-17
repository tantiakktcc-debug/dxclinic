<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Users & Operator Management</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage operator and doctor accounts, reset credentials, and audit security actions (Revision 24 Section 65-67)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="openChangeOwnPasswordModal" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition">
                🔑 Change My Password
            </button>
            <button type="button" wire:click="openCreateModal" id="btn_add_user" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Add User / Operator</span>
            </button>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="flex flex-col sm:flex-row items-center gap-3 bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="relative flex-1 w-full">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, email, or mobile..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-xl">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <div class="w-full sm:w-48">
            <select wire:model.live="roleFilter" class="w-full py-2 px-3 text-xs border border-slate-300 rounded-xl bg-white font-medium">
                <option value="ALL">All Roles</option>
                <option value="ADMIN">Administrators</option>
                <option value="OPERATOR">Operators</option>
                <option value="DOCTOR">Doctors</option>
            </select>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[10px] font-bold">
                    <th class="py-3 px-4">User Identity</th>
                    <th class="py-3 px-4">Role</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Last Login</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @forelse($users as $user)
                    <tr class="hover:bg-slate-50/70 transition" wire:key="user-{{ $user->id }}">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">{{ $user->name }}</span>
                                @if($user->login_id)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        ID: {{ $user->login_id }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-500 font-mono">{{ $user->email }} @if($user->phone)&bull; 📞 {{ $user->phone }}@endif</div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $user->isAdmin() ? 'bg-purple-100 text-purple-700' : ($user->isOperator() ? 'bg-teal-100 text-teal-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ $user->role }}
                            </span>
                            @if($user->isDoctor() && $user->doctor)
                                <div class="text-[11px] font-bold text-indigo-700 mt-1">👨‍⚕️ {{ $user->doctor->display_name }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $user->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                {{ $user->status }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-500 text-[11px]">
                            {{ $user->last_login_at ? $user->last_login_at->format('d M Y, H:i') : 'Never' }}
                        </td>
                        <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                            <button type="button" wire:click="openEditModal({{ $user->id }})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-[11px]">
                                Edit
                            </button>
                            <button type="button" wire:click="openResetPasswordModal({{ $user->id }})" class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold rounded-lg text-[11px] border border-amber-200">
                                Reset Password
                            </button>
                            @if($user->id !== auth()->id())
                                <button type="button" wire:click="toggleUserStatus({{ $user->id }})" class="px-2.5 py-1 {{ $user->isActive() ? 'text-rose-700 bg-rose-50 hover:bg-rose-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }} font-bold rounded-lg text-[11px]">
                                    {{ $user->isActive() ? 'Deactivate' : 'Activate' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                            No users found matching current filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Create User Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Create New User Account</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="createUser" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Full Name *</label>
                        <input type="text" wire:model="newName" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('newName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">User Login ID * (e.g. OPR01)</label>
                        <input type="text" wire:model="newLoginId" class="w-full p-2 border border-slate-300 rounded-lg font-mono uppercase font-bold text-blue-900" placeholder="Auto-suggested">
                        @error('newLoginId') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Email Address *</label>
                        <input type="email" wire:model="newEmail" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('newEmail') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mobile Number</label>
                        <input type="tel" wire:model="newPhone" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Role *</label>
                        <select wire:model.live="newRole" class="w-full p-2 border border-slate-300 rounded-lg bg-white font-medium">
                            <option value="OPERATOR">OPERATOR (Reception & Billing)</option>
                            <option value="DOCTOR">DOCTOR (Clinical OPD)</option>
                            <option value="ADMIN">ADMIN (Full Authority)</option>
                        </select>
                    </div>
                    @if($newRole === 'DOCTOR')
                        <div class="bg-blue-50/60 p-2.5 rounded-xl border border-blue-200">
                            <label class="block font-bold text-blue-900 mb-1 text-[11px] uppercase tracking-wider">Bound Doctor Profile *</label>
                            <select wire:model="newDoctorId" class="w-full p-2 border border-blue-300 bg-white rounded-lg text-xs font-semibold text-blue-900">
                                <option value="">Select Doctor Profile...</option>
                                @foreach($doctors as $doc)
                                    <option value="{{ $doc->id }}">👨‍⚕️ {{ $doc->display_name }} ({{ $doc->specialization ?? 'General' }})</option>
                                @endforeach
                            </select>
                            @error('newDoctorId') <span class="text-rose-600 text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Password * (Min 8 chars)</label>
                        <input type="password" wire:model="newPassword" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('newPassword') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Confirm Password *</label>
                        <input type="password" wire:model="newPasswordConfirmation" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit User Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Edit User Profile</h3>
                    <button type="button" wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="updateUser" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Full Name *</label>
                        <input type="text" wire:model="editName" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('editName') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">User Login ID *</label>
                        <input type="text" wire:model="editLoginId" class="w-full p-2 border border-slate-300 rounded-lg font-mono uppercase font-bold text-blue-900">
                        @error('editLoginId') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Email Address *</label>
                        <input type="email" wire:model="editEmail" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('editEmail') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mobile Number</label>
                        <input type="tel" wire:model="editPhone" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Role *</label>
                        <select wire:model.live="editRole" class="w-full p-2 border border-slate-300 rounded-lg bg-white font-medium">
                            <option value="OPERATOR">OPERATOR</option>
                            <option value="DOCTOR">DOCTOR</option>
                            <option value="ADMIN">ADMIN</option>
                        </select>
                    </div>
                    @if($editRole === 'DOCTOR')
                        <div class="bg-blue-50/60 p-2.5 rounded-xl border border-blue-200">
                            <label class="block font-bold text-blue-900 mb-1 text-[11px] uppercase tracking-wider">Bound Doctor Profile *</label>
                            <select wire:model="editDoctorId" class="w-full p-2 border border-blue-300 bg-white rounded-lg text-xs font-semibold text-blue-900">
                                <option value="">Select Doctor Profile...</option>
                                @foreach($doctors as $doc)
                                    <option value="{{ $doc->id }}">👨‍⚕️ {{ $doc->display_name }} ({{ $doc->specialization ?? 'General' }})</option>
                                @endforeach
                            </select>
                            @error('editDoctorId') <span class="text-rose-600 text-[10px] block mt-1">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Reset Password Modal (Admin) -->
    @if($showResetPasswordModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Reset Password — {{ $resetUserName }}</h3>
                    <button type="button" wire:click="$set('showResetPasswordModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="resetUserPassword" class="space-y-3 text-xs">
                    <p class="text-slate-500 text-[11px]">Enter a new secure password for this user. The reset action is strictly recorded in the audit log.</p>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">New Password * (Min 8 chars)</label>
                        <input type="password" wire:model="resetPassword" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('resetPassword') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Confirm New Password *</label>
                        <input type="password" wire:model="resetPasswordConfirmation" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showResetPasswordModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold shadow">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Change Own Password Modal -->
    @if($showChangeOwnPasswordModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Change Your Password</h3>
                    <button type="button" wire:click="$set('showChangeOwnPasswordModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="changeOwnPassword" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Current Password *</label>
                        <input type="password" wire:model="currentPassword" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('currentPassword') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">New Password * (Min 8 chars)</label>
                        <input type="password" wire:model="ownNewPassword" class="w-full p-2 border border-slate-300 rounded-lg">
                        @error('ownNewPassword') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Confirm New Password *</label>
                        <input type="password" wire:model="ownNewPasswordConfirmation" class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showChangeOwnPasswordModal', false)" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
