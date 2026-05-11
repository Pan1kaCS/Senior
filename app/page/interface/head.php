<?php
/** @var Graphics $graphics */
$title = $title ?? 'KS2 Servers';
/** @var string $themeCssHref */
$themeCssHref = (isset($graphics) && $graphics) ? $graphics->getThemeCssHref() : '/public/assets/themes/blackwhite/blackwhite.css';
?>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= h($title) ?></title>

<link rel="stylesheet" href="/public/assets/themes/blackwhite/base.css" />
    <link rel="stylesheet" href="<?= h($themeCssHref) ?>" />
    <link rel="stylesheet" href="/public/assets/themes/blackwhite/custom.css" />
    <link rel="stylesheet" href="/public/assets/themes/blackwhite/sidebar.css" />
</head>
<body>
<?php



