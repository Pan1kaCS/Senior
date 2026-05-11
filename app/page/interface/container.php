<?php
/** @var Graphics $graphics */
?>
<main class="main">
    <div class="main__card">
        <?php
        // In this minimal kernel we let Graphics render modules indirectly.
        // If you extend Modules->getModulesForPage(), adjust Graphics::render() accordingly.
        $route = $route ?? '404';
        $moduleNames = $modules ?? [];
        foreach ($moduleNames as $m) {
            if ($graphics) {
                $graphics->renderModule($m);
            }
        }


        ?>
    </div>
</main>


