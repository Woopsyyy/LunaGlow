<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin(APP_URL . '/user/settings.php');

$userId = $_SESSION['user_id'];
$user   = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Fetch addresses
$addresses = dbFetchAll("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC", [$userId]);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verify CSRF
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'CSRF token validation failed.');
        header('Location: ' . APP_URL . '/user/settings.php');
        exit;
    }

    if ($action === 'profile') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $phone = sanitize($_POST['phone'] ?? '');
        
        if (!$name || !$email) {
            setFlash('error', 'Please enter a valid name and email.');
        } else {
            // Check email uniqueness
            $emailExists = dbFetchColumn("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($emailExists) {
                setFlash('error', 'Email is already taken by another account.');
            } else {
                $avatarName = $user['avatar'];
                
                // Handle Avatar Upload
                if (!empty($_FILES['avatar']['name'])) {
                    $file     = $_FILES['avatar'];
                    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                    
                    if ($file['size'] > MAX_FILE_SIZE) {
                        setFlash('error', 'Avatar file size exceeds the 5MB limit.');
                        header('Location: ' . APP_URL . '/user/settings.php');
                        exit;
                    }
                    
                    if (!in_array($file['type'], ALLOWED_TYPES)) {
                        setFlash('error', 'Invalid file type. Only JPG, PNG, and WEBP are allowed.');
                        header('Location: ' . APP_URL . '/user/settings.php');
                        exit;
                    }
                    
                    if (!is_dir(UPLOAD_PATH)) {
                        mkdir(UPLOAD_PATH, 0777, true);
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $filename)) {
                        // Delete old avatar if exists and not default
                        if ($user['avatar'] && file_exists(UPLOAD_PATH . $user['avatar'])) {
                            @unlink(UPLOAD_PATH . $user['avatar']);
                        }
                        $avatarName = $filename;
                    }
                }
                
                dbQuery("UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?", [$name, $email, $phone, $avatarName, $userId]);
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                setFlash('success', 'Profile updated successfully.');
            }
        }
        header('Location: ' . APP_URL . '/user/settings.php?tab=profile');
        exit;

    } elseif ($action === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            setFlash('error', 'All password fields are required.');
        } elseif ($newPassword !== $confirmPassword) {
            setFlash('error', 'New passwords do not match.');
        } elseif (strlen($newPassword) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } else {
            // Verify current password
            $currentHashed = dbFetchColumn("SELECT password FROM users WHERE id = ?", [$userId]);
            if (!password_verify($currentPassword, $currentHashed)) {
                setFlash('error', 'Incorrect current password.');
            } else {
                $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
                dbQuery("UPDATE users SET password = ? WHERE id = ?", [$newHashed, $userId]);
                setFlash('success', 'Password updated successfully.');
            }
        }
        header('Location: ' . APP_URL . '/user/settings.php?tab=password');
        exit;

    } elseif ($action === 'add_address' || $action === 'edit_address') {
        $addressId    = sanitizeInt($_POST['address_id'] ?? 0);
        $label        = sanitize($_POST['label'] ?? 'Home');
        $fullName     = sanitize($_POST['full_name'] ?? '');
        $phone        = sanitize($_POST['phone'] ?? '');
        $addressLine1 = sanitize($_POST['address_line1'] ?? '');
        $addressLine2 = sanitize($_POST['address_line2'] ?? '');
        $city         = sanitize($_POST['city'] ?? '');
        $province     = sanitize($_POST['province'] ?? '');
        $zipCode      = sanitize($_POST['zip_code'] ?? '');
        $isDefault    = isset($_POST['is_default']) ? 1 : 0;

        if (!$fullName || !$phone || !$addressLine1 || !$city || !$province || !$zipCode) {
            setFlash('error', 'Please fill in all required address fields.');
        } else {
            if ($isDefault) {
                // Remove default flag from all other addresses
                dbQuery("UPDATE addresses SET is_default = 0 WHERE user_id = ?", [$userId]);
            }
            
            // If it's the first address, force it to be default
            if (empty($addresses)) {
                $isDefault = 1;
            }

            if ($action === 'add_address') {
                dbQuery("INSERT INTO addresses (user_id, label, full_name, phone, address_line1, address_line2, city, province, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$userId, $label, $fullName, $phone, $addressLine1, $addressLine2, $city, $province, $zipCode, $isDefault]);
                setFlash('success', 'New address added.');
            } else {
                // Verify ownership of the address before updating
                dbQuery("UPDATE addresses SET label = ?, full_name = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, province = ?, zip_code = ?, is_default = ? WHERE id = ? AND user_id = ?",
                    [$label, $fullName, $phone, $addressLine1, $addressLine2, $city, $province, $zipCode, $isDefault, $addressId, $userId]);
                setFlash('success', 'Address updated.');
            }
        }
        header('Location: ' . APP_URL . '/user/settings.php?tab=addresses');
        exit;

    } elseif ($action === 'delete_address') {
        $addressId = sanitizeInt($_POST['address_id'] ?? 0);
        
        // Find if address is default
        $wasDefault = dbFetchColumn("SELECT is_default FROM addresses WHERE id = ? AND user_id = ?", [$addressId, $userId]);
        
        dbQuery("DELETE FROM addresses WHERE id = ? AND user_id = ?", [$addressId, $userId]);
        
        // If deleted address was default, set another address as default
        if ($wasDefault) {
            $nextId = dbFetchColumn("SELECT id FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$userId]);
            if ($nextId) {
                dbQuery("UPDATE addresses SET is_default = 1 WHERE id = ?", [$nextId]);
            }
        }
        
        setFlash('success', 'Address deleted.');
        header('Location: ' . APP_URL . '/user/settings.php?tab=addresses');
        exit;
    }
}

$activeTab = sanitize($_GET['tab'] ?? 'profile');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .dash-layout { display: grid; grid-template-columns: 260px 1fr; gap: 32px; padding: calc(var(--navbar-h)+40px) var(--section-px) var(--section-py); max-width: var(--container); margin: 0 auto; }
  .dash-sidebar { position: sticky; top: calc(var(--navbar-h)+16px); height: fit-content; }
  .dash-profile-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: var(--radius-xl); padding: 28px; color: white; text-align: center; margin-bottom: 14px; }
  .dash-avatar { width: 64px; height: 64px; border-radius: 50%; background: rgba(255,255,255,.25); display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-size: 1.6rem; font-weight: 700; margin: 0 auto 12px; border: 2px solid rgba(255,255,255,.3); overflow: hidden; }
  .dash-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .dash-nav { background: white; border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); }
  .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; font-size: .88rem; color: var(--text-body); transition: .2s; text-decoration: none; border-bottom: 1px solid var(--border-light); }
  .dash-nav-item:last-child { border-bottom: none; }
  .dash-nav-item i { width: 18px; text-align: center; color: var(--text-muted); }
  .dash-nav-item:hover, .dash-nav-item.active { background: var(--primary-light); color: var(--primary); }
  .dash-nav-item:hover i, .dash-nav-item.active i { color: var(--primary); }
  .dash-nav-item.danger { color: var(--danger); } .dash-nav-item.danger i { color: var(--danger); } .dash-nav-item.danger:hover { background: #fef2f2; }

  .settings-tabs { display: flex; gap: 20px; border-bottom: 1px solid var(--border); margin-bottom: 24px; }
  .tab-btn { background: none; border: none; padding: 12px 6px; font-family: inherit; font-size: .9rem; font-weight: 500; color: var(--text-muted); cursor: pointer; transition: .2s; border-bottom: 2px solid transparent; }
  .tab-btn:hover { color: var(--primary); }
  .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }

  .settings-panel { display: none; background: white; border-radius: var(--radius-xl); padding: 32px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); }
  .settings-panel.active { display: block; }

  .form-group { margin-bottom: 20px; }
  .form-label { display: block; font-size: .84rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; }
  .form-control { width: 100%; padding: 11px 16px; border-radius: var(--radius-md); border: 1.5px solid var(--border); font-family: inherit; font-size: .88rem; color: var(--text-dark); transition: .2s; }
  .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

  .address-card { border: 1.5px solid var(--border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; transition: .2s; }
  .address-card:hover { border-color: var(--primary-light); box-shadow: var(--shadow-sm); }
  .address-card.default { border-color: var(--primary); background: var(--primary-light); }
  .address-badge { display: inline-block; background: var(--primary); color: white; font-size: .65rem; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: var(--radius-pill); margin-bottom: 8px; }

  .avatar-upload-box { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
  .avatar-preview { width: 80px; height: 80px; border-radius: 50%; overflow: hidden; background: var(--beige); border: 2px solid var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-family: var(--font-serif); font-weight: 700; color: var(--primary); }
  .avatar-preview img { width: 100%; height: 100%; object-fit: cover; }

  /* Address modal style */
  .address-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
  .address-modal.open { display: flex; }
  .modal-inner { background: white; border-radius: var(--radius-xl); padding: 32px; max-width: 500px; width: 100%; box-shadow: var(--shadow-xl); max-height: 90vh; overflow-y: auto; }
  
  @media(max-width:900px) { .dash-layout { grid-template-columns: 1fr; } .dash-sidebar { position: static; } .form-row { grid-template-columns: 1fr; gap: 0; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/../components/navbar.php'; ?>
<?php include __DIR__ . '/../components/cart-drawer.php'; ?>

<div class="dash-layout">
  <!-- Sidebar -->
  <aside class="dash-sidebar">
    <div class="dash-profile-card">
      <div class="dash-avatar">
        <?php if ($user['avatar']): ?>
          <img src="<?= APP_URL ?>/assets/images/uploads/<?= sanitize($user['avatar']) ?>" alt="">
        <?php else: ?>
          <?= strtoupper(substr($user['name'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div style="font-family:var(--font-serif);font-size:1.1rem;font-weight:600;margin-bottom:4px;"><?= sanitize($user['name']) ?></div>
      <div style="font-size:.76rem;opacity:.8;"><?= sanitize($user['email']) ?></div>
    </div>
    <nav class="dash-nav">
      <a href="<?= APP_URL ?>/user/dashboard.php" class="dash-nav-item"><i class="fa-regular fa-user"></i> My Account</a>
      <a href="<?= APP_URL ?>/user/orders.php" class="dash-nav-item"><i class="fa-regular fa-bag-shopping"></i> My Orders</a>
      <a href="<?= APP_URL ?>/user/wishlist.php" class="dash-nav-item"><i class="fa-regular fa-heart"></i> My Wishlist</a>
      <a href="<?= APP_URL ?>/tracking.php" class="dash-nav-item"><i class="fa-solid fa-truck"></i> Track Order</a>
      <a href="<?= APP_URL ?>/user/settings.php" class="dash-nav-item active"><i class="fa-solid fa-gear"></i> Settings</a>
      <a href="<?= APP_URL ?>/logout.php" class="dash-nav-item danger"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <div>
    <?= renderFlash() ?>

    <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:24px;">Settings</h2>

    <div class="settings-tabs">
      <button class="tab-btn <?= $activeTab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile')">Profile Info</button>
      <button class="tab-btn <?= $activeTab === 'password' ? 'active' : '' ?>" onclick="switchTab('password')">Change Password</button>
      <button class="tab-btn <?= $activeTab === 'addresses' ? 'active' : '' ?>" onclick="switchTab('addresses')">Address Book</button>
    </div>

    <!-- PROFILE PANEL -->
    <div id="panel-profile" class="settings-panel <?= $activeTab === 'profile' ? 'active' : '' ?>">
      <form action="<?= APP_URL ?>/user/settings.php" method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="profile">

        <div class="avatar-upload-box">
          <div class="avatar-preview">
            <?php if ($user['avatar']): ?>
              <img src="<?= APP_URL ?>/assets/images/uploads/<?= sanitize($user['avatar']) ?>" id="avatarImg" alt="">
            <?php else: ?>
              <span id="avatarLetter"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
            <?php endif; ?>
          </div>
          <div>
            <label for="avatar" class="form-label" style="margin-bottom:6px;">Profile Picture</label>
            <input type="file" name="avatar" id="avatar" accept="image/*" onchange="previewAvatar(this)" style="font-size:.8rem;">
            <p style="font-size:.74rem;color:var(--text-muted);margin-top:6px;">JPG, PNG or WEBP. Max size 5MB.</p>
          </div>
        </div>

        <div class="form-group">
          <label for="name" class="form-label">Full Name</label>
          <input type="text" name="name" id="name" class="form-control" value="<?= sanitize($user['name']) ?>" required>
        </div>

        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" name="email" id="email" class="form-control" value="<?= sanitize($user['email']) ?>" required>
        </div>

        <div class="form-group">
          <label for="phone" class="form-label">Phone Number</label>
          <input type="text" name="phone" id="phone" class="form-control" value="<?= sanitize($user['phone'] ?? '') ?>" placeholder="e.g., 09171234567">
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Save Profile Changes</button>
      </form>
    </div>

    <!-- PASSWORD PANEL -->
    <div id="panel-password" class="settings-panel <?= $activeTab === 'password' ? 'active' : '' ?>">
      <form action="<?= APP_URL ?>/user/settings.php" method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="password">

        <div class="form-group">
          <label for="current_password" class="form-label">Current Password</label>
          <input type="password" name="current_password" id="current_password" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="new_password" class="form-label">New Password</label>
          <input type="password" name="new_password" id="new_password" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="confirm_password" class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Change Password</button>
      </form>
    </div>

    <!-- ADDRESS BOOK PANEL -->
    <div id="panel-addresses" class="settings-panel <?= $activeTab === 'addresses' ? 'active' : '' ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <h3 style="font-family:var(--font-serif);font-size:1.2rem;">Saved Addresses</h3>
        <button class="btn btn-primary btn-sm" onclick="openAddressModal()">Add New Address</button>
      </div>

      <?php if (empty($addresses)): ?>
        <div style="text-align:center;padding:48px 24px;border:1.5px dashed var(--border);border-radius:var(--radius-xl);">
          <i class="fa-solid fa-map-location-dot" style="font-size:2rem;color:var(--text-muted);margin-bottom:12px;"></i>
          <p style="color:var(--text-muted);font-size:.9rem;">No addresses saved yet.</p>
        </div>
      <?php else: ?>
        <?php foreach ($addresses as $addr): ?>
          <div class="address-card <?= $addr['is_default'] ? 'default' : '' ?>">
            <div>
              <?php if ($addr['is_default']): ?>
                <span class="address-badge">Default</span>
              <?php endif; ?>
              <h4 style="font-weight:600;font-size:.92rem;color:var(--text-dark);"><?= sanitize($addr['label']) ?></h4>
              <p style="font-size:.86rem;margin-top:6px;font-weight:500;"><?= sanitize($addr['full_name']) ?> · <?= sanitize($addr['phone']) ?></p>
              <p style="font-size:.82rem;color:var(--text-muted);margin-top:2px;">
                <?= sanitize($addr['address_line1']) ?><?= $addr['address_line2'] ? ', ' . sanitize($addr['address_line2']) : '' ?><br>
                <?= sanitize($addr['city']) ?>, <?= sanitize($addr['province']) ?> <?= sanitize($addr['zip_code']) ?>
              </p>
            </div>
            <div style="display:flex;gap:10px;">
              <button class="btn btn-ghost btn-sm" onclick='openAddressModal(<?= json_encode($addr) ?>)'><i class="fa-solid fa-pen"></i></button>
              <form action="<?= APP_URL ?>/user/settings.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this address?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_address">
                <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i class="fa-regular fa-trash-can"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ADDRESS BOOK MODAL -->
<div class="address-modal" id="addressModal">
  <div class="modal-inner">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <h3 style="font-family:var(--font-serif);font-size:1.4rem;" id="modalTitle">Add New Address</h3>
      <button onclick="closeAddressModal()" style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:var(--text-muted);"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <form action="<?= APP_URL ?>/user/settings.php" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" id="modalAction" value="add_address">
      <input type="hidden" name="address_id" id="modalAddressId" value="">

      <div class="form-group">
        <label for="addr_label" class="form-label">Address Label</label>
        <input type="text" name="label" id="addr_label" class="form-control" placeholder="e.g., Home, Office" required>
      </div>

      <div class="form-group">
        <label for="addr_fullname" class="form-label">Full Name</label>
        <input type="text" name="full_name" id="addr_fullname" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="addr_phone" class="form-label">Phone Number</label>
        <input type="text" name="phone" id="addr_phone" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="addr_line1" class="form-label">Address Line 1</label>
        <input type="text" name="address_line1" id="addr_line1" class="form-control" placeholder="House #, Street name" required>
      </div>

      <div class="form-group">
        <label for="addr_line2" class="form-label">Address Line 2 (Optional)</label>
        <input type="text" name="address_line2" id="addr_line2" class="form-control" placeholder="Apartment, suite, unit, building">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="addr_city" class="form-label">City</label>
          <input type="text" name="city" id="addr_city" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="addr_province" class="form-label">Province</label>
          <input type="text" name="province" id="addr_province" class="form-control" required>
        </div>
      </div>

      <div class="form-group">
        <label for="addr_zip" class="form-label">Zip Code</label>
        <input type="text" name="zip_code" id="addr_zip" class="form-control" required>
      </div>

      <div class="form-group" style="display:flex;align-items:center;gap:10px;">
        <input type="checkbox" name="is_default" id="addr_default" value="1">
        <label for="addr_default" style="font-size:.85rem;font-weight:500;color:var(--text-dark);user-select:none;">Set as Default Shipping Address</label>
      </div>

      <div style="display:flex;gap:12px;margin-top:24px;">
        <button type="button" class="btn btn-outline btn-full" onclick="closeAddressModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-full" id="modalSubmitBtn">Save Address</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script>
function switchTab(tabId) {
  // Update browser URL query parameter without reloading page
  const url = new URL(window.location);
  url.searchParams.set('tab', tabId);
  window.history.pushState({}, '', url);

  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.settings-panel').forEach(panel => panel.classList.remove('active'));
  
  // Find current clicked button by exact target matching
  const matchingBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => btn.getAttribute('onclick').includes(tabId));
  if (matchingBtn) matchingBtn.classList.add('active');
  
  const panel = document.getElementById('panel-' + tabId);
  if (panel) panel.classList.add('active');
}

function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const preview = document.querySelector('.avatar-preview');
      preview.innerHTML = `<img src="${e.target.result}" id="avatarImg" alt="">`;
    }
    reader.readAsDataURL(input.files[0]);
  }
}

function openAddressModal(addr = null) {
  const modal = document.getElementById('addressModal');
  const title = document.getElementById('modalTitle');
  const action = document.getElementById('modalAction');
  const idField = document.getElementById('modalAddressId');
  const btn = document.getElementById('modalSubmitBtn');

  if (addr) {
    title.textContent = 'Edit Address';
    action.value = 'edit_address';
    idField.value = addr.id;
    btn.textContent = 'Update Address';

    document.getElementById('addr_label').value = addr.label;
    document.getElementById('addr_fullname').value = addr.full_name;
    document.getElementById('addr_phone').value = addr.phone;
    document.getElementById('addr_line1').value = addr.address_line1;
    document.getElementById('addr_line2').value = addr.address_line2 || '';
    document.getElementById('addr_city').value = addr.city;
    document.getElementById('addr_province').value = addr.province;
    document.getElementById('addr_zip').value = addr.zip_code;
    document.getElementById('addr_default').checked = addr.is_default == 1;
  } else {
    title.textContent = 'Add New Address';
    action.value = 'add_address';
    idField.value = '';
    btn.textContent = 'Save Address';

    document.getElementById('addr_label').value = 'Home';
    document.getElementById('addr_fullname').value = '<?= addslashes(sanitize($user['name'])) ?>';
    document.getElementById('addr_phone').value = '<?= addslashes(sanitize($user['phone'] ?? '')) ?>';
    document.getElementById('addr_line1').value = '';
    document.getElementById('addr_line2').value = '';
    document.getElementById('addr_city').value = '';
    document.getElementById('addr_province').value = '';
    document.getElementById('addr_zip').value = '';
    document.getElementById('addr_default').checked = false;
  }

  modal.classList.add('open');
}

function closeAddressModal() {
  document.getElementById('addressModal').classList.remove('open');
}
</script>
</body>
</html>
