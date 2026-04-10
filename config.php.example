<?php

/**
 * Beginner-friendly config for backup.php
 *
 * 1) Copy this file to config.php
 * 2) Fill only what you need
 * 3) Keep config.php private (chmod 640)
 */

return [

    // auto  = try UAPI, fallback to native backup
    // uapi  = force cPanel UAPI only
    // native = force PHP-only backup (works when UAPI is blocked)
    'engine' => 'auto',

    'cpanel' => [
        'host'       => 'your-domain.com',
        'user'       => 'cpanel_username',
        'token'      => '', // leave empty if host blocks UAPI
        'port'       => 2083,
        'timeout'    => 300,
        'ssl_verify' => true,
    ],

    // Used only in native mode (or when auto falls back to native)
    'native' => [
        // Usually /home/your_cpanel_user
        'home_dir'      => '/home/your_cpanel_user',

        // Folders/files to include (relative to home_dir or absolute path)
        'include_paths' => [
            'public_html',
            // 'mail',
        ],

        // Skip heavy/unneeded folders
        'exclude_paths' => [
            'tmp',
            '.trash',
            'logs',
        ],

        // Optional database dumps
        // Add one entry per DB if you want SQL backups
        'databases' => [
            // [
            //     'name' => 'cpuser_wpdb',
            //     'host' => 'localhost',
            //     'port' => 3306,
            //     'user' => 'cpuser_wpuser',
            //     'pass' => 'strong-password',
            // ],
        ],
    ],

    'storage' => [
        // Local backup files are created here first
        'local_dir'   => __DIR__ . '/artifacts',
        'temp_dir'    => __DIR__ . '/tmp',
        'keep_local'  => 7,
    ],

    // You can use one or many destinations.
    // Supported types: local, ftp, sftp, rclone, google_drive, dropbox, s3
    // google_drive/dropbox/s3 use rclone internally.
    'destinations' => [
        [
            'name'    => 'Local archive',
            'type'    => 'local',
            'enabled' => true,
        ],

        // FTP destination
        // [
        //     'name'    => 'Remote FTP',
        //     'type'    => 'ftp',
        //     'enabled' => false,
        //     'host'    => 'ftp.example.com',
        //     'port'    => 21,
        //     'user'    => 'ftp_user',
        //     'pass'    => 'ftp_password',
        //     'path'    => '/backups',
        //     'passive' => true,
        // ],

        // SFTP destination (requires PHP ssh2 extension)
        // [
        //     'name'    => 'Remote SFTP',
        //     'type'    => 'sftp',
        //     'enabled' => false,
        //     'host'    => 'sftp.example.com',
        //     'port'    => 22,
        //     'user'    => 'sftp_user',
        //     'pass'    => 'sftp_password',
        //     'path'    => '/backups',
        // ],

        // Google Drive via rclone remote named "gdrive"
        // [
        //     'name'    => 'Google Drive',
        //     'type'    => 'google_drive',
        //     'enabled' => false,
        //     'remote'  => 'gdrive',
        //     'path'    => 'cPanel-Backups',
        // ],

        // Dropbox via rclone remote named "dropbox"
        // [
        //     'name'    => 'Dropbox',
        //     'type'    => 'dropbox',
        //     'enabled' => false,
        //     'remote'  => 'dropbox',
        //     'path'    => 'cPanel-Backups',
        // ],

        // S3 via rclone remote named "s3"
        // [
        //     'name'    => 'Amazon S3',
        //     'type'    => 's3',
        //     'enabled' => false,
        //     'remote'  => 's3',
        //     'path'    => 'my-bucket/cpanel-backups',
        // ],
    ],

    'notification' => [
        'email'           => 'admin@your-domain.com',
        'from'            => 'backup@your-domain.com',
        'subject_success' => '[Backup] cPanel backup completed successfully',
        'subject_failure' => '[Backup] ALERT — cPanel backup failed',
    ],

    'logging' => [
        'enabled'  => true,
        'file'     => __DIR__ . '/logs/backup.log',
        'max_size' => 2097152,
        'keep'     => 5,
    ],

];
