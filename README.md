# Automated cPanel Backup Script (Updated for Jupiter Theme)

This PHP script automates cPanel full backups using the Jupiter theme and uploads them to a remote FTP server. It also sends a notification email upon completion.

## Requirements

- PHP installed on your server.
- cPanel access with API token support.
- FTP server credentials to store the backup.

## Configuration

### 1. Update the Script

Edit the PHP script and replace the placeholders with your actual details:

- **cPanel credentials**:
  - `your_cpanel_username`: Your cPanel username.
  - `your_api_token`: The API token generated from your cPanel account.
  - `your_domain.com`: Your domain where cPanel is hosted.
  - `your_cpsession_id`: The current cPanel session ID. You can find this when logged into your cPanel in the URL.

- **FTP credentials**:
  - `your_ftp_username`: The username for your FTP account.
  - `your_ftp_password`: The password for your FTP account.
  - `your_ftp_host`: The FTP server’s hostname or IP address.

- **Email address**:
  - `youremail@example.com`: Your email address to receive notifications about the backup status.

### 2. Create a cPanel API Token

To generate an API token for authentication:

1. Log in to your cPanel account.
2. Go to **Security** > **API Tokens**.
3. Click **Create Token**.
4. Enter a name for your token (e.g., `BackupScriptToken`).
5. Set the token permissions to allow backups.
6. Copy the token and paste it into the script in place of `your_api_token`.

### 3. Set Up a Cron Job

To automate the backup process, set up a cron job to run the script at your preferred time interval.

1. Log in to your hosting control panel (cPanel or SSH).
2. Go to **Cron Jobs**.
3. Add a new cron job and schedule it. For example, to run the script every day at midnight:

0 0 * * * /usr/bin/php /path/to/your/backup.php

Make sure to replace `/path/to/your/backup.php` with the actual path to the PHP script on your server.

### 4. How to Use

1. Upload the script to your server (preferably outside of your public HTML directory for security).
2. Ensure the script has the necessary permissions to run.
3. You can run the script manually via the command line or set it up with a cron job for automation.

### 5. Security Considerations

1. Keep the script secure: Do not store this script in publicly accessible directories. It contains sensitive information like API tokens and FTP credentials.
2. Use SSL: If your cPanel supports SSL, ensure it is enabled to encrypt communication between the script and the cPanel server.


## Support

For any issues or inquiries, feel free to open an issue on this repository, or contact us at [UnderHost](https://underhost.com/business-backup.php).

## License

This project is open-source and available under the MIT License. See `LICENSE` file for details.
