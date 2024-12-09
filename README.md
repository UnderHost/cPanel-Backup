
# Automated cPanel Backup Script (Version 124+)

This repository contains a PHP script to automate the process of creating a full cPanel backup and transferring it via FTP. You can choose to back up either to your home directory or directly to an FTP server.

## Why Regular Backups Are Crucial

Backing up your website regularly is essential to protect your data from unexpected events like server failures, cyberattacks, or accidental data loss. Learn more about the importance of backups in our blog post: [The Importance of Regular Backups for Your Website](https://underhost.com/blog/the-importance-of-regular-backups-for-your-website/).

If you’re looking for a comprehensive and reliable backup solution, check out our [business backup service](https://underhost.com/business-backup.php).

---

## Prerequisites

- PHP installed on your server.
- cURL enabled for PHP.
- FTP server to store the backup files.
- cPanel API token.

## Getting Started

### Download the Repository

To use this script, download the repository as a ZIP file and set it up securely:

1. Visit the GitHub repository: [https://github.com/UnderHost/cPanel-Backup](https://github.com/UnderHost/cPanel-Backup).
2. Click on the **Code** button and select **Download ZIP**.
3. Extract the ZIP file on your computer or server.
4. Place the script in a secure directory outside the `public_html` folder to ensure it is not publicly accessible.

### Setting Up cPanel API Token

1. Log in to your cPanel account.
2. Navigate to **Security** > **API Tokens**.
3. Click on **Create** to generate a new token.
4. Provide a name for the token and set the required permissions.
   - Ensure you grant sufficient permissions for backup operations.
5. Copy the generated token and replace the `$cpanel_token` value in the script.

### Configuration

Edit the `backup.php` script to include your cPanel and FTP details:

```
// cPanel credentials and details
$cpanel_host = 'your_cpanel_host.com'; // Use the hostname if your domain is behind CloudFlare
$cpanel_user = 'your_cpanel_username';
$cpanel_token = 'your_api_token';

// FTP credentials
$ftp_host = 'ftp.example.com';
$ftp_user = 'ftp_username';
$ftp_pass = 'ftp_password';
$ftp_path = '/backups';  // Path on the FTP server to store backups

// Backup settings
$backup_type = 'ftp'; // Choose between 'homedir' or 'ftp'
```

---

## Automating Backups with Cron

To automate the backup process, you can set up a cron job to run the script at your desired interval.

### Setting Up Scheduled Tasks (Cron Jobs) via cPanel

1. Log in to your cPanel account.
2. Navigate to **Advanced** > **Cron Jobs**.
3. Under **Add New Cron Job**, set the desired frequency (e.g., to run every day at 2 AM, use `0 2 * * *`).
4. In the **Command** field, enter the following:

```
/usr/bin/php -q /path/to/backup.php >> /home/yourcpanelusername/backup.log 2>&1
```

Replace `/path/to/backup.php` with the full path to the script on your server, and `yourcpanelusername` with your cPanel username.

---

## Script Overview

The script uses cPanel's JSON API to create a backup. You can choose between backing up to your home directory (`homedir`) or to an FTP server (`ftp`).

### Example Configuration

```
$cpanel_host = 'underhost.com';
$cpanel_user = 'theuser';
$cpanel_token = 'your_api_token';

$ftp_host = 'ftp.underhost.com';
$ftp_user = 'ftpBACKUP_username';
$ftp_pass = 'ftpFTP_password';
$ftp_path = '/backups/daily';

$backup_type = 'ftp'; // Change to 'homedir' if needed
```

---

## Troubleshooting

- **Error: SSL Verification**: If you encounter SSL verification issues, ensure that your PHP installation has the correct SSL certificates. You can also disable SSL verification by modifying the cURL options in the script (not recommended for production).
- **Backup Failure**: Ensure that the API token has the correct permissions and that the FTP credentials are accurate.

---

## Additional Resources

- **Why Backups Matter:** Read our blog post on [The Importance of Regular Backups for Your Website](https://underhost.com/blog/the-importance-of-regular-backups-for-your-website/).
- **Looking for a Backup Service?** Check out our [business backup solution](https://underhost.com/business-backup.php) for automated, secure backups.

---

## Support

For any issues or inquiries, feel free to open an issue on this repository or contact us via [UnderHost](https://underhost.com).

## License

This project is open-source and available under the MIT License. See the `LICENSE` file for details.


