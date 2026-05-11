<?php
declare(strict_types=1);

require_once __DIR__ . '/steam_openid.php';

function auth_steam_start(): void {
    steam_oid_redirect_to_login();
}

function auth_steam_callback(): void {
    steam_openid_handle_callback_and_login();
}

function auth_logout(): void {
    steam_logout();
}

