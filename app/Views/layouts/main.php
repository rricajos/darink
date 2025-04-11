<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Título dinámico con fallback -->
  <title><?= $title ?? 'Darink.App – Alimentación con conciencia' ?></title>

  <!-- Descripción para SEO -->
  <meta name="description" content="Darink.App es una aplicación diseñada para acompañarte en tu camino hacia una alimentación más sana, consciente y compasiva.">

  <!-- Palabras clave opcionales -->
  <meta name="keywords" content="TCA, alimentación consciente, salud mental, trastornos alimentarios, bienestar, autocuidado, app de salud">

  <!-- Autor -->
  <meta name="author" content="Darink Team">

  <!-- Open Graph (para compartir en redes sociales como Facebook) -->
  <meta property="og:title" content="Darink.App – Alimentación con conciencia">
  <meta property="og:description" content="Un espacio seguro para quienes buscan sanar su relación con la comida.">
  <meta property="og:image" content="<?= base_url('images/og-image.png') ?>">
  <meta property="og:url" content="<?= current_url() ?>">
  <meta property="og:type" content="website">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Darink.App – Alimentación con conciencia">
  <meta name="twitter:description" content="Un espacio seguro para quienes buscan sanar su relación con la comida.">
  <meta name="twitter:image" content="<?= base_url('images/og-image.png') ?>">

  <!-- Favicon (ajústalo según tu logo) -->
  <link rel="icon" href="<?= base_url('favicon.ico') ?>" type="image/x-icon">

  <!-- CSS principal -->
  <link rel="stylesheet" href="<?= base_url('/css/style.css') ?>">
  <link rel="stylesheet" href="<?= base_url('/css/navbar.css') ?>">

  <!-- Fuente opcional desde Google Fonts -->
  <!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"> -->
</head>

<body>

  <?= view('partials/navbar') ?>
  

  <main>
    <?= $this->renderSection('content') ?>
  </main>

  <?= view('partials/footer') ?>

  <script src="<?= base_url('js/navbar.js') ?>"></script>
</body>

</html>