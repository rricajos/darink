<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?= view('index/index_fim') ?> <!-- what (First Impression Maters F.I.M.)-->
<?= view('index/index_why') ?> <!-- why -->
<?= view('index/index_how') ?> <!-- how -->
<?= view('index/index_cta') ?> <!-- cta -->

<?= $this->endSection() ?>
