<?php
// lightForm.php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<p>No has iniciado sesión.</p>";
    exit();
}

// Obtener todos los lunches para el usuario
require_once 'MySQL.php';
$lunches = MySQL::fetchAll("SELECT * FROM lunches WHERE userId = ?", [$_SESSION['user_id']]);
?>

<h1>Evaluar Comidas</h1>

<?php if (count($lunches) > 0): ?>
    <form id="light-form">
        <label for="lunchId">Selecciona una comida:</label>
        <select id="lunchId" name="lunchId">
            <?php foreach ($lunches as $lunch): ?>
                <option value="<?php echo $lunch['id']; ?>"><?php echo $lunch['location']; ?> el <?php echo $lunch['date']; ?> a las <?php echo $lunch['time']; ?></option>
            <?php endforeach; ?>
        </select>

        <label for="trafficLight">Elige una evaluación:</label>
        <select id="trafficLight" name="trafficLight">
            <option value="green">Verde (Bien)</option>
            <option value="yellow">Amarillo (Neutral)</option>
            <option value="red">Rojo (Mal)</option>
        </select>

        <label for="trafficLightNote">Comentarios:</label>
        <textarea id="trafficLightNote" name="trafficLightNote"></textarea>

        <button type="submit">Evaluar</button>
    </form>

    <script>
    document.getElementById('light-form').addEventListener('submit', function (event) {
        event.preventDefault();

        const data = {
            lunchId: document.getElementById('lunchId').value,
            trafficLight: document.getElementById('trafficLight').value,
            trafficLightNote: document.getElementById('trafficLightNote').value
        };

        // Enviar los datos al backend
        sendData('addLight.php', data)
            .then(response => {
                if (response.success) {
                    alert("Evaluación guardada.");
                } else {
                    alert("Error al guardar la evaluación.");
                }
            });
    });
    </script>
<?php else: ?>
    <p>No tienes comidas registradas para evaluar.</p>
<?php endif; ?>
