<?php
// Render installer page
// Resolve app dir reliably from this file location.
// __DIR__ = .../app/modules/install_db/forward
// App root = .../app
$appDir = dirname(__DIR__, 3);
include $appDir . '/page/install/install_db.php';



