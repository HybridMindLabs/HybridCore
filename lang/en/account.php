<?php

return [
    /* ── Page / tabs ── */
    'my_account' => 'My Account',
    'account_subtitle' => 'Manage your profile, security and preferences.',
    'tab_profile' => 'Profile',
    'tab_security' => 'Password & 2FA',
    'tab_sessions' => 'Active Sessions',
    'tab_prefs' => 'Preferences',
    'tab_connected' => 'Connected Accounts',

    /* ── Profile header ── */
    'view_public_profile' => 'View public profile',
    '2fa_on' => '2FA On',
    'member_since' => 'Member since',
    'last_seen' => 'Last seen',
    'sign_out' => 'Sign out',

    /* ── Email notice ── */
    'email_not_verified' => 'Email not verified',
    'verify_now' => 'Verify now',

    /* ── Profile form ── */
    'profile' => 'Profile',
    'profile_hint' => 'Your public-facing information.',
    'display_name' => 'Display name',
    'username' => 'Username',
    'username_shown_as' => 'shown as @username',
    'username_hint' => 'Letters, numbers and underscores only.',
    'email' => 'Email address',
    'email_unverified' => '— not verified',
    'email_change_note' => 'Changing your email requires re-verification.',
    'bio' => 'Bio',
    'location' => 'Location',
    'website' => 'Website',
    'optional' => 'optional',
    'save_profile' => 'Save Profile',

    /* ── Security / password ── */
    'security' => 'Security',
    'security_hint' => 'Keep your account safe with a strong password.',
    'current_password' => 'Current password',
    'new_password' => 'New password',
    'confirm_new_password' => 'Confirm new password',
    'change_password' => 'Change Password',

    /* ── 2FA ── */
    '2fa_title' => 'Two-Factor Authentication',
    '2fa_enabled_hint' => 'Your account has an extra layer of protection.',
    '2fa_disabled_hint' => 'Add a second step when signing in.',
    '2fa_enabled' => 'Enabled',
    '2fa_disabled' => 'Disabled',
    '2fa_description' => 'Two-factor authentication adds a second verification step during sign-in using an authenticator app (Google Authenticator, Authy, etc.). Even if your password is compromised, your account stays safe.',
    '2fa_setup' => 'Set up two-factor authentication',
    '2fa_step1' => '1. Scan with your authenticator app',
    '2fa_step1_hint' => 'Open Google Authenticator, Authy, or any TOTP app and scan the QR code.',
    '2fa_manual_key' => 'Or enter the setup key:',
    '2fa_step2' => '2. Enter the 6-digit code from your app',
    '2fa_verify' => 'Verify & Enable',
    '2fa_cancel' => 'Cancel',
    '2fa_recovery_title' => 'Recovery Codes',
    '2fa_recovery_hint' => 'Use these if you lose access to your authenticator app. Each code can only be used once.',
    '2fa_recovery_reveal' => 'Click the eye icon to reveal your recovery codes.',
    '2fa_confirm_pw' => 'Confirm password',
    '2fa_regenerate' => 'Regenerate',
    '2fa_regen_confirm' => 'Regenerate recovery codes? Current codes stop working immediately.',
    '2fa_disable_title' => 'Disable Two-Factor Authentication',
    '2fa_disable_hint' => 'You will only need your password to sign in. This reduces your account security.',
    '2fa_disable_btn' => 'Disable 2FA',
    '2fa_confirm_your_pw' => 'Confirm your password',

    /* ── Sessions ── */
    'sessions_title' => 'Active Sessions',
    'sessions_hint' => 'These devices are currently signed in to your account.',
    'sessions_one' => ':count session',
    'sessions_many' => ':count sessions',
    'sessions_this_device' => 'This device',
    'sessions_unknown_ip' => 'Unknown IP',
    'sessions_revoke' => 'Revoke session',
    'sessions_empty' => 'No active sessions found.',
    'sessions_sign_out_others' => 'Sign out of all other sessions',
    'sessions_confirm_hint' => 'Confirm your password to sign out of all other active sessions.',
    'sessions_your_pw' => 'Your password',
    'sessions_revoke_all' => 'Revoke All',
    'sessions_cancel' => 'Cancel',
    'sessions_just_now' => 'Just now',
    'sessions_minutes_ago' => ':n m ago',
    'sessions_hours_ago' => ':n h ago',
    'sessions_days_ago' => ':n d ago',
    'sessions_on' => 'on',

    /* ── Preferences ── */
    'preferences' => 'Preferences',
    'preferences_hint' => 'Language and regional settings.',
    'language' => 'Language',
    'timezone' => 'Timezone',
    'save_preferences' => 'Save Preferences',

    /* ── Connected accounts ── */
    'connected_accounts' => 'Connected Accounts',
    'connected_accounts_hint' => 'Link third-party accounts for faster sign-in.',
    'no_providers' => 'No sign-in providers are available yet.',
    'connected' => 'Connected',
    'not_connected' => 'Not connected',
    'connect' => 'Connect',
    'disconnect' => 'Disconnect',
    'unavailable' => 'Unavailable',
    'disconnect_confirm' => 'Disconnect this account?',

    /* ── Flash messages ── */
    'profile_updated' => 'Profile updated.',
    'profile_updated_verify' => 'Profile updated. Please verify your new email address.',
    'preferences_updated' => 'Preferences updated.',
    'password_changed' => 'Password changed.',
    'wrong_current_password' => 'Your current password is incorrect.',
    'account_disconnected' => 'Account disconnected.',
    'account_connected' => 'Account connected.',
    'oauth_already_linked' => 'That account is already linked to another user.',

    /* ── Data export ── */
    'export_queued' => 'Your data export is being prepared — you will get a notification with a download link.',
    'export_ready' => 'Your data export is ready.',
    'export_download' => 'Download export',
];
