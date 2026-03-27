<?php

/**
 * cPanel Backup — Configuration File
 *
 * Copy this file to config.php and fill in your values.
 * NEVER commit config.php to version control — add it to .gitignore.
 *
 * Permissions: chmod 640 config.php
 */

return [

    // ─── cPanel ──────────────────────────────────────────────────────────────
    'cpanel' => [
        // Your cPanel hostname or server IP (no trailing slash, no https://)
        'host'    => 'your-domain.com',

        // cPanel username
        'user'    => 'cpanel_username',

        // cPanel API Token (WHM → Manage API Tokens → Generate Token)
        // Recommended scopes: Backup/fullbackup_to_ftp or Backup/fullbackup_to_homedir
        'token'   => 'YOUR_CPANEL_API_TOKEN_HERE',

        // cPanel HTTPS port (2083 = SSL, 2082 = non-SSL — SSL strongly recommended)
        'port'    => 2083,

        // Request timeout in seconds (large accounts may need 300+)
        'timeout' => 300,

        // Set to true only if cPanel uses a self-signed / untrusted certificate.
        // Leave false in production — disabling SSL verification is a security risk.
        'ssl_verify' => true,
    ],

    // ─── Backup Destination ──────────────────────────────────────────────────
    'backup' => [
        // Destination type: 'ftp' or 'homedir'
        //   ftp     – transfers the backup to a remote FTP server (see 'ftp' section)
        //   homedir – stores the backup in your cPanel home directory
        'type' => 'ftp',
    ],

    // ─── FTP (used when backup.type = 'ftp') ─────────────────────────────────
    'ftp' => [
        'host'    => 'ftp.backup-server.com',
        'user'    => 'ftp_username',
        'pass'    => 'ftp_password',
        'port'    => 21,
        'path'    => '/backups',      // Remote directory — must exist on FTP server
        'passive' => true,            // Passive mode — recommended behind firewalls
    ],

    // ─── Notifications ───────────────────────────────────────────────────────
    'notification' => [
        // Email address to receive success/failure reports
        'email'           => 'admin@your-domain.com',

        // Sender address (must be a valid address on this server to pass SPF/DKIM)
        'from'            => 'backup@your-domain.com',

        'subject_success' => '[Backup] cPanel backup completed successfully',
        'subject_failure' => '[Backup] ALERT — cPanel backup failed',
    ],

    // ─── Logging ─────────────────────────────────────────────────────────────
    'logging' => [
        'enabled'  => true,

        // Absolute path is strongly recommended so cron jobs write to the right place.
        // Example: '/home/cpanel_username/backup_logs/backup.log'
        // Defaults to the directory this config lives in if left as a relative path.
        'file'     => __DIR__ . '/logs/backup.log',

        // Maximum log file size in bytes before rotation (default 2 MB)
        'max_size' => 2097152,

        // How many rotated log files to keep (e.g. backup.log.1, backup.log.2 …)
        'keep'     => 5,
    ],

];
