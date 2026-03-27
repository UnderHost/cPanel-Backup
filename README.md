# Automated cPanel Backup Script (Version 0.3)

# UnderHost — cPanel Backup Script

> Automated full cPanel backups via the UAPI, delivered to an FTP server or your home directory.  
> Cron-friendly · Secure · Logged · Email notifications

---

## Features

- **FTP or home directory** backup destinations
- **cPanel UAPI** authentication via API tokens (no password storage)
- **Email notifications** on success and failure
- **Rotating log files** — configurable size and history depth
- **Dry-run mode** — test your config without triggering a real backup
- **Proper exit codes** — integrates cleanly with cron monitoring
- **CLI-only execution** — cannot be triggered via a web request

---

## Requirements

| Requirement | Minimum |
|---|---|
| PHP | 7.4+ |
| PHP extension | `curl` |
| cPanel | Any version supporting UAPI |
| Access | cPanel API Token |

---

## Installation

### 1. Download the files

```bash
# Via git
git clone https://github.com/UnderHost/cPanel-Backup.git
cd cPanel-Backup

# Or download the zip from GitHub and extract it
```

### 2. Place files outside `public_html`

```
/home/your_cpanel_user/
├── backups/           ← store the script here, NOT inside public_html
│   ├── backup.php
│   ├── config.php     ← your private config (never commit this)
│   ├── config.php.example
│   └── logs/          ← created automatically on first run
└── public_html/
```

### 3. Set permissions

```bash
chmod 750 /home/your_cpanel_user/backups/
chmod 640 /home/your_cpanel_user/backups/config.php
```

### 4. Configure

```bash
cp config.php.example config.php
nano config.php   # or edit via cPanel File Manager → Code Edit
```

Fill in your cPanel credentials, FTP details, and notification email.  
See [Configuration](#configuration) below for all options.

---

## Configuration

Copy `config.php.example` to `config.php` and edit it:

```php
<?php
return [

    'cpanel' => [
        'host'       => 'your-domain.com',       // cPanel hostname (no https://)
        'user'       => 'cpanel_username',
        'token'      => 'YOUR_API_TOKEN',         // WHM → Manage API Tokens
        'port'       => 2083,                     // 2083 = SSL (recommended)
        'timeout'    => 300,
        'ssl_verify' => true,
    ],

    'backup' => [
        'type' => 'ftp',                          // 'ftp' or 'homedir'
    ],

    'ftp' => [
        'host'    => 'ftp.backup-server.com',
        'user'    => 'ftp_username',
        'pass'    => 'ftp_password',
        'port'    => 21,
        'path'    => '/backups',
        'passive' => true,
    ],

    'notification' => [
        'email' => 'admin@your-domain.com',
        'from'  => 'backup@your-domain.com',
    ],

    'logging' => [
        'enabled'  => true,
        'file'     => __DIR__ . '/logs/backup.log',
        'max_size' => 2097152,  // 2 MB before rotation
        'keep'     => 5,        // rotated files to retain
    ],

];
```

### Generating a cPanel API Token

1. Log in to **cPanel**
2. Go to **Security → Manage API Tokens**
3. Click **Create**
4. Name it (e.g. `backup-script`) and click **Create**
5. Copy the token into `config.php` → `cpanel.token`

> **Tip:** Tokens are scoped per account and don't require storing your cPanel password.

---

## Usage

### Manual run

```bash
/usr/local/bin/php /home/your_cpanel_user/backups/backup.php
```

### Dry run (no backup triggered — tests config and logs only)

```bash
/usr/local/bin/php /home/your_cpanel_user/backups/backup.php --dry-run
```

### Custom config path

```bash
/usr/local/bin/php backup.php --config=/etc/mybackup/config.php
```

### Exit codes

| Code | Meaning |
|------|---------|
| `0`  | Success |
| `1`  | Failure (config error, API error, cURL error) |

---

## Automation with Cron

### Set up in cPanel

1. Log in to cPanel → **Advanced → Cron Jobs**
2. Add a new cron job

### Recommended schedules

| Frequency | Cron expression | Notes |
|-----------|----------------|-------|
| Daily     | `0 2 * * *`    | Runs at 2:00 AM every day |
| Weekly    | `0 2 * * 0`    | Runs at 2:00 AM every Sunday |
| Monthly   | `0 2 1 * *`    | Runs at 2:00 AM on the 1st |

### Cron command

```
0 2 * * *  /usr/local/bin/php /home/YOUR_USER/backups/backup.php >> /dev/null 2>&1
```

> The script writes to its own log file — piping to `/dev/null` is fine.  
> Remove `>> /dev/null 2>&1` temporarily if you want cPanel to email you the raw output.

---

## Logs

Log files are written to `logs/backup.log` (relative to the script, or the absolute path in config).

```
[2025-03-27 02:00:01] [INFO]  ════════════════════════════════════════
[2025-03-27 02:00:01] [INFO]  UnderHost cPanel Backup — starting
[2025-03-27 02:00:01] [INFO]  Backup type    : ftp
[2025-03-27 02:00:01] [INFO]  Destination    : FTP (ftp.backup-server.com:21)
[2025-03-27 02:00:02] [INFO]  Sending backup request to cPanel UAPI…
[2025-03-27 02:00:03] [INFO]  Backup request accepted by cPanel
[2025-03-27 02:00:03] [INFO]  Completed in 2.14s
```

Logs rotate automatically when they exceed `max_size`, keeping the last `keep` copies.

---

## Troubleshooting

### `Config file not found`
```
[FATAL] Config file not found: /home/user/backups/config.php
```
Run: `cp config.php.example config.php` then edit it.

---

### `Missing required config: cPanel API token`
You haven't filled in `cpanel.token`. Generate one in cPanel → Security → Manage API Tokens.

---

### `cURL error #6: Could not resolve host`
The `cpanel.host` value is wrong or DNS is unavailable from this server.

---

### `cPanel API returned HTTP 401`
Your API token is invalid, expired, or the username doesn't match. Regenerate the token in cPanel.

---

### `cURL error` — SSL issues
If your cPanel uses a self-signed certificate, set `'ssl_verify' => false` in config.  
Do **not** disable SSL verification on production servers unless absolutely necessary.

---

### FTP connection issues
- Test your FTP credentials manually with an FTP client first
- Try `'passive' => true` (most firewalls require passive mode)
- Check that the remote `path` exists on the FTP server

---

## Security

- **Store config outside `public_html`** — it contains credentials
- **Set `chmod 640 config.php`** — readable only by owner and group
- **Use API tokens** instead of cPanel passwords
- **Keep `ssl_verify` enabled** in production
- **Rotate FTP passwords** periodically
- Add `config.php` and `logs/` to `.gitignore`

---

## .gitignore

```gitignore
config.php
logs/
*.log
*.log.*
```

---

## Support

- 📝 [Open a GitHub Issue](https://github.com/UnderHost/cPanel-Backup/issues)
- 📧 [UnderHost Support](https://underhost.com/contact)
- 🌐 [UnderHost — Managed Backup Solutions](https://underhost.com/business-backup.php)

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

*Maintained by [UnderHost](https://underhost.com) — Reliable Hosting Solutions*
