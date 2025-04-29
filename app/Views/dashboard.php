<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$menuItems = [
  ['url' => '/dashboard', 'icon' => 'fa fa-calendar', 'label' => 'Calendar View', 'active' => true],
  ['url' => '/lunch/index', 'icon' => 'fa fa-list', 'label' => 'List View'],
  // ['url' => '/agenda', 'icon' => 'fa fa-calendar-check-o', 'label' => 'Agenda View'], // Si no carga, usa fa-calendar
  ['url' => '/lunch/new', 'icon' => 'fa fa-cutlery', 'label' => 'Add Lunch'],
  // ['url' => '/graphs', 'icon' => 'fa fa-line-chart', 'label' => 'Graphs'],
  // ['url' => '/profile',   'icon' => 'fa fa-user',       'label' => 'Profile'],
  // ['url' => '/logout',    'icon' => 'fa fa-sign-out',   'label' => 'Log Out'],
];
?>


<section class="">



  <nav class="super-flex-row-nowrap" role="navigation" aria-label="Darink App Dashboard">
    <?= view('dashboard/dashboard_menu', ['items' => $menuItems]) ?>
  </nav>


</section>


<?= $this->endSection() ?>