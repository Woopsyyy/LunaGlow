// ============================================================
// Luna Glow — Main JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  initNavbar();
  initSearch();
  initReveal();
  autoHideFlash();
  initMobileMenu();
});

// ── Navbar Scroll ──────────────────────────────────────────
function initNavbar() {
  const navbar = document.getElementById('navbar');
  if (!navbar) return;
  const onScroll = () => navbar.classList.toggle('scrolled', window.scrollY > 40);
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

// ── Mobile Menu ────────────────────────────────────────────
function initMobileMenu() {
  const hamburger = document.getElementById('hamburger');
  const navLinks  = document.getElementById('navLinks');
  const overlay   = document.getElementById('mobileOverlay');
  if (!hamburger) return;

  hamburger.addEventListener('click', () => {
    const open = navLinks.classList.toggle('open');
    hamburger.classList.toggle('open', open);
    overlay.classList.toggle('active', open);
    document.body.style.overflow = open ? 'hidden' : '';
  });

  overlay.addEventListener('click', closeMobileMenu);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMobileMenu(); });
}

function closeMobileMenu() {
  document.getElementById('navLinks')?.classList.remove('open');
  document.getElementById('hamburger')?.classList.remove('open');
  document.getElementById('mobileOverlay')?.classList.remove('active');
  document.body.style.overflow = '';
}

// ── Cart Toggle ────────────────────────────────────────────
const cartToggle  = document.getElementById('cartToggle');
const cartDrawer  = document.getElementById('cartDrawer');
const cartOverlay = document.getElementById('cartOverlay');

if (cartToggle) cartToggle.addEventListener('click', openCart);

function openCart() {
  cartDrawer?.classList.add('open');
  cartOverlay?.classList.add('active');
  document.body.style.overflow = 'hidden';
  loadCartDrawer();
}

function closeCart() {
  cartDrawer?.classList.remove('open');
  cartOverlay?.classList.remove('active');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCart(); });

// ── Live Search ────────────────────────────────────────────
function initSearch() {
  const toggle   = document.getElementById('searchToggle');
  const bar      = document.getElementById('searchBar');
  const input    = document.getElementById('searchInput');
  const closeBtn = document.getElementById('searchClose');
  const results  = document.getElementById('searchResults');
  if (!toggle) return;

  toggle.addEventListener('click', () => {
    bar.classList.toggle('open');
    if (bar.classList.contains('open')) setTimeout(() => input.focus(), 200);
  });

  closeBtn?.addEventListener('click', () => {
    bar.classList.remove('open');
    input.value = '';
    results.innerHTML = '';
  });

  let searchTimer;
  input?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = input.value.trim();
    if (!q) { results.innerHTML = ''; return; }
    searchTimer = setTimeout(() => fetchSearch(q), 320);
  });
}

async function fetchSearch(q) {
  const results = document.getElementById('searchResults');
  try {
    const res  = await fetch(`/xampp/Project/LunaGlow/api/search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    if (!data.length) { results.innerHTML = '<p style="font-size:.84rem;color:var(--text-muted);padding:8px 14px;">No products found.</p>'; return; }
    results.innerHTML = data.map(p => `
      <a href="/xampp/Project/LunaGlow/product.php?id=${p.id}" class="search-item">
        <img src="${p.image}" alt="${escHtml(p.name)}">
        <div>
          <div class="search-item-name">${escHtml(p.name)}</div>
          <div class="search-item-price">&#8369;${parseFloat(p.price).toFixed(2)}</div>
        </div>
      </a>
    `).join('');
  } catch { results.innerHTML = ''; }
}

// ── Scroll Reveal ──────────────────────────────────────────
function initReveal() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
  }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
  els.forEach(el => observer.observe(el));
}

// ── Flash Auto-hide ────────────────────────────────────────
function autoHideFlash() {
  const flash = document.getElementById('flashMsg');
  if (flash) setTimeout(() => flash.remove(), 5000);
}

// ── Cart Badge Update ──────────────────────────────────────
function updateCartBadge(count) {
  const badge = document.getElementById('cartBadge');
  if (!badge) return;
  badge.textContent = count;
  badge.style.display = count > 0 ? 'flex' : 'none';
}

// ── Toast Notification ─────────────────────────────────────
function showToast(message, type = 'success') {
  const el = document.createElement('div');
  el.className = `flash-message flash-${type}`;
  el.id = 'dynamicToast';
  el.style.cssText = 'position:fixed;top:calc(var(--navbar-h) + 16px);right:20px;z-index:9999;';
  el.innerHTML = `
    <i class="fa-solid fa-${type === 'success' ? 'circle-check' : 'circle-xmark'}"></i>
    <span>${escHtml(message)}</span>
    <button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
  `;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

// ── HTML Escape ────────────────────────────────────────────
function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

// ── Dropdown hover for desktop ─────────────────────────────
document.querySelectorAll('.nav-dropdown').forEach(d => {
  if (window.innerWidth <= 900) {
    d.querySelector('a')?.addEventListener('click', e => {
      e.preventDefault();
      d.classList.toggle('open');
    });
  }
});
