<?php
// home.php
?>

<section>
  <div class="supercont">
    

    <nav class="homenav">


      <form action="index.php" method="post">
        <input type="hidden" name="case" value="add">
        <button type="submit">
          <i class="fas fa-plus"></i>
          <p>Añadir</p>
        </button>
      </form>


      <form action="index.php" method="post">
        <input type="hidden" name="case" value="list">


        <button type="submit">
          <i class="fas fa-list"></i>
          <p>Ver todos</p>
        </button>
      </form>



      <form action="index.php" method="post">
        <input type="hidden" name="case" value="loggout">


        <button type="submit">
          <i class="fas fa-sign-out-alt"></i>
          <p>Cerrar Session</p>
        </button>
      </form>



      <form action="index.php" method="post">
        <input type="hidden" name="case" value="profile">

        <button type="submit">
          <i class="fas fa-user"></i>
          <p>Ver Perfil</p>
        </button>
      </form>
    </nav>

  </div>
</section>
<style>
  .homenav {
    display: grid;
    grid-template-columns: repeat(auto-fit,
        minmax(150px, 1fr));
    gap: 1rem;

  }

  .homenav>form {
    background-color: #aaa;
    border-radius: 1rem;


  }

  .homenav>form>button {
    background-color: transparent;
    border: none;
    outline: none;
    width: 100%;
    height: 100%;
    border-radius: 1rem;
    padding: 1rem;
    display: flex;
    flex-flow: column nowrap;
    align-items: center;
    justify-content: center;
    gap: 1rem;
  }

  .homenav>form>button:hover {
    cursor: pointer;
   background-color: rebeccapurple;
   color: white;
  }

  .homenav>form>button>i {
    font-size: 3rem;
  }
</style>