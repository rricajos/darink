<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Título dinámico con fallback -->
  <title><?= isset($title) ? "$title – Darink.App" : "Darink.App – Alimentación con conciencia" ?></title>

  <!-- Descripción dinámica para SEO -->
  <meta name="description" content="<?= esc($meta_description ?? 'Darink.App te guía hacia una alimentación sana, consciente y compasiva. Un espacio seguro para mejorar tu bienestar.') ?>">

  <!-- Palabras clave dinámicas -->
  <meta name="keywords" content="<?= esc($meta_keywords ?? 'TCA, alimentación consciente, salud mental, trastornos alimentarios, bienestar, autocuidado, app de salud') ?>">

  <!-- Autor -->
  <meta name="author" content="Darink Team">

  <!-- Canonical URL -->
  <link rel="canonical" href="<?= esc($meta_url ?? current_url()) ?>">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= esc($title ?? 'Darink.App – Alimentación con conciencia') ?>">
  <meta property="og:description" content="<?= esc($meta_description ?? 'Un espacio seguro para quienes buscan sanar su relación con la comida.') ?>">
  <meta property="og:image" content="<?= esc($meta_image ?? base_url('images/og-image.png')) ?>">
  <meta property="og:url" content="<?= esc($meta_url ?? current_url()) ?>">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= esc($title ?? 'Darink.App – Alimentación con conciencia') ?>">
  <meta name="twitter:description" content="<?= esc($meta_description ?? 'Un espacio seguro para quienes buscan sanar su relación con la comida.') ?>">
  <meta name="twitter:image" content="<?= esc($meta_image ?? base_url('images/og-image.png')) ?>">

  <!-- Favicon -->
  <link rel="icon" href="<?= base_url('favicon.ico') ?>" type="image/x-icon">

  <!-- CSS -->
  <link rel="stylesheet" href="<?= base_url('/css/roots.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/style.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/table.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/input.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/button.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/navbar.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/dashboard.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/food_feedback_component.css') ?>">

  <!-- Zilla Slab -->
  <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab&display=swap" rel="stylesheet">


  <link rel="stylesheet" href="/font-awesome-4.7.0/css/font-awesome.min.css">
  <!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"> -->

  <!-- Datos estructurados JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "Darink.App",
    "url": "<?= base_url() ?>",
    "author": {
      "@type": "Organization",
      "name": "Darink Team"
    },
    "description": <?= json_encode($meta_description ?? 'Darink.App te guía hacia una alimentación sana, consciente y compasiva.') ?>,
    "inLanguage": "es"
  }
  </script>
  
</head>
<body>

  <?= view('header/header') ?>


  <main role="main">
    <?= $this->renderSection('content') ?>
  </main>

  <footer role="contentinfo">
    <?= view('footer/footer') ?>
  </footer>

</body>
</html>
