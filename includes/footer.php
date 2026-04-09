<?php
declare(strict_types=1);
?>
    <script>
        (function () {
            function removeSplash() {
                var splash = document.querySelector('[data-splash]');
                if (splash) {
                    splash.parentNode.removeChild(splash);
                }
            }

            function dismissFlashes() {
                var flashes = document.querySelectorAll('[data-flash]');

                if (!flashes.length) {
                    return;
                }

                window.setTimeout(function () {
                    flashes.forEach(function (flash) {
                        flash.classList.add('is-hiding');

                        window.setTimeout(function () {
                            if (flash && flash.parentNode) {
                                flash.parentNode.removeChild(flash);
                            }
                        }, 360);
                    });
                }, 3000);
            }

            window.addEventListener('load', removeSplash);
            window.addEventListener('load', dismissFlashes);
            setTimeout(removeSplash, 5000);
        })();
    </script>
    <script src="<?= e(asset_url('assets/js/app.js')); ?>"></script>
</body>
</html>
