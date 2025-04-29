<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>


<?php if (session()->getFlashdata('success') === 'changes_saved'): ?>
<div id="modalPreguntar" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:1000;">
  <div style="background:white;padding:20px;border-radius:8px;text-align:center;max-width:400px;width:90%;">
    <h3>¡Cambios guardados!</h3>
    <p>¿Qué quieres hacer ahora?</p>
    <button onclick="seguirEditando()" style="margin-right:10px;">Seguir editando</button>
    <button onclick="verLista()">Ver lista de lunches</button>
  </div>
</div>

<script>
function seguirEditando() {
    document.getElementById('modalPreguntar').style.display = 'none';
}

function verLista() {
    window.location.href = "<?= site_url('lunch') ?>"; // Asegúrate que la URL sea correcta
}
</script>
<?php endif; ?>



<?= $this->endSection() ?>