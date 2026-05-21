<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Story — Luna Glow</title>
  <meta name="description" content="Discover the story behind Luna Glow. Crafted with clean ingredients, designed for modern beauty, celebrating your most radiant self.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  /* About Hero */
  .about-hero {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    padding-top: var(--navbar-h);
    background: linear-gradient(rgba(42, 31, 40, 0.4), rgba(42, 31, 40, 0.5)), url('https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?q=80&w=1600&auto=format&fit=crop') center/cover no-repeat;
    color: white;
    text-align: center;
  }
  .about-hero-content {
    max-width: 800px;
    padding: 40px 20px;
    z-index: 2;
  }
  .about-hero-title {
    font-family: var(--font-serif);
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 700;
    margin-bottom: 20px;
    line-height: 1.2;
    letter-spacing: 1px;
  }
  .about-hero-desc {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.8;
  }

  /* Philosophy Section */
  .about-grid {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 60px;
    align-items: center;
    padding: 80px var(--section-px);
    max-width: var(--container);
    margin: 0 auto;
  }
  .about-visual {
    position: relative;
    border-radius: var(--radius-xl);
    overflow: hidden;
    height: 540px;
    box-shadow: var(--shadow-lg);
  }
  .about-visual img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .about-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .about-info h2 {
    font-family: var(--font-serif);
    font-size: 2.5rem;
    color: var(--text-dark);
    margin-bottom: 24px;
    line-height: 1.2;
  }
  .about-info p {
    font-size: 1rem;
    color: var(--text-body);
    line-height: 1.8;
    margin-bottom: 20px;
  }

  /* Stats Section */
  .about-stats {
    background: var(--beige);
    padding: 60px var(--section-px);
    text-align: center;
  }
  .stats-inner {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    max-width: var(--container);
    margin: 0 auto;
  }
  .stat-item {
    padding: 10px;
  }
  .stat-number {
    font-family: var(--font-serif);
    font-size: 2.8rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 8px;
  }
  .stat-label {
    font-size: .88rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  /* Features List */
  .features-section {
    padding: 80px var(--section-px);
    max-width: var(--container);
    margin: 0 auto;
    text-align: center;
  }
  .features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 48px;
  }
  .feature-box {
    background: white;
    padding: 40px 30px;
    border-radius: var(--radius-xl);
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-xs);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .feature-box:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary-light);
  }
  .feature-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin: 0 auto 24px;
  }
  .feature-title {
    font-family: var(--font-serif);
    font-size: 1.3rem;
    color: var(--text-dark);
    margin-bottom: 14px;
  }
  .feature-desc {
    font-size: .9rem;
    color: var(--text-muted);
    line-height: 1.7;
  }

  /* Call to action */
  .cta-section {
    background: linear-gradient(135deg, #fce8ed 0%, #fdf8f5 100%);
    padding: 80px 20px;
    text-align: center;
    border-radius: var(--radius-xl);
    margin: 0 var(--section-px) 80px;
  }
  .cta-title {
    font-family: var(--font-serif);
    font-size: 2.4rem;
    color: var(--text-dark);
    margin-bottom: 16px;
  }
  .cta-desc {
    color: var(--text-muted);
    max-width: 500px;
    margin: 0 auto 32px;
    font-size: 1rem;
    line-height: 1.8;
  }

  @media (max-width: 900px) {
    .about-grid { grid-template-columns: 1fr; gap: 40px; }
    .about-visual { height: 350px; }
    .stats-inner { grid-template-columns: repeat(2, 1fr); gap: 24px; }
    .features-grid { grid-template-columns: 1fr; }
    .cta-section { margin: 0 20px 60px; }
  }
  </style>
</head>
<body>

<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/cart-drawer.php'; ?>

<!-- Hero -->
<section class="about-hero">
  <div class="about-hero-content">
    <h1 class="about-hero-title">Redefining Luminous Beauty</h1>
    <p class="about-hero-desc">Luna Glow was created to celebrate individuality, confidence, and radiant skin. We merge luxury ingredients with skin-first formulas for a natural, editorial glow.</p>
  </div>
</section>

<!-- Our Story -->
<section class="about-grid">
  <div class="about-visual">
    <img src="https://images.unsplash.com/photo-1596462502278-27bfdc403348?q=80&w=800&auto=format&fit=crop" alt="Luna Glow Cosmetics Formulation">
  </div>
  <div class="about-info">
    <span class="section-label" style="margin-bottom:12px;">Our Origin</span>
    <h2>Born from a Desire for Light, Made to Elevate.</h2>
    <p>Established in 2026, Luna Glow began in Manila with a simple, singular vision: to curate a makeup range that bridges the gap between scientific skincare and luxury cosmetics. We believed makeup shouldn't hide your skin; it should illuminate it.</p>
    <p>Every formula we launch is clean, rich in active nutrients, and meticulously crafted to keep your skin hydrated while providing seamless, editorial finishes. From velvet-soft lips to buildable foundations, we bring you formulas that deliver premium, lasting elegance.</p>
  </div>
</section>

<!-- Stats -->
<section class="about-stats">
  <div class="stats-inner">
    <div class="stat-item">
      <div class="stat-number">100%</div>
      <div class="stat-label">Cruelty Free</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">12K+</div>
      <div class="stat-label">Happy Clients</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">15+</div>
      <div class="stat-label">Clean Formulations</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">4.9★</div>
      <div class="stat-label">Average Review</div>
    </div>
  </div>
</section>

<!-- Values -->
<section class="features-section">
  <span class="section-label">Our Core Values</span>
  <h2 style="font-family:var(--font-serif);font-size:2.2rem;color:var(--text-dark);margin-top:12px;">What We Stand For</h2>
  <div class="features-grid">
    <div class="feature-box">
      <div class="feature-icon"><i class="fa-solid fa-feather"></i></div>
      <h3 class="feature-title">Pure Ingredients</h3>
      <p class="feature-desc">We load our cosmetics with nourishing botanical extracts, vitamin E, and hyaluronic acid. Free from parabens, sulfates, and toxic fillers.</p>
    </div>
    <div class="feature-box">
      <div class="feature-icon"><i class="fa-solid fa-heart"></i></div>
      <h3 class="feature-title">Cruelty-Free Ethics</h3>
      <p class="feature-desc">We never test on animals. We believe that true beauty resides in kindness and cruelty-free sustainability from source to shelf.</p>
    </div>
    <div class="feature-box">
      <div class="feature-icon"><i class="fa-solid fa-sparkles"></i></div>
      <h3 class="feature-title">Effortless Radiance</h3>
      <p class="feature-desc">Our products are engineered for buildable coverage and easy, mistake-proof blending so that you achieve a professional glow every time.</p>
    </div>
  </div>
</section>

<!-- Call to Action -->
<section class="cta-section">
  <h2 class="cta-title">Experience the Glow Today</h2>
  <p class="cta-desc">Discover our best-selling lipsticks, foundations, and sets carefully curated for your most confident self.</p>
  <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary btn-lg">Explore the Boutique</a>
</section>

<?php include __DIR__ . '/components/footer.php'; ?>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
</body>
</html>
