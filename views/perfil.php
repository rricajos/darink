<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'MySQL.php';
$user = MySQL::fetchAll("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
?>

<h1>Perfil de <?php echo $user[0]['username']; ?></h1>
<p>Nombre: <?php echo $user[0]['firstName']; ?> <?php echo $user[0]['lastName']; ?></p>
<p>Edad: <?php echo $user[0]['age']; ?></p>
<p>Género: <?php echo $user[0]['gender']; ?></p>
