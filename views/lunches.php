<?php
// lunches.php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<p>No has iniciado sesión.</p>";
    exit();
}

// Obtener los lunches registrados por el usuario
require_once 'MySQL.php';
$lunches = MySQL::fetchAll("SELECT * FROM lunches WHERE userId = ?", [$_SESSION['user_id']]);

?>

<h1>Mis Comidas Registradas</h1>

<?php if (count($lunches) > 0): ?>
    <ul>
        <?php foreach ($lunches as $lunch): ?>
            <li><?php echo $lunch['location']; ?> el <?php echo $lunch['date']; ?> a las <?php echo $lunch['time']; ?></li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No has registrado comidas.</p>
<?php endif; ?>
