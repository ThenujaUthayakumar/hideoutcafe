<?php
/**
 * Layout Footer & Global Modals
 */
$user = currentUser();
$currentShift = getActiveCashRegister($user['id'] ?? 0);
?>
    </div><!-- End flex-1 container -->

    <!-- ============================================== -->
    <!-- SHIFT CASH REGISTER MODAL                      -->
    <!-- ============================================== -->
    <div id="shift-register-modal" class="modal-overlay fixed inset-0 bg-stone-900/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
            <div class="flex items-center justify-between pb-4 border-b border-stone-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-vault text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-stone-900 text-lg">Cash Register Shift</h3>
                        <p class="text-xs text-stone-500">Cashier: <?= e($user['name']) ?></p>
                    </div>
                </div>
                <button onclick="closeModal('shift-register-modal')" class="text-stone-400 hover:text-stone-600 p-2 rounded-xl">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="mt-5">
                <?php if ($currentShift): ?>
                    <!-- Shift Closing Form -->
                    <div class="p-4 bg-amber-50/70 border border-amber-200 rounded-2xl mb-4 space-y-2">
                        <div class="flex justify-between text-xs font-semibold text-stone-600">
                            <span>Opening Float:</span>
                            <span class="font-bold text-stone-900">$<?= number_format($currentShift['opening_cash'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-xs font-semibold text-stone-600">
                            <span>Cash Sales This Shift:</span>
                            <span class="font-bold text-emerald-700">+$<?= number_format($currentShift['total_cash_sales'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-xs font-semibold text-stone-600">
                            <span>Card Sales This Shift:</span>
                            <span class="font-bold text-sky-700">$<?= number_format($currentShift['total_card_sales'], 2) ?></span>
                        </div>
                        <div class="border-t border-amber-200 pt-2 flex justify-between text-sm font-black text-stone-900">
                            <span>Expected Cash in Drawer:</span>
                            <span class="text-amber-900">$<?= number_format($currentShift['opening_cash'] + $currentShift['total_cash_sales'], 2) ?></span>
                        </div>
                    </div>

                    <form id="close-shift-form" onsubmit="handleCloseShift(event)" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-700 mb-1">Counted Closing Cash ($) *</label>
                            <input type="number" step="0.01" min="0" id="close_cash_input" required class="w-full px-4 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:ring-2 focus:ring-amber-700 focus:bg-white text-base font-bold text-stone-900" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-700 mb-1">Shift Closing Notes (Optional)</label>
                            <textarea id="close_notes_input" rows="2" class="w-full px-4 py-2 bg-stone-50 border border-stone-200 rounded-xl focus:ring-2 focus:ring-amber-700 text-xs" placeholder="Discrepancy reason, tips, etc."></textarea>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" onclick="closeModal('shift-register-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs transition">Cancel</button>
                            <button type="submit" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-md transition">Close Shift & Reconcile</button>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- Shift Opening Form -->
                    <form id="open-shift-form" onsubmit="handleOpenShift(event)" class="space-y-4">
                        <div class="p-3.5 bg-stone-50 border border-stone-200 rounded-2xl text-xs text-stone-600">
                            <p class="font-bold text-stone-800 mb-1"><i class="fa-solid fa-circle-info text-amber-700 mr-1"></i> Register is Closed</p>
                            Enter the opening cash float in the drawer to begin making sales.
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-700 mb-1">Opening Cash Float ($) *</label>
                            <input type="number" step="0.01" min="0" id="open_cash_input" value="100.00" required class="w-full px-4 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:ring-2 focus:ring-amber-700 focus:bg-white text-base font-bold text-stone-900">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-700 mb-1">Notes (Optional)</label>
                            <input type="text" id="open_notes_input" class="w-full px-4 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs" placeholder="e.g. Morning Opening Float">
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" onclick="closeModal('shift-register-modal')" class="flex-1 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs transition">Cancel</button>
                            <button type="submit" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition">Open Register Shift</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    <script>
      // Flash notifications from PHP session
      <?php if ($success = getFlash('success')): ?>
        Toast.success("<?= addslashes($success) ?>");
      <?php endif; ?>
      <?php if ($error = getFlash('error')): ?>
        Toast.error("<?= addslashes($error) ?>");
      <?php endif; ?>

      // Mobile sidebar toggle
      const sidebarToggle = document.getElementById('sidebar-toggle-btn');
      const sidebar = document.getElementById('app-sidebar');
      if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
          sidebar.classList.toggle('hidden');
        });
      }

      // Drawer AJAX Handlers
      async function handleOpenShift(e) {
        e.preventDefault();
        const cash = document.getElementById('open_cash_input').value;
        const notes = document.getElementById('open_notes_input').value;
        
        const fd = new FormData();
        fd.append('action', 'open_shift');
        fd.append('opening_cash', cash);
        fd.append('notes', notes);

        const res = await fetch('<?= BASE_URL ?>/api/drawer_action.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          Toast.error(data.message || 'Error opening shift');
        }
      }

      async function handleCloseShift(e) {
        e.preventDefault();
        const cash = document.getElementById('close_cash_input').value;
        const notes = document.getElementById('close_notes_input').value;
        
        const fd = new FormData();
        fd.append('action', 'close_shift');
        fd.append('closing_cash', cash);
        fd.append('notes', notes);

        const res = await fetch('<?= BASE_URL ?>/api/drawer_action.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          Swal.fire({
            title: 'Shift Closed!',
            html: `Counted: $${parseFloat(data.summary.counted).toFixed(2)}<br>Expected: $${parseFloat(data.summary.expected).toFixed(2)}<br>Difference: <b>$${parseFloat(data.summary.difference).toFixed(2)}</b>`,
            icon: 'info',
            confirmButtonColor: '#8b5a2b'
          }).then(() => window.location.reload());
        } else {
          Toast.error(data.message || 'Error closing shift');
        }
      }
    </script>
</body>
</html>
