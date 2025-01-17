document.addEventListener("DOMContentLoaded", function() {
    // Variables para los enlaces
    const homeLink = document.getElementById('home');
    const profileLink = document.getElementById('profile');
    const lunchesLink = document.getElementById('lunches');
    const content = document.getElementById('content');

    // Función para cargar el contenido de una página usando AJAX
    function loadContent(page) {
        const xhr = new XMLHttpRequest();
        xhr.open("GET", page, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                content.innerHTML = xhr.responseText; // Cargar el contenido en el div#content
            }
        };

        xhr.send();
    }

    // Eventos de clic para cargar el contenido sin recargar la página
    homeLink.addEventListener('click', function() {
        loadContent('home.php'); // Cargar página de inicio
    });

    profileLink.addEventListener('click', function() {
        loadContent('perfil.php'); // Cargar página de perfil
    });

    lunchesLink.addEventListener('click', function() {
        loadContent('lunches.php'); // Cargar página de comidas
    });

    // Cargar la página de inicio por defecto al cargar la página
    loadContent('home.php');
});
