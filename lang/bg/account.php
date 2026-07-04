<?php

return [
    /* ── Page / tabs ── */
    'my_account' => 'Моят акаунт',
    'account_subtitle' => 'Управлявай профила, сигурността и предпочитанията си.',
    'tab_profile' => 'Профил',
    'tab_security' => 'Парола & 2FA',
    'tab_sessions' => 'Активни сесии',
    'tab_prefs' => 'Предпочитания',
    'tab_connected' => 'Свързани акаунти',

    /* ── Profile header ── */
    'view_public_profile' => 'Виж публичния профил',
    '2fa_on' => '2FA вкл.',
    'member_since' => 'Член от',
    'last_seen' => 'Последно виждан',
    'sign_out' => 'Изход',

    /* ── Email notice ── */
    'email_not_verified' => 'Имейлът не е потвърден',
    'verify_now' => 'Потвърди сега',

    /* ── Profile form ── */
    'profile' => 'Профил',
    'profile_hint' => 'Вашата публична информация.',
    'display_name' => 'Показвано име',
    'username' => 'Потребителско име',
    'username_shown_as' => 'показва се като @username',
    'username_hint' => 'Само букви, цифри и долна черта.',
    'email' => 'Имейл адрес',
    'email_unverified' => '— не е потвърден',
    'email_change_note' => 'Смяната на имейла изисква повторно потвърждение.',
    'bio' => 'За мен',
    'location' => 'Местоположение',
    'website' => 'Уебсайт',
    'optional' => 'по желание',
    'save_profile' => 'Запази профила',

    /* ── Security / password ── */
    'security' => 'Сигурност',
    'security_hint' => 'Пазете акаунта си с силна парола.',
    'current_password' => 'Текуща парола',
    'new_password' => 'Нова парола',
    'confirm_new_password' => 'Потвърдете новата парола',
    'change_password' => 'Смени паролата',

    /* ── 2FA ── */
    '2fa_title' => 'Двуфакторна автентикация',
    '2fa_enabled_hint' => 'Акаунтът ви има допълнителна защита.',
    '2fa_disabled_hint' => 'Добавете втора стъпка при влизане.',
    '2fa_enabled' => 'Включена',
    '2fa_disabled' => 'Изключена',
    '2fa_description' => 'Двуфакторната автентикация добавя втора стъпка при влизане чрез приложение (Google Authenticator, Authy и др.). Дори паролата ви да е компрометирана, акаунтът остава защитен.',
    '2fa_setup' => 'Настрои двуфакторна автентикация',
    '2fa_step1' => '1. Сканирайте с приложението си',
    '2fa_step1_hint' => 'Отворете Google Authenticator, Authy или друго TOTP приложение и сканирайте QR кода.',
    '2fa_manual_key' => 'Или въведете ключа ръчно:',
    '2fa_step2' => '2. Въведете 6-цифрения код от приложението',
    '2fa_verify' => 'Потвърди и включи',
    '2fa_cancel' => 'Отказ',
    '2fa_recovery_title' => 'Кодове за възстановяване',
    '2fa_recovery_hint' => 'Използвайте ги при загуба на достъп до приложението. Всеки код може да се ползва само веднъж.',
    '2fa_recovery_reveal' => 'Натиснете иконата на окото, за да видите кодовете.',
    '2fa_confirm_pw' => 'Потвърдете паролата',
    '2fa_regenerate' => 'Генерирай нови',
    '2fa_regen_confirm' => 'Генериране на нови кодове? Текущите кодове спират да работят веднага.',
    '2fa_disable_title' => 'Изключване на двуфакторна автентикация',
    '2fa_disable_hint' => 'При влизане ще ви трябва само парола. Това намалява сигурността на акаунта.',
    '2fa_disable_btn' => 'Изключи 2FA',
    '2fa_confirm_your_pw' => 'Потвърдете паролата си',

    /* ── Sessions ── */
    'sessions_title' => 'Активни сесии',
    'sessions_hint' => 'Тези устройства са влезли в акаунта ви.',
    'sessions_one' => ':count сесия',
    'sessions_many' => ':count сесии',
    'sessions_this_device' => 'Това устройство',
    'sessions_unknown_ip' => 'Неизвестен IP',
    'sessions_revoke' => 'Прекрати сесията',
    'sessions_empty' => 'Няма намерени активни сесии.',
    'sessions_sign_out_others' => 'Изход от всички други сесии',
    'sessions_confirm_hint' => 'Потвърдете паролата за изход от всички останали активни сесии.',
    'sessions_your_pw' => 'Вашата парола',
    'sessions_revoke_all' => 'Прекрати всички',
    'sessions_cancel' => 'Отказ',
    'sessions_just_now' => 'Преди малко',
    'sessions_minutes_ago' => 'преди :n мин.',
    'sessions_hours_ago' => 'преди :n ч.',
    'sessions_days_ago' => 'преди :n дни',
    'sessions_on' => 'на',

    /* ── Preferences ── */
    'preferences' => 'Предпочитания',
    'preferences_hint' => 'Език и регионални настройки.',
    'language' => 'Език',
    'timezone' => 'Часова зона',
    'save_preferences' => 'Запази предпочитанията',

    /* ── Connected accounts ── */
    'connected_accounts' => 'Свързани акаунти',
    'connected_accounts_hint' => 'Свържете външни акаунти за по-бърз вход.',
    'no_providers' => 'Все още няма налични доставчици за вход.',
    'connected' => 'Свързан',
    'not_connected' => 'Не е свързан',
    'connect' => 'Свържи',
    'disconnect' => 'Премахни',
    'unavailable' => 'Недостъпен',
    'disconnect_confirm' => 'Премахване на връзката с този акаунт?',

    /* ── Flash messages ── */
    'profile_updated' => 'Профилът е обновен.',
    'profile_updated_verify' => 'Профилът е обновен. Моля, потвърдете новия си имейл адрес.',
    'preferences_updated' => 'Предпочитанията са запазени.',
    'password_changed' => 'Паролата е сменена.',
    'wrong_current_password' => 'Текущата ви парола е неправилна.',
    'account_disconnected' => 'Връзката с акаунта е премахната.',
    'account_connected' => 'Акаунтът е свързан.',
    'oauth_already_linked' => 'Този акаунт вече е свързан с друг потребител.',

    /* ── Data export ── */
    'export_queued' => 'Експортът на данните ти се подготвя — ще получиш известие с линк за сваляне.',
    'export_ready' => 'Експортът на данните ти е готов.',
    'export_download' => 'Свали експорта',
];
