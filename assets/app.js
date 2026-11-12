/* ============================================================
   APP.JS — JavaScript utama (sidebar, clock, accordion, dll)
   ============================================================ */

/* ============================================================
   1. SIDEBAR TOGGLE
   ============================================================ */
(function () {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const closeBtn = document.getElementById('sidebarCloseBtn');
  if (!toggle || !sidebar || !overlay) return;

  const mobileQuery = window.matchMedia('(max-width: 768px)');
  const tabletQuery = window.matchMedia('(max-width: 1024px)');

  function openMobileSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('show');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('sidebar-mobile-open');
    document.body.style.overflow = 'hidden';
  }
  function closeMobileSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('sidebar-mobile-open');
    document.body.style.overflow = '';
  }
  function toggleCollapsed() {
    const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
    toggle.setAttribute('aria-expanded', String(!isCollapsed));
  }
  function toggleSidebar() {
    if (mobileQuery.matches) {
      sidebar.classList.contains('open') ? closeMobileSidebar() : openMobileSidebar();
    } else {
      toggleCollapsed();
    }
  }

  toggle.addEventListener('click', toggleSidebar);
  overlay.addEventListener('click', closeMobileSidebar);
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      if (mobileQuery.matches) closeMobileSidebar();
      else { document.body.classList.add('sidebar-collapsed'); toggle.setAttribute('aria-expanded', 'false'); }
    });
  }
  sidebar.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => { if (mobileQuery.matches) closeMobileSidebar(); });
  });

  function handleBreakpointChange() {
    closeMobileSidebar();
    document.body.classList.remove('sidebar-collapsed');
    toggle.setAttribute('aria-expanded', 'false');
  }
  if (mobileQuery.addEventListener) {
    mobileQuery.addEventListener('change', handleBreakpointChange);
    tabletQuery.addEventListener('change', handleBreakpointChange);
  } else {
    mobileQuery.addListener(handleBreakpointChange);
    tabletQuery.addListener(handleBreakpointChange);
  }

  window.addEventListener('keydown', e => { if (e.key === 'Escape') closeMobileSidebar(); });
})();

/* ============================================================
   2. CART TOGGLE (KASIR)
   ============================================================ */
(function () {
  const cartToggleBtn = document.getElementById('cartToggleBtn');
  if (!cartToggleBtn) return;
  cartToggleBtn.addEventListener('click', function (e) {
    e.preventDefault();
    document.body.classList.toggle('cart-open');
    cartToggleBtn.setAttribute('aria-expanded',
      String(document.body.classList.contains('cart-open')));
  });
  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.body.classList.remove('cart-open');
      cartToggleBtn.setAttribute('aria-expanded', 'false');
    }
  });
})();

/* ============================================================
   3. JAM REAL-TIME
   ============================================================ */
(function () {
  const bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const el = document.getElementById('liveClockText');
  if (!el) return;
  function updateClock() {
    const now = new Date();
    const d = String(now.getDate()).padStart(2, '0');
    const b = bulan[now.getMonth()];
    const y = now.getFullYear();
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    const ss = String(now.getSeconds()).padStart(2, '0');
    el.textContent = `${d} ${b} ${y} ${hh}:${mm}:${ss}`;
  }
  updateClock();
  setInterval(updateClock, 1000);
})();

/* ============================================================
   4. ACCORDION (DETAIL EXPAND)
   ============================================================ */
function initAccordion(selector) {
  const closeDetail = (inner) => {
    inner.classList.remove('open');
    const id = inner.id.replace('inner-', '');
    document.querySelector(`[data-target="${id}"]`)?.classList.remove('active');
    document.getElementById('icon-' + id)?.classList.remove('open');
  };

  document.querySelectorAll(selector).forEach(row => {
    row.addEventListener('click', function () {
      const targetId = this.dataset.target;
      const inner = document.getElementById('inner-' + targetId);
      if (!inner) return;

      const isOpen = inner.classList.contains('open');

      document.querySelectorAll('.detail-inner.open').forEach(el => {
        if (el !== inner) closeDetail(el);
      });

      if (isOpen) {
        closeDetail(inner);
      } else {
        inner.classList.add('open');
        this.classList.add('active');
        document.getElementById('icon-' + targetId)?.classList.add('open');
        setTimeout(() => this.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 350);
      }
    });
  });
}

// Auto-init accordion saat halaman load
document.addEventListener('DOMContentLoaded', function() {
  initAccordion('.row-konsumen');
  initAccordion('.row-transaksi');
});

/* ============================================================
   5. FORMAT RUPIAH INPUT
   ============================================================ */
function formatRupiahInput(el) {
  let v = el.value.replace(/[^\d]/g, '');
  if (v === '') { el.value = ''; return; }
  el.value = new Intl.NumberFormat('id-ID').format(parseInt(v, 10));
}

window.formatRupiahInput = formatRupiahInput;

/* ============================================================
   6. MODAL HELPERS (Global)
   ============================================================ */
function tutupModal(id) {
  document.getElementById(id)?.classList.remove('show');
}
window.tutupModal = tutupModal;

// Tutup modal saat klik overlay
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) {
      this.classList.remove('show');
    }
  });
});

// Tutup modal dengan ESC
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.show').forEach(el => el.classList.remove('show'));
  }
});