# Automated cPanel Backup Script (Version 0.2)

[Backup Security Shield](https://underhost.com/images/backup-shield.png) *Ensure your website's safety with automated backups*

This PHP script automates full cPanel backups and transfers them to your preferred destination (FTP server or home directory). Designed for reliability and security, it's perfect for website owners and administrators.

## Table of Contents
- [Why Backups Matter](#why-backups-matter)
- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Automation with Cron](#automation-with-cron)
- [Troubleshooting](#troubleshooting)
- [Security Considerations](#security-considerations)
- [Support](#support)
- [License](#license)

## Why Backups Matter

Regular backups protect against:
- Server failures 🖥️
- Cyber attacks 🛡️
- Human errors 👨💻
- Malware infections 🦠

**Recommended Reading:**  
[The Importance of Regular Backups for Your Website](https://underhost.com/blog/the-importance-of-regular-backups-for-your-website/)

**Need a managed solution?**  
Explore our [Business Backup Service](https://underhost.com/business-backup.php)

## Features

✅ **Flexible Backup Destinations**  
- FTP/SFTP servers  
- Home directory  

✅ **Enhanced Security**  
- Encrypted API connections  
- Configurable SSL verification  

✅ **Automation Ready**  
- Detailed logging  
- Email notifications  
- Cron job compatible  

✅ **Smart Configuration**  
- Log rotation  
- Connection timeouts  
- Passive FTP mode  

## Prerequisites

- PHP 7.4+ with cURL extension
- cPanel access with API permissions
- FTP server credentials
- Server with cron access

## Installation

### Option 1: Using cPanel File Manager
1. Log in to your cPanel account
2. Navigate to **Files** → **File Manager**
3. Create a new directory outside `public_html` (e.g., `backups`)
4. Upload these files to the new directory:
   - `backup.php`
   - `config.php`

### Option 2: Using FTP Client
1. Connect to your server via FTP
2. Create a directory outside `public_html` (e.g., `/backups`)
3. Upload the script files to this directory

### Set Permissions via File Manager:
1. Right-click on the backup directory
2. Select **Change Permissions**
3. Set to `750` (Owner: Read/Write/Execute, Group: Read/Execute)
4. Set config.php permissions to `640`

## Configuration

Edit `config.php` using cPanel File Manager:

1. Right-click on `config.php`
2. Select **Code Edit**
3. Replace with your configuration:

```
<?php
return [
    'cpanel' => [
        'host' => 'yourdomain.com',
        'user' => 'cpanel_username',
        'token' => 'API_TOKEN_HERE',
        'port' => 2083
    ],
    'ftp' => [
        'host' => 'backup.server.com',
        'user' => 'ftp_username',
        'pass' => 'ftp_password',
        'path' => '/backups/daily',
        'passive' => true
    ],
    'backup' => [
        'type' => 'ftp',
        'notify_email' => 'you@email.com'
    ]
];
```

## Automation with Cron

### Set Up via cPanel:
1. Navigate to **Advanced** → **Cron Jobs**
2. Add new cron job with your preferred schedule
3. Command to run:

```
/usr/local/bin/php /home/your_cpanel_user/backups/backup.php
```

**Common Schedules:**
- Daily: `0 2 * * *` (Runs at 2 AM daily)
- Weekly: `0 2 * * 0` (Runs at 2 AM every Sunday)

## Troubleshooting

### Common Issues

**API Authentication Failed**  
```
Error: Invalid API credentials (Status: 0)
```
Solution:
1. Verify token permissions
2. Check token hasn't expired
3. Ensure correct cPanel username

**FTP Connection Issues**  
```
Error: Could not connect to FTP server
```
Solution:
1. Test FTP credentials manually
2. Verify firewall settings
3. Try passive mode: `'passive' => true`

## Security Considerations

🔒 **Best Practices:**
1. Store script outside web root
2. Restrict file permissions:
   - Scripts: `640`
   - Directory: `750`
3. Use strong FTP passwords
4. Monitor backup logs regularly

## Support

For assistance:  
📝 [Open a GitHub Issue](https://github.com/UnderHost/cPanel-Backup/issues)  
📧 [Contact UnderHost Support](https://underhost.com/contact)

## License

MIT License - See [LICENSE](LICENSE) for full details.

---

*Maintained by [UnderHost](https://underhost.com) - Reliable Hosting Solutions*
```
