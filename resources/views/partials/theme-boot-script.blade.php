{{--
    Aplica el tema y el estado del sidebar (colapsado o no) guardados antes
    del primer paint, evitando parpadeo. wire:navigate no recarga el
    documento -- solo reemplaza el <body> -- así que esto debe volver a
    correr en cada navegación (evento livewire:navigated), o el <html>
    nuevo que trae el servidor no sabría que el usuario había elegido modo
    oscuro / sidebar colapsado y la app volvería a su estado por defecto.
--}}
<script>
    function aplicarTemaCeba() {
        var stored = localStorage.getItem('ceba-theme');
        var isDark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', isDark);
    }

    function aplicarSidebarCeba() {
        // wire:navigate reemplaza el <body> entero: por un instante el
        // sidebar vuelve a su ancho expandido por defecto antes de que este
        // script corra y le devuelva la clase 'sidebar-collapsed'. Sin
        // "sidebar-no-transition" esa corrección se ve como si el sidebar se
        // desplegara y volviera a colapsar de golpe, porque hereda la misma
        // transición suave pensada para cuando el usuario hace clic.
        var aside = document.querySelector('.sidebar-desktop');
        if (aside) {
            aside.classList.add('sidebar-no-transition');
        }

        document.documentElement.classList.toggle('sidebar-collapsed', localStorage.getItem('ceba-sidebar-collapsed') === '1');

        if (aside) {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    aside.classList.remove('sidebar-no-transition');
                });
            });
        }
    }

    aplicarTemaCeba();
    aplicarSidebarCeba();
    document.addEventListener('livewire:navigated', aplicarTemaCeba);
    document.addEventListener('livewire:navigated', aplicarSidebarCeba);
</script>
