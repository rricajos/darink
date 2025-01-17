<section>
  <div class="supercont">


    <article class="subcont art-login">

      <h2>Iniciar sesión</h2>

      <!-- Mostrar el mensaje de error si existe -->
      <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
      <?php endif; ?>

      <!-- Formulario de Login -->
      <form action="index.php" method="POST" class="form-login">
        <input type="hidden" name="case" value="loggin">

        <div class="form-login-card">
          <label for="nickname">Nombre de usuario</label>
          <input type="text" name="nickname" id="nickname" required>
        </div>

        <div class="form-login-card">
          <label for="password">Contraseña</label>
          <input type="password" name="password" id="password" required>
        </div>


        <div class="form-login-card">
          <button type="submit"><span>Iniciar sesión</span></button>
        </div>

      </form>

      <!-- Enlace para registrarse si el usuario no tiene cuenta -->
      <p>Aún no tienes cuenta? <a href="register.php">Regístrate aquí</a></p>

    </article>

  </div>

</section>

<style>
  .art-login {
    max-width: 500px;
    margin: 4rem auto;
  }

  .form-login {
    display: flex;
    flex-flow: column wrap;
    gap: 1rem;
    padding: 1rem;

  }

  .form-login-card {
    display: flex;
    flex-flow: column nowrap;
  }
</style>