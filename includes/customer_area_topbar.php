<?php
declare(strict_types=1);
?>
<section class="customer-area-backbar">
    <a
        class="storefront-back-button"
        href="<?= e(app_url('index.php')); ?>"
        onclick="if (window.history.length > 1) { history.back(); return false; }"
    >
        <span>Voltar</span>
        <svg viewBox="0 0 66 43" aria-hidden="true" focusable="false">
            <polygon points="39.58,4.46 44.11,0 66,21.5 44.11,43 39.58,38.54 56.94,21.5"></polygon>
            <polygon points="19.79,4.46 24.32,0 46.21,21.5 24.32,43 19.79,38.54 37.15,21.5"></polygon>
            <polygon points="0,4.46 4.53,0 26.42,21.5 4.53,43 0,38.54 17.36,21.5"></polygon>
        </svg>
    </a>
</section>