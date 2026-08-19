/**
 * Aura Cafe POS - Global Application Utilities
 */

// Audio Sound Effects via Web Audio API (Zero external assets required!)
const SoundFX = {
  ctx: null,
  init() {
    if (!this.ctx) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (AudioCtx) this.ctx = new AudioCtx();
    }
  },
  playBeep(freq = 880, type = 'sine', duration = 0.08) {
    try {
      this.init();
      if (!this.ctx) return;
      if (this.ctx.state === 'suspended') this.ctx.resume();
      
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      
      osc.type = type;
      osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
      gain.gain.setValueAtTime(0.15, this.ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration);
      
      osc.connect(gain);
      gain.connect(this.ctx.destination);
      
      osc.start();
      osc.stop(this.ctx.currentTime + duration);
    } catch (e) {
      console.warn("Audio play blocked or unavailable", e);
    }
  },
  success() {
    this.playBeep(980, 'sine', 0.08);
    setTimeout(() => this.playBeep(1320, 'sine', 0.12), 90);
  },
  error() {
    this.playBeep(320, 'sawtooth', 0.15);
  },
  click() {
    this.playBeep(650, 'sine', 0.04);
  },
  bell() {
    this.playBeep(1200, 'triangle', 0.3);
  }
};

// Toast Notifications
const Toast = {
  show(message, type = 'success') {
    const bgColors = {
      success: 'bg-emerald-600',
      error: 'bg-rose-600',
      warning: 'bg-amber-500',
      info: 'bg-sky-600'
    };
    const icons = {
      success: 'fa-check-circle',
      error: 'fa-exclamation-circle',
      warning: 'fa-triangle-exclamation',
      info: 'fa-info-circle'
    };

    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `flex items-center gap-3 px-4 py-3 text-white rounded-xl shadow-xl transition-all duration-300 transform translate-x-12 opacity-0 pointer-events-auto ${bgColors[type] || bgColors.info}`;
    toast.innerHTML = `
      <i class="fa-solid ${icons[type] || icons.info} text-lg"></i>
      <span class="text-sm font-medium">${message}</span>
    `;

    container.appendChild(toast);

    if (type === 'success') SoundFX.click();
    if (type === 'error') SoundFX.error();

    // Trigger animate-in
    setTimeout(() => {
      toast.classList.remove('translate-x-12', 'opacity-0');
    }, 20);

    // Auto dismiss after 3.5s
    setTimeout(() => {
      toast.classList.add('translate-x-12', 'opacity-0');
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  },
  success(msg) { this.show(msg, 'success'); },
  error(msg) { this.show(msg, 'error'); },
  warning(msg) { this.show(msg, 'warning'); },
  info(msg) { this.show(msg, 'info'); }
};

// Modal Open/Close Helper
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }
}

// Global SweetAlert Confirm helper
function confirmAction(title, text, confirmBtnText = 'Yes, confirm') {
  return Swal.fire({
    title: title,
    text: text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#8b5a2b',
    cancelButtonColor: '#9ca3af',
    confirmButtonText: confirmBtnText,
    customClass: {
      popup: 'rounded-2xl shadow-2xl border border-stone-200'
    }
  });
}
