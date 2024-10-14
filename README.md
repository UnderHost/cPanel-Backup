# Automated cPanel Backup Script (Updated for Jupiter Theme)

This repository contains a PHP script to automate the process of creating a full cPanel backup and transferring it via FTP. You can choose to back up either to your home directory or directly to an FTP server.

## Prerequisites

- PHP installed on your server.
- cURL enabled for PHP.
- FTP server to store the backup files.
- cPanel API token.

## Getting Started

### Clone the Repository

To use this script, clone the repository to your server:

```
git clone https://github.com/yourusername/cpanel-backup-script.git
cd cpanel-backup-script
```

### Setting Up cPanel API Token

1. Log in to your cPanel account.
2. Navigate to **Security** > **API Tokens**.
3. Click on **Create** to generate a new token.
4. Provide a name for the token and set the required permissions.
   - Make sure you grant sufficient permissions for backup operations.
5. Copy the generated token and replace the `$cpanel_token` value in the script.

### Configuration

Edit the `backup.php` script to include your cPanel and FTP details:

```
// cPanel credentials and details
$cpanel_host = 'your_cpanel_host.com';
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

```
php backup.php
```

## Automating Backups with Cron

To automate the backup process, you can set up a cron job to run the script at your desired interval.

### Setting Up Scheduled Tasks (Cron Jobs) via cPanel

1. Log in to your cPanel account.
2. Navigate to **Advanced** > **Cron Jobs**.
3. Under **Add New Cron Job**, set the desired frequency (e.g., to run every day at 2 AM, use `0 2 * * *`).
4. In the **Command** field, enter the following:

```
/usr/bin/php /path/to/backup.php >> /home/yourcpanelusername/backup.log 2>&1
```

Replace `/path/to/backup.php` with the full path to the script on your server, and `yourcpanelusername` with your cPanel username.

## Script Overview

The script uses cPanel's JSON API to create a backup. You can choose between backing up to your home directory (`homedir`) or to an FTP server (`ftp`).

- **cPanel credentials**: Provide your cPanel host, username, and API token.
- **FTP credentials**: Provide the details of the FTP server where you want to store the backup.
- **Backup settings**: You can switch between `ftp` and `homedir` for the backup destination.

### Example Configuration

```
$cpanel_host = 'your_cpanel_host.com';
$cpanel_user = 'your_cpanel_username';
$cpanel_token = 'your_api_token';

$ftp_host = 'ftp.example.com';
$ftp_user = 'ftp_username';
$ftp_pass = 'ftp_password';
$ftp_path = '/backups';

$backup_type = 'ftp'; // Change to 'homedir' if needed
```


## Troubleshooting

- **Error: SSL Verification**: If you encounter SSL verification issues, ensure that your PHP installation has the correct SSL certificates. You can also disable SSL verification by modifying the cURL options in the script (not recommended for production).
- **Backup Failure**: Ensure that the API token has the correct permissions and that the FTP credentials are accurate.


## Support

For any issues or inquiries, feel free to open an issue on this repository, or contact us at [UnderHost](https://underhost.com/business-backup.php).

## License

This project is open-source and available under the MIT License. See `LICENSE` file for details.
