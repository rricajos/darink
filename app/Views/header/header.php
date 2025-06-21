<?php
$current = uri_string(); // o current_url() si usas rutas absolutas
?>

<header role="banner" class="main-header">
<h1><?= esc($title ?? 'Sin título') ?></h1>


  <nav role="navigation" aria-label="Menú principal" class="main-nav">
    <ul id="menu" class="nav-list">
      <li>
        <a href="/" class="pushable" <?= $current === '' ? 'aria-current="page"' : '' ?>>
          <span class="shadow"></span>
          <span class="edge"></span>
          <span class="front">
            <span class="nav-icon">🚀</span>
            <span class="nav-label">Iniciar</span>
          </span>
        </a>
      </li>
      <li>
        <a href="/serv" class="pushable" <?= $current === 'serv' ? 'aria-current="page"' : '' ?>>
          <span class="shadow"></span>
          <span class="edge"></span>
          <span class="front">
            <span class="nav-icon">📒</span>
            <span class="nav-label">Agenda</span>
          </span>
        </a>
      </li>
      <li>
        <a href="/blog" class="pushable" <?= $current === 'blog' ? 'aria-current="page"' : '' ?>>
          <span class="shadow"></span>
          <span class="edge"></span>
          <span class="front">
            <span class="nav-icon">📰</span>
            <span class="nav-label">Blog</span>
          </span>
        </a>
      </li>
      <li>
        <a href="/auth" class="pushable" <?= $current === 'auth' ? 'aria-current="page"' : '' ?>>
          <span class="shadow"></span>
          <span class="edge"></span>
          <span class="front">
            <span class="nav-icon">🔑</span>
            <span class="nav-label">Acceder</span>
          </span>
        </a>
      </li>
    </ul>
  </nav>
</header>

<style>
  .main-header {
    background-color: #f9f9f9;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding-bottom: 16vh;
  }

  .app-title {
    font-size: 2rem;
    margin: 1rem 0;
  }

  .main-nav {
    width: 100%;
    background-color: none;
    color: white;
  }

  .nav-list {
    display: flex;
    justify-content: space-evenly;
    list-style: none;
    margin: 0;
    padding: 0.5rem 0.25rem;
    gap: 0.25rem;
  }

  .nav-list li {
    flex: 1;
    text-align: center;
  }

  .pushable {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    padding: 0;
    text-decoration: none;
    border-radius: 12px;
    position: relative;
    cursor: pointer;
    transition: filter 250ms;
  }

  .pushable .shadow {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.25);
    transform: translateY(2px);
    transition: transform 600ms cubic-bezier(0.2, 1.5, 0.3, 1.2);
    z-index: 1;
  }

  .pushable .edge {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 12px;
    background: linear-gradient(to left, #444 0%, #666 8%, #666 92%, #444 100%);
    z-index: 2;
  }

  .pushable .front {
    position: relative;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    border-radius: 12px;
    padding: 8px 0;
    font-weight: bold;
    color: white;
    background: #555;
    transform: translateY(-4px);
    transition: transform 600ms cubic-bezier(0.2, 1.5, 0.3, 1.2);
    z-index: 3;
  }

  .pushable:hover {
    filter: brightness(110%);
  }

  .pushable:hover .front {
    transform: translateY(-10px);
    transition: transform 400ms cubic-bezier(0.2, 1.6, 0.3, 1.2);
  }

  .pushable:active .front {
    transform: translateY(-1px);
    transition: transform 50ms ease-out;
  }

  .pushable:hover .shadow {
    transform: translateY(6px);
    transition: transform 400ms cubic-bezier(0.2, 1.6, 0.3, 1.2);
  }

  .pushable:active .shadow {
    transform: translateY(0);
    transition: transform 50ms ease-out;
  }

  .pushable[aria-current="page"] .front {
    background: #0077ff;
  }

  .pushable[aria-current="page"] .edge {
    background: linear-gradient(to left,
        #0056c4 0%,
        #0077ff 8%,
        #0077ff 92%,
        #0056c4 100%);
  }

  .nav-icon {
    font-size: 1.2rem;
    transition: font-size 0.3s ease;
  }

  .nav-label {
    font-size: 0.75rem;
  }

  @media (max-width: 720px) {
    .main-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      border-top: 1px solid #ccc;
      z-index: 1000;
    }

    .nav-icon {
      font-size: 2rem;
    }

    .nav-label {
      font-size: 0.65rem;
    }

    .pushable .front {
      padding: 10px 0;
    }
  }
</style>