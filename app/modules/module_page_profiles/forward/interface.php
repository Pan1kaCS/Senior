<?php
// Контент профиля. Движок рендерит только этот interface.php.
// Подключаем функции модуля.
require_once __DIR__ . '/functions.php';

$steamid64 = profiles_get_steamid_from_route($route ?? '', $_GET);
$stats = profiles_fetch_stats($steamid64);

$displayName = trim(($stats['nickname'] ?? '') !== '' ? ($stats['nickname']) : 'Игрок');


$avatar = $stats['avatar'] ?: 'https://avatars.steamstatic.com/' . ($steamid64 ?? '') . '_full.jpg';
$onlineText = 'Был(а) в игре - неизвестно';
$xpPercent = (int)($stats['xp_progress_percent'] ?? 0);
$xpValue = (int)($stats['xp_value'] ?? 0);
$xpMax = (int)($stats['xp_max'] ?? 0);
$level = (int)($stats['level'] ?? 0);
?>

<link rel="stylesheet" href="/public/assets/themes/blackwhite/blackwhite.css" />
<link rel="stylesheet" href="/app/modules/module_page_profiles/assets/css/profile.css" />

<script defer src="/app/modules/module_page_profiles/assets/js/profile.js"></script>

<div id="routerpages">
    <div>
        <div id="profile-cover" class="sc-TBHXX dRQQro"></div>
        <div id="profile" class="other page_profile_new">
            <div id="profile-main">
                <div id="information">
                    <img class="avatar" src="<?= profiles_e($avatar) ?>" alt="<?= profiles_e($displayName) ?>">
                </div>
                <div id="date" class="user-theme__none">
                    <div class="sc-eVQVnD qZxEo"></div>
                    <div class="sc-cbkKJc jDeeYA">
                        <div id="name_player"><?= profiles_e($displayName) ?></div>
                        <div id="online_player"><?= profiles_e($onlineText) ?></div>
                    </div>
                    <span class="xp__wrapper">
                        <div class="xp-bar">
                            <div class="loyalty__progressBox--2KEX">
                                <div class="loyalty__progressBlock--W_he">
                                    <div class="loyalty__progressBar--3fut">
                                        <div class="loyalty__progressBg--2f0j tippy" id="profile_user_width_xp" style="width: <?= $xpPercent ?>%;"></div>
                                    </div>
                                </div>
                                <div class="loyalty__giftInfo--2qi6"><span class="loyalty__giftSum--1Rr9"><span class="viMoneyValue--1vFp"><span class="viMoneyValue__text--1EVB" id="profile_user_rank"> </span></span></span></div>
                            </div>
                        </div>
                        <div class="xp-bar-info">
                            <div class="xp-bar-info-1" id="profile_user_value_new"><?= $xpValue ?></div>
                            <div class="xp-bar-info-3">&nbsp;/&nbsp;</div>
                            <div class="xp-bar-info-2" id="profile_user_max_xp"><?= $xpMax ?> XP<span>(Уровень <?= $level ?>)</span></div>
                        </div>
                    </span>
                </div>
            </div>
            <div class="profile-socials-wrapper"></div>
        </div>
        <div>
            <nav id="profile-nav">
                <div class="sc-gpGtIc kfZpaa active"><a aria-current="page" class="active" href="/profiles/<?= profiles_e($steamid64 ?? '') ?>">Профиль</a></div>
            </nav>
            <div id="profile-content">
                <div class="profile-content-block" id="profile-stats" style="display: grid;">
                    <div class="profile-stats-grid">
                        <div><strong id="p-profile-top"><?= (int)($stats['place'] ?? 0) ?></strong>
                            <p>Место</p>
                        </div>
                        <div><strong id="p-profile-faceit-country"><img data-tippy-placement="bottom" src="<?= profiles_e($stats['country_flag'] ?? '/storage/img/flags/ru.svg') ?>"></strong>
                            <p>Страна</p>
                        </div>
                        <div><strong id="p-profile-faceit-elo"><?= (int)($stats['faceit_elo'] ?? 0) ?></strong>
                            <p>Faceit ELO</p>
                        </div>
                        <div><strong id="p-profile-kd"><?= (float)($stats['kd'] ?? 0) ?></strong>
                            <p>K/D</p>
                        </div>
                        <div><strong id="p-profile-kills"><?= (int)($stats['kills'] ?? 0) ?></strong>
                            <p>Убийства</p>
                        </div>
                        <div><strong id="p-profile-hs"><?= (int)($stats['headshot_percent'] ?? 0) ?>%</strong>
                            <p>Убийств в голову</p>
                        </div>
                        <div><strong id="p-profile-hours"><?= (int)($stats['hours'] ?? 0) ?></strong>
                            <p>Время игры</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>