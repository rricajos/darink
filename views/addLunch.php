<?php
// addLunch.php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<p>No has iniciado sesión.</p>";
    exit();
}
?>

<h1>Registrar Comida</h1>

<form id="lunch-form">
    <label for="date">Fecha:</label>
    <input type="date" id="date" name="date" required>

    <label for="time">Hora:</label>
    <input type="time" id="time" name="time" required>

    <label for="location">Ubicación:</label>
    <select id="location" name="location">
        <option value="at my home">En mi casa</option>
        <option value="at somebody home">En casa de alguien</option>
        <option value="outside">Fuera</option>
    </select>

    <button type="submit">Registrar</button>
</form>

<script>
document.getElementById('lunch-form').addEventListener('submit', function (event) {
    event.preventDefault();

    const data = {
        date: document.getElementById('date').value,
        time: document.getElementById('time').value,
        location: document.getElementById('location').value
    };

    // Enviar datos al servidor para guardar el nuevo lunch
    sendData('addLunchAction.php', data)
        .then(response => {
            if (response.success) {
                alert("Comida registrada correctamente.");
                loadPage("lunches");  // Recargar la lista de comidas
            } else {
                alert("Error al registrar la comida.");
            }
        });
});
</script>
