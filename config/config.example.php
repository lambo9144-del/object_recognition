<?php

// Copy this file to config.php and edit it. Never commit config.php to GitHub.
//
// Create an admin password hash with:
//   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT);"

return [
    'admin_user' => 'admin',
    'admin_password_hash' => '',

    // Optional: API keys can also be entered on the Settings page instead.
    'gemini_api_key' => '',
    'agnes_api_key' => '',

    // Optional scan history (import database/database.sql first)
    'db_enabled' => false,
    'db_host' => 'localhost',
    'db_name' => 'ai_ar_scanner',
    'db_user' => 'root',
    'db_pass' => '',

    'timezone' => 'UTC',
];
