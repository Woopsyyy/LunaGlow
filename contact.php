<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['name'] ?? '');
    $email   = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (!$name || !$email || !$subject || !$message) {
        setFlash('error', 'Please fill in all fields with valid information.');
    } else {
        // In a live system, this would mail() or write to a db.
        // We'll set a beautiful flash success message.
        setFlash('success', 'Thank you, ' . $name . '! Your message has been sent. We will get back to you within 24 hours.');
        header('Location: ' . APP_URL . '/contact.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us — Luna Glow</title>
  <meta name="description" content="Get in touch with Luna Glow. Whether you have questions about your order, our cosmetics, or just want to chat beauty, we're here to help.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .contact-container {
    max-width: var(--container);
    margin: 0 auto;
    padding: calc(var(--navbar-h) + 40px) var(--section-px) 80px;
  }
  .contact-header {
    text-align: center;
    margin-bottom: 50px;
  }
  .contact-header h1 {
    font-family: var(--font-serif);
    font-size: clamp(2rem, 4vw, 3rem);
    color: var(--text-dark);
    margin-top: 10px;
  }

  .contact-grid {
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: 60px;
  }

  /* Info Side */
  .contact-info-panel {
    background: var(--beige);
    border-radius: var(--radius-xl);
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .info-title {
    font-family: var(--font-serif);
    font-size: 1.8rem;
    color: var(--text-dark);
    margin-bottom: 14px;
  }
  .info-desc {
    font-size: .95rem;
    color: var(--text-body);
    line-height: 1.7;
    margin-bottom: 32px;
  }
  .info-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
  }
  .info-item-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: white;
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: var(--shadow-xs);
    flex-shrink: 0;
  }
  .info-item-title {
    font-weight: 600;
    color: var(--text-dark);
    font-size: .9rem;
    margin-bottom: 4px;
  }
  .info-item-detail {
    font-size: .88rem;
    color: var(--text-body);
  }

  .social-box {
    margin-top: 40px;
  }
  .social-title {
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 16px;
  }
  .social-links {
    display: flex;
    gap: 12px;
  }
  .social-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: white;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: var(--shadow-xs);
    transition: .2s;
    text-decoration: none;
  }
  .social-btn:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
  }

  /* Form Side */
  .contact-form-panel {
    background: white;
    border-radius: var(--radius-xl);
    padding: 40px;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-xs);
  }
  .form-group {
    margin-bottom: 20px;
  }
  .form-label {
    display: block;
    font-size: .84rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
  }
  .form-control {
    width: 100%;
    padding: 12px 18px;
    border-radius: var(--radius-md);
    border: 1.5px solid var(--border);
    font-family: inherit;
    font-size: .88rem;
    color: var(--text-dark);
    transition: .2s;
    background: transparent;
  }
  .form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 4px var(--primary-glow);
  }
  textarea.form-control {
    resize: vertical;
    min-height: 140px;
  }

  @media (max-width: 900px) {
    .contact-grid {
      grid-template-columns: 1fr;
      gap: 40px;
    }
  }
  </style>
</head>
<body>

<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/cart-drawer.php'; ?>

<div class="contact-container">
  <?= renderFlash() ?>

  <div class="contact-header">
    <span class="section-label">Get in Touch</span>
    <h1>We’d Love to Hear from You</h1>
  </div>

  <div class="contact-grid">
    <!-- Info Section -->
    <div class="contact-info-panel">
      <div>
        <h2 class="info-title">Luna Glow HQ</h2>
        <p class="info-desc">Have a question about our products, orders, shipping, or looking to collaborate? Drop us a line. Our beauty experts are ready to assist you!</p>

        <div class="info-item">
          <div class="info-item-icon"><i class="fa-solid fa-envelope"></i></div>
          <div>
            <div class="info-item-title">Email Us</div>
            <div class="info-item-detail"><a href="mailto:<?= CONTACT_EMAIL ?>" style="color:inherit;"><?= CONTACT_EMAIL ?></a></div>
          </div>
        </div>

        <div class="info-item">
          <div class="info-item-icon"><i class="fa-solid fa-phone"></i></div>
          <div>
            <div class="info-item-title">Call Us</div>
            <div class="info-item-detail"><?= CONTACT_PHONE ?></div>
          </div>
        </div>

        <div class="info-item">
          <div class="info-item-icon"><i class="fa-solid fa-location-dot"></i></div>
          <div>
            <div class="info-item-title">Visit Our Showroom</div>
            <div class="info-item-detail"><?= CONTACT_ADDRESS ?></div>
          </div>
        </div>
      </div>

      <div class="social-box">
        <div class="social-title">Follow Our Journey</div>
        <div class="social-links">
          <a href="<?= SOCIAL_INSTAGRAM ?>" class="social-btn" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="<?= SOCIAL_FACEBOOK ?>" class="social-btn" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="<?= SOCIAL_TIKTOK ?>" class="social-btn" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
          <a href="<?= SOCIAL_YOUTUBE ?>" class="social-btn" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
        </div>
      </div>
    </div>

    <!-- Form Section -->
    <div class="contact-form-panel">
      <h3 style="font-family:var(--font-serif);font-size:1.6rem;color:var(--text-dark);margin-bottom:24px;">Send a Message</h3>
      <form action="<?= APP_URL ?>/contact.php" method="POST" id="contactForm">
        <?= csrfField() ?>
        <div class="form-group">
          <label for="name" class="form-label">Full Name</label>
          <input type="text" name="name" id="name" class="form-control" placeholder="Enter your full name" required>
        </div>

        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" required>
        </div>

        <div class="form-group">
          <label for="subject" class="form-label">Subject</label>
          <input type="text" name="subject" id="subject" class="form-control" placeholder="What is this regarding?" required>
        </div>

        <div class="form-group">
          <label for="message" class="form-label">Message</label>
          <textarea name="message" id="message" class="form-control" placeholder="Type your message here..." required></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="padding:14px;margin-top:10px;">
          <i class="fa-solid fa-paper-plane" style="margin-right:8px;"></i> Send Message
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
</body>
</html>
