<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?= $title ?? 'Darink.app' ?></title>

  <link rel="stylesheet" href="<?= base_url('/css/style.css') ?>">

</head>

<body>

  <?= view('partials/header') ?>
  

  <main>
    <?= $this->renderSection('content') ?>
  </main>

</body>

</html>