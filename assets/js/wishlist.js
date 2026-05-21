// ============================================================
// Luna Glow — Wishlist JavaScript
// ============================================================

async function toggleWishlist(productId, btn) {
  const icon = btn.querySelector('i');
  const isActive = btn.classList.contains('active');

  btn.disabled = true;

  try {
    const res  = await fetch('/xampp/Project/LunaGlow/api/wishlist.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ product_id: productId, action: isActive ? 'remove' : 'add' }),
    });
    const data = await res.json();

    if (data.success) {
      btn.classList.toggle('active', !isActive);
      icon.className = isActive ? 'fa-regular fa-heart' : 'fa-solid fa-heart';
      showToast(isActive ? 'Removed from wishlist.' : 'Added to wishlist! ♥', 'success');
      updateWishlistBadge(data.count);
    } else if (data.redirect) {
      window.location.href = data.redirect;
    } else {
      showToast(data.message || 'Could not update wishlist.', 'error');
    }
  } catch {
    showToast('Network error. Please try again.', 'error');
  }

  btn.disabled = false;
}

function updateWishlistBadge(count) {
  const badge = document.querySelector('[aria-label="Wishlist"] .nav-badge');
  if (!badge) return;
  if (count > 0) { badge.textContent = count; badge.style.display = 'flex'; }
  else badge.style.display = 'none';
}
