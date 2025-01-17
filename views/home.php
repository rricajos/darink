<?php
// home.php
?>

<section>
  <div class="supercont">
    

    <nav class="nav-home">


      <form action="index.php" method="post" class="nav-home-form">
        <input type="hidden" name="case" value="add">
        <button type="submit">
          <span>

            <i class="fas fa-plus"></i>
            <p>Añadir</p>
          </span>
        </button>
      </form>


      <form action="index.php" method="post" class="nav-home-form">
        <input type="hidden" name="case" value="list">


        <button type="submit">
          <span>
            <i class="fas fa-list"></i>
            <p>Ver todos</p>

          </span>
        </button>
      </form>



      <form action="index.php" method="post" class="nav-home-form">
        <input type="hidden" name="case" value="loggout">


        <button type="submit">
          <span>

            <i class="fas fa-sign-out-alt"></i>
            <p>Cerrar Session</p>

          </span>
        </button>
      </form>



      <form action="index.php" method="post" class="nav-home-form">
        <input type="hidden" name="case" value="profile">

        <button type="submit">
         <span>
         <i class="fas fa-user"></i>
         <p>Ver Perfil</p>
         </span>
        </button>
      </form>
    </nav>

  </div>
</section>
<style>
  .nav-home {
    display: grid;
    grid-template-columns: repeat(auto-fit,
        minmax(150px, 1fr));
    gap: 1rem;
    min-height: 90vh;

  }

  nav-home-form {
    width: 100%;
    display: flex;
    flex-flow: column nowrap;
  }


</style>