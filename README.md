# cPanel Backup Script (Beginner Friendly)
# Automated cPanel Backup Script (Version 0.4)

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![ShellCheck](https://github.com/UnderHost/one-domain/actions/workflows/shellcheck.yml/badge.svg)](https://github.com/UnderHost/one-domain/actions/workflows/shellcheck.yml)
[![Version](https://img.shields.io/badge/version-2026.0.4-green.svg)](https://github.com/UnderHost/one-domain/blob/main/docs/CHANGELOG.md)
[![UnderHost](https://img.shields.io/badge/by-UnderHost.com-orange)](https://underhost.com)

This PHP script automates full cPanel backups and transfers them to your preferred destination (FTP server or home directory). Designed for reliability and security, it's perfect for website owners and administrators.

Automated backups for **normal cPanel users** (no root access required).

This script is designed for shared hosting accounts where:
- You can run cron jobs in cPanel
- Your files are outside `public_html`
- Your host may block cPanel UAPI tokens

---

## What this script can do

1. **Try cPanel UAPI first** (if token is available)
2. **Fallback to native PHP backup** when UAPI is blocked
3. Create local backup archive (`.zip` or `.tar.gz`)
4. Upload backups to one or more destinations:
   - Local (keep in your account)
   - FTP
   - SFTP (if `ssh2` extension exists)
   - Google Drive (via `rclone`)
   - Dropbox (via `rclone`)
   - S3-compatible storage (via `rclone`)
5. Send success/failure email
6. Rotate logs and keep a limited number of local backups

---

## Requirements

- PHP 7.4+
- cPanel cron access
- Script stored outside `public_html`
- Optional:
  - `curl` extension for UAPI mode
  - `ftp` extension for FTP upload
  - `ssh2` extension for SFTP upload
  - `rclone` binary for Google Drive / Dropbox / S3 (and generic cloud remotes)

---

## Folder layout (recommended)

```text
/home/your_cpanel_user/
├── backups/
│   ├── backup.php
│   ├── config.php
│   ├── config.php.example
│   ├── logs/
│   ├── artifacts/
│   └── tmp/
└── public_html/
```

---

## Quick Start (non-technical)

### 1) Install files

Upload all script files to a folder like:

`/home/your_cpanel_user/backups/`

> Do not place this script inside `public_html`.

### 2) Configure

```bash
cp config.php.example config.php
```

Open `config.php` and edit:
- `engine` (`auto` is recommended)
- `native.home_dir`
- `native.include_paths`
- `native.databases` (optional but recommended)
- `destinations`
- notification email

### 3) Run once manually

```bash
/usr/local/bin/php /home/your_cpanel_user/backups/backup.php --dry-run
/usr/local/bin/php /home/your_cpanel_user/backups/backup.php
```

### 4) Add cron job in cPanel

Daily at 2 AM:

```cron
0 2 * * * /usr/local/bin/php /home/your_cpanel_user/backups/backup.php >> /dev/null 2>&1
```

---

## Engine modes

### `engine = auto` (recommended)
- Uses UAPI if available and working.
- Automatically falls back to native backup if UAPI fails.

### `engine = uapi`
- Force cPanel UAPI backup trigger.
- Fails if host blocks API token or endpoint.

### `engine = native`
- Never calls UAPI.
- Uses pure PHP backup logic:
  - Copies selected folders
  - Dumps selected MySQL databases
  - Packages everything into one archive

---

## Destinations

You can enable **multiple** destinations at once.

### Local
Keeps archives in `storage.local_dir`.

### FTP
Uses PHP FTP extension.

### SFTP
Uses PHP `ssh2` extension.

### Google Drive / Dropbox / S3
Uses `rclone` remote config. Set destination type to `google_drive`, `dropbox`, `s3`, or `rclone`.

Example destination:

```php
[
  'name' => 'Google Drive',
  'type' => 'google_drive',
  'enabled' => true,
  'remote' => 'gdrive',
  'path' => 'cPanel-Backups',
]
```

---

## Database backup notes

In native mode, add each database in `native.databases`:

```php
[
  'name' => 'cpuser_wpdb',
  'host' => 'localhost',
  'port' => 3306,
  'user' => 'cpuser_wpuser',
  'pass' => 'your-db-password',
]
```

If `databases` is empty, only files are backed up.

---

## Logs and retention

- Logs are saved to `logging.file`
- Log rotation: `logging.max_size` + `logging.keep`
- Local backup retention: `storage.keep_local`

---

## Troubleshooting

### UAPI not allowed by hosting company
Use:

```php
'engine' => 'native'
```

### Google Drive / Dropbox / S3 upload fails
- Make sure `rclone` is installed on your hosting environment
- Confirm the remote name in config (`remote`)
- Test command in terminal (if shell access exists):
  `rclone lsd <remote>:`

### SFTP upload fails
Your hosting PHP may not include `ssh2` extension. Use FTP or rclone-based destination instead.

### Script works manually but not in cron
Use absolute paths in cron and config.

---

```
/home/your_cpanel_user/
├── backups/           ← store the script here, NOT inside public_html
│   ├── backup.php
│   ├── config.php     ← your private config (never commit this)
│   ├── config.php.example
│   └── logs/          ← created automatically on first run
└── public_html/
```

## Security tips

- Keep script and config outside `public_html`
- Use file permissions: `chmod 640 config.php`
- Use strong destination passwords
- Do not commit real credentials to Git
- Test restore process regularly

---

## Why Backups Matter
---

**Recommended Reading:**  
[The Importance of Regular Backups for Your Website](https://underhost.com/blog/the-importance-of-regular-backups-for-your-website/)

**Need a managed solution?**  
Explore our [Business Backup Service](https://underhost.com/business-backup.php)

## License

MIT

## Support

For assistance:  
- 📝 [Open a GitHub Issue](https://github.com/UnderHost/cPanel-Backup/issues)
- 📧 [UnderHost Support](https://underhost.com/contact)
- 🌐 [UnderHost — Managed Backup Solutions](https://underhost.com/business-backup.php)

---
