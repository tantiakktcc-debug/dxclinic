/**
 * DxClinic Universal Keyboard-First Operation Engine (Revision 39)
 * Empowers keyboard-only daily operation across Admin, Operator, Doctor, Superadmin, and PWA portals.
 * Shortcuts: Function Keys (F1-F9), Alt Navigation (Alt+D/Q/B/C/P/E/S/H), Action Keys (Ctrl+Enter, Esc, ?)
 */
(function () {
    'use strict';

    const SHORTCUTS = [
        { key: 'F1', desc: 'Open Keyboard Shortcuts Cheatsheet', category: 'General' },
        { key: 'F2', desc: 'New Patient / New OPD Booking', category: 'Operator / Reception' },
        { key: 'F3 / F4', desc: 'Focus Quick Search (Patient / Token / Queue)', category: 'Operator / Reception' },
        { key: 'F7', desc: 'Print Token / Receipt / Prescription', category: 'Operator / Reception' },
        { key: 'F8', desc: 'Submit Booking / Allocate Token / Complete Payment', category: 'Operator / Reception' },
        { key: 'F9', desc: 'Complete Consultation / Save Clinical Note', category: 'Doctor' },
        { key: 'Alt + D', desc: 'Navigate to Fleet / Main Dashboard', category: 'Navigation' },
        { key: 'Alt + Q', desc: 'Navigate to OPD Arrival Queue', category: 'Navigation' },
        { key: 'Alt + B', desc: 'Navigate to Operator Booking (or PWA Booking)', category: 'Navigation' },
        { key: 'Alt + P', desc: 'Navigate to Patient Records & History', category: 'Navigation' },
        { key: 'Alt + L', desc: 'Navigate to Diagnostic Lab Worklist', category: 'Navigation' },
        { key: 'Alt + C', desc: 'Navigate to Cash Session / Counter Manager', category: 'Navigation' },
        { key: 'Alt + E', desc: 'Navigate to Expense Management', category: 'Navigation' },
        { key: 'Alt + S', desc: 'Navigate to Clinic Settings', category: 'Navigation' },
        { key: 'Alt + H', desc: 'Open Documentation & User Manual', category: 'Navigation' },
        { key: 'Alt + M', desc: 'View Professional Role-Based Manual (HTML)', category: 'Navigation' },
        { key: 'Ctrl + Enter', desc: 'Submit Active Form / Confirm Action', category: 'General' },
        { key: 'Escape', desc: 'Close Active Modal / Dropdown / Cancel', category: 'General' },
        { key: '?', desc: 'Toggle Keyboard Shortcuts (when not typing)', category: 'General' },
    ];

    // Build and inject modal HTML if not already in document
    function injectShortcutsModal() {
        if (document.getElementById('dx-shortcuts-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'dx-shortcuts-modal';
        modal.className = 'dx-modal-backdrop';
        modal.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,0.75);backdrop-filter:blur(4px);z-index:99999;display:none;align-items:center;justify-content:center;padding:1rem;';
        modal.innerHTML = `
            <div class="dx-modal-card" style="background:#0f172a;color:#f8fafc;border:1px solid #334155;border-radius:1rem;max-width:680px;width:100%;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
                    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #1e293b;display:flex;align-items:center;justify-content:space-between;background:#1e293b/50;">
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <div style="background:#0284c7;color:#fff;border-radius:0.5rem;padding:0.4rem 0.6rem;font-weight:900;font-size:0.9rem;">⌨</div>
                            <div>
                                <h3 style="font-size:1.15rem;font-weight:800;letter-spacing:-0.02em;margin:0;">Keyboard Shortcuts & Operation</h3>
                                <p style="font-size:0.75rem;color:#94a3b8;margin:0.2rem 0 0 0;">Keyboard-first workflow for uninterrupted daily clinic throughput</p>
                            </div>
                        </div>
                        <button type="button" id="dx-modal-close-btn" style="background:transparent;border:none;color:#94a3b8;font-size:1.5rem;cursor:pointer;line-height:1;padding:0.25rem 0.5rem;">&times;</button>
                    </div>
                    
                    <div style="padding:1rem 1.5rem;border-bottom:1px solid #1e293b;background:#0b0f19;">
                        <input type="text" id="dx-shortcuts-search" placeholder="Type to filter shortcuts (e.g. queue, booking, print)..." style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:0.5rem;padding:0.6rem 0.9rem;color:#f8fafc;font-size:0.85rem;box-sizing:border-box;">
                    </div>

                    <div id="dx-shortcuts-list" style="padding:1rem 1.5rem;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:0.5rem;">
                        ${SHORTCUTS.map(s => `
                            <div class="dx-shortcut-row" data-search="${(s.key + ' ' + s.desc + ' ' + s.category).toLowerCase()}" style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0.85rem;border-radius:0.5rem;background:#131d2e;border:1px solid #1e293b;">
                                <div style="display:flex;align-items:center;gap:0.75rem;">
                                    <span style="font-size:0.7rem;text-transform:uppercase;font-weight:700;color:#38bdf8;background:rgba(56,189,248,0.1);padding:0.15rem 0.5rem;border-radius:0.3rem;border:1px solid rgba(56,189,248,0.2);min-width:70px;text-align:center;">${s.category}</span>
                                    <span style="font-size:0.85rem;color:#e2e8f0;font-weight:500;">${s.desc}</span>
                                </div>
                                <kbd style="background:#1e293b;border:1px solid #475569;box-shadow:0 2px 0 #334155;color:#f8fafc;padding:0.25rem 0.65rem;border-radius:0.4rem;font-family:monospace;font-size:0.8rem;font-weight:700;white-space:nowrap;">${s.key}</kbd>
                            </div>
                        `).join('')}
                    </div>

                    <div style="padding:0.85rem 1.5rem;border-top:1px solid #1e293b;background:#0b0f19;display:flex;align-items:center;justify-content:space-between;font-size:0.75rem;color:#64748b;">
                        <span>Press <kbd style="background:#1e293b;padding:0.15rem 0.35rem;border-radius:0.25rem;border:1px solid #334155;">Esc</kbd> or <kbd style="background:#1e293b;padding:0.15rem 0.35rem;border-radius:0.25rem;border:1px solid #334155;">F1</kbd> to close</span>
                        <a href="/manuals/user_manual.html" target="_blank" style="color:#38bdf8;text-decoration:none;font-weight:600;">📖 Open Full User Manual &rarr;</a>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        document.getElementById('dx-modal-close-btn').addEventListener('click', toggleShortcutsModal);
        modal.addEventListener('click', function (e) {
            if (e.target === this) toggleShortcutsModal();
        });

        const searchInput = document.getElementById('dx-shortcuts-search');
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            const rows = modal.querySelectorAll('.dx-shortcut-row');
            rows.forEach(r => {
                const match = !q || r.dataset.search.includes(q);
                r.style.display = match ? 'flex' : 'none';
            });
        });
    }

    function toggleShortcutsModal() {
        injectShortcutsModal();
        const modal = document.getElementById('dx-shortcuts-modal');
        if (!modal) return;
        const isHidden = modal.style.display === 'none' || !modal.style.display;
        modal.style.display = isHidden ? 'flex' : 'none';
        if (isHidden) {
            const search = document.getElementById('dx-shortcuts-search');
            if (search) {
                search.value = '';
                search.dispatchEvent(new Event('input'));
                setTimeout(() => search.focus(), 50);
            }
        }
    }

    function isInputActive() {
        const active = document.activeElement;
        if (!active) return false;
        const tag = active.tagName.toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        if (active.isContentEditable) return true;
        return false;
    }

    // Main Keyboard Dispatcher
    window.addEventListener('keydown', function (e) {
        // 1. F1: Help / Shortcuts modal (always intercepts)
        if (e.key === 'F1') {
            e.preventDefault();
            toggleShortcutsModal();
            return;
        }

        // 2. Escape: Close modal if open
        if (e.key === 'Escape') {
            const modal = document.getElementById('dx-shortcuts-modal');
            if (modal && modal.style.display !== 'none') {
                e.preventDefault();
                toggleShortcutsModal();
                return;
            }
            // Close any custom modals
            const openModals = document.querySelectorAll('.modal-open, [data-modal-open="true"]');
            openModals.forEach(m => {
                if (typeof m.close === 'function') m.close();
            });
            return;
        }

        // 3. Question mark '?' when NOT inside an input field
        if (e.key === '?' && !isInputActive()) {
            e.preventDefault();
            toggleShortcutsModal();
            return;
        }

        // 4. Ctrl + Enter: Submit active form or primary button
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            const primaryBtn = document.querySelector('#btn_operator_confirm_booking, #btn_operator_submit_booking, #btn_pwa_submit_booking, button[type="submit"]:not([disabled])');
            if (primaryBtn) {
                primaryBtn.click();
            } else if (document.activeElement && document.activeElement.form) {
                document.activeElement.form.requestSubmit();
            }
            return;
        }

        // 5. Function Keys: F2, F3, F4, F7, F8, F9
        if (e.key === 'F2') {
            e.preventDefault();
            // In Operator Booking or Arrival Queue: trigger new booking
            const walkinBtn = document.getElementById('btn_new_walkin_booking');
            if (walkinBtn) {
                walkinBtn.click();
                return;
            }
            const newPatientBtn = document.getElementById('btn_mode_new_patient');
            if (newPatientBtn) {
                newPatientBtn.click();
                setTimeout(() => {
                    const nameInput = document.getElementById('operator_patient_name');
                    if (nameInput) nameInput.focus();
                }, 100);
            } else {
                const bookAnotherBtn = document.getElementById('btn_operator_book_another');
                if (bookAnotherBtn) {
                    bookAnotherBtn.click();
                } else {
                    // Navigate to booking
                    window.location.href = '/operator/booking';
                }
            }
            return;
        }

        if (e.key === 'F3' || e.key === 'F4') {
            e.preventDefault();
            const existingBtn = document.getElementById('btn_mode_existing_patient');
            if (existingBtn) existingBtn.click();
            
            setTimeout(() => {
                const searchInput = document.getElementById('input_arrival_search')
                    || document.getElementById('operator_search_input') 
                    || document.getElementById('patient_search_input') 
                    || document.getElementById('arrival_search_input')
                    || document.querySelector('input[type="search"]')
                    || document.getElementById('pwa_patient_name');
                if (searchInput) {
                    searchInput.focus();
                    if (searchInput.select) searchInput.select();
                }
            }, 100);
            return;
        }

        if (e.key === 'F7') {
            e.preventDefault();
            const printBtn = document.getElementById('btn_operator_download_token') 
                || document.querySelector('[data-action="print-token"]')
                || document.querySelector('button[onclick*="print"]');
            if (printBtn) {
                printBtn.click();
            } else {
                window.print();
            }
            return;
        }

        if (e.key === 'F8') {
            e.preventDefault();
            const submitBtn = document.getElementById('btn_operator_confirm_booking')
                || document.getElementById('btn_operator_submit_booking') 
                || document.getElementById('btn_pwa_submit_booking')
                || document.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.click();
            return;
        }

        if (e.key === 'F9') {
            e.preventDefault();
            const completeBtn = document.getElementById('btn_complete_consultation')
                || document.querySelector('[data-action="complete-consultation"]');
            if (completeBtn) completeBtn.click();
            return;
        }

        // 6. Alt Navigation Shortcuts
        if (e.altKey && !e.ctrlKey && !e.shiftKey) {
            const k = e.key.toLowerCase();
            switch (k) {
                case 'd':
                    e.preventDefault();
                    window.location.href = '/dashboard';
                    break;
                case 'q':
                    e.preventDefault();
                    window.location.href = '/operator/queue';
                    break;
                case 'b':
                    e.preventDefault();
                    // If on PWA, keep on PWA booking; otherwise operator booking
                    if (window.location.pathname.startsWith('/book/')) {
                        const firstInput = document.getElementById('pwa_patient_name') || document.querySelector('input');
                        if (firstInput) firstInput.focus();
                    } else {
                        window.location.href = '/operator/booking';
                    }
                    break;
                case 'p':
                    e.preventDefault();
                    window.location.href = '/patients';
                    break;
                case 'l':
                    e.preventDefault();
                    window.location.href = '/lab';
                    break;
                case 'c':
                    e.preventDefault();
                    window.location.href = '/cash/sessions';
                    break;
                case 'e':
                    e.preventDefault();
                    window.location.href = '/expenses';
                    break;
                case 's':
                    e.preventDefault();
                    window.location.href = '/settings';
                    break;
                case 'h':
                    e.preventDefault();
                    window.location.href = '/help/manual';
                    break;
                case 'm':
                    e.preventDefault();
                    window.open('/manuals/user_manual.html', '_blank');
                    break;
            }
        }
    });

    // Expose helper API globally
    window.DxShortcuts = {
        openModal: toggleShortcutsModal,
        list: SHORTCUTS
    };

    // Auto-inject modal on DOM load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectShortcutsModal);
    } else {
        injectShortcutsModal();
    }
})();
