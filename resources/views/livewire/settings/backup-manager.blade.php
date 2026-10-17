<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Backup & Restore Subsystem</h1>
            <p class="text-xs text-slate-500 mt-0.5">Automated AES-256-GCM encrypted database & file backups, cryptographic verification, and rollback-protected atomic restoration (Revision 24 Section 74-81)</p>
        </div>
        <div>
            <button type="button" wire:click="openCreateModal" id="btn_create_backup" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Create Encrypted Backup Now</span>
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Automatic Backup Schedule Card (Section 74) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl mt-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900">Automated Daily Backup Schedule</h3>
                <p class="text-xs text-slate-500 mt-0.5">Section 74: Automatically dumps all tables and documents into a timestamped encrypted container</p>
            </div>
        </div>
        <form wire:submit.prevent="saveSchedule" class="flex items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                <input type="checkbox" wire:model="autoEnabled" class="rounded border-slate-300 text-blue-600">
                <span>Enabled</span>
            </label>
            <div class="flex items-center gap-1.5">
                <span class="text-xs text-slate-500 font-medium">Time:</span>
                <input type="time" wire:model="scheduleTime" class="py-1 px-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
            </div>
            <button type="submit" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs border border-slate-200 transition">
                Save Schedule
            </button>
        </form>
    </div>

    <!-- Backup History Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900">Backup Archive History</h3>
            <span class="text-xs text-slate-400">{{ $backups->count() }} total {{ Str::plural('archive', $backups->count()) }}</span>
        </div>
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider text-[10px] font-bold">
                    <th class="py-3 px-4">Date / Time</th>
                    <th class="py-3 px-4">Archive Filename (.ncbk)</th>
                    <th class="py-3 px-4">Size</th>
                    <th class="py-3 px-4">Type</th>
                    <th class="py-3 px-4">SHA-256 Checksum</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @forelse($backups as $b)
                    <tr class="hover:bg-slate-50/70 transition" wire:key="backup-{{ $b->id }}">
                        <td class="py-3 px-4 whitespace-nowrap">
                            <div class="font-bold text-slate-900">{{ $b->created_at->format('d M Y, H:i') }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $b->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="py-3 px-4 font-mono font-bold text-slate-800 text-[11px]">
                            {{ basename($b->file_path) }}
                        </td>
                        <td class="py-3 px-4 font-mono font-bold text-slate-600 text-xs">
                            {{ number_format($b->size_bytes / 1024, 1) }} KB
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $b->backup_type === 'MANUAL' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $b->backup_type }}
                            </span>
                        </td>
                        <td class="py-3 px-4 font-mono text-[10px] text-slate-500">
                            {{ substr($b->checksum_sha256, 0, 16) }}...
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $b->status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $b->status }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                            <button type="button" wire:click="downloadBackup({{ $b->id }})" title="Download encrypted .ncbk archive" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-lg text-[11px] border border-blue-200">
                                ⬇ Archive (.ncbk)
                            </button>
                            <button type="button" wire:click="downloadMetadata({{ $b->id }})" title="Download SHA-256 metadata manifest" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-[11px] border border-slate-200">
                                📋 Manifest (.json)
                            </button>
                            <button type="button" wire:click="openVerifyModal({{ $b->id }})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-[11px]">
                                Verify
                            </button>
                            <button type="button" wire:click="openRestoreModal({{ $b->id }})" class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold rounded-lg text-[11px] border border-amber-200">
                                Restore
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                            No backup archives found. Click "Create Encrypted Backup Now" to create one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Create Backup Modal (Section 76-77) -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Create Encrypted Backup</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="createBackup" class="space-y-3 text-xs">
                    <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-200 text-blue-900 text-[11px] leading-relaxed">
                        <strong>Security Rule (Section 77):</strong> Enter an encryption password. Container is encrypted with <strong>AES-256-GCM + PBKDF2</strong>. dxclinic never stores plaintext backup passwords.
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Backup Password * (Min 8 chars)</label>
                        <input type="password" wire:model="backupPassword" placeholder="Enter secure password" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('backupPassword') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Confirm Backup Password *</label>
                        <input type="password" wire:model="backupPasswordConfirmation" placeholder="Re-enter password" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('backupPasswordConfirmation') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Local Drive / Target Directory (Configurable)</label>
                        <input type="text" wire:model="customBackupFolder" placeholder="e.g. D:\DxClinic_Backups or default storage path" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono text-[11px]">
                        <p class="text-[10px] text-slate-400 mt-1">Directory on server or local attached storage. Also generates companion <code class="font-mono">.meta.json</code>.</p>
                        @error('customBackupFolder') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" id="btn_confirm_create_backup" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Generate Backup Archive</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Verify Backup Modal -->
    @if($showVerifyModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Verify Backup Integrity</h3>
                    <button type="button" wire:click="$set('showVerifyModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="verifyBackup" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Backup Password *</label>
                        <input type="password" wire:model="verifyPassword" placeholder="Enter backup encryption password" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('verifyPassword') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    @if($verifyResult)
                        <div class="p-3 rounded-xl border text-xs {{ $verifyResult['valid'] ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900' }}">
                            <div class="font-bold mb-1">Verification Status: {{ $verifyResult['valid'] ? 'PASSED (VERIFIED)' : 'FAILED' }}</div>
                            @if(!empty($verifyResult['manifest']))
                                <div class="text-[11px] font-mono space-y-0.5">
                                    <div>Schema: {{ $verifyResult['manifest']['schema_version'] ?? 'N/A' }}</div>
                                    <div>Tables: {{ count($verifyResult['manifest']['tables'] ?? []) }} dumped</div>
                                    <div>Files: {{ count($verifyResult['manifest']['files'] ?? []) }} packaged</div>
                                </div>
                            @endif
                            @if(!empty($verifyResult['error']))
                                <div class="text-[11px] text-rose-700 mt-1">{{ $verifyResult['error'] }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showVerifyModal', false)" class="px-4 py-2 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Close</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold">Decrypt & Verify</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Restore Backup Modal (Section 80) -->
    @if($showRestoreModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg p-6 animate-in fade-in">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-rose-700 flex items-center gap-1.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span>Restore Database from Backup</span>
                    </h3>
                    <button type="button" wire:click="$set('showRestoreModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit.prevent="restoreBackup" class="space-y-4 text-xs">
                    <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-900 text-[11px] leading-relaxed space-y-1">
                        <div class="font-bold">⚠️ High-Risk Action (Section 80):</div>
                        <div>Restoring replaces current database tables with data from the backup archive. The system automatically creates a pre-restore safety snapshot before executing, and immediately rolls back if verification fails.</div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Backup Encryption Password *</label>
                        <input type="password" wire:model="restorePassword" placeholder="Enter password used to encrypt this backup" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono">
                        @error('restorePassword') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Confirmation: Type <code class="text-rose-600 font-bold">RESTORE</code> below *</label>
                        <input type="text" wire:model="restoreConfirmText" placeholder="RESTORE" class="w-full py-2 px-3 border border-slate-300 rounded-xl font-mono font-bold uppercase text-rose-700">
                        @error('restoreConfirmText') <span class="text-rose-600 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showRestoreModal', false)" class="px-4 py-2 border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" id="btn_confirm_restore" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold">Execute Protected Restoration</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
