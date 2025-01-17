<section>
  <div class="supercont">


    <div class="login-container">
      <h2>Iniciar sesión</h2>

      <!-- Mostrar el mensaje de error si existe -->
      <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
      <?php endif; ?>

      <!-- Formulario de Login -->
      <form action="index.php" method="POST">
        <input type="hidden" name="case" value="loggin">

        <div class="form-group">
          <label for="nickname">Nombre de usuario</label>
          <input type="text" name="nickname" id="nickname" required>
        </div>

        <div class="form-group">
          <label for="password">Contraseña</label>
          <input type="password" name="password" id="password" required>
        </div>

        <button type="submit">Iniciar sesión</button>
      </form>

      <!-- Enlace para registrarse si el usuario no tiene cuenta -->
      <p>Aún no tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
    </div>

  </div>
</section>