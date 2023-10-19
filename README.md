# cPanel Backup Script

This repository contains a PHP script to automate backups in cPanel without requiring SSH access. The script can perform backups to the home directory or send them via FTP to an off-site server.

## Features

- Full backup to home directory or off-site via FTP.
- Email notification upon backup completion.
- Easy setup with minimal configuration required.

## Setup

1. Clone this repository or download the `backup.php` script.
2. Open the `backup.php` script and update the configurations with your cPanel and FTP (if needed) credentials.
3. Specify the backup type by setting the `$backup_type` variable to either `'homedir'` for home directory backup or `'ftp'` for FTP backup.
4. Upload the script to your server or run it on your local machine.
5. Schedule the script using cPanel's Cron Job feature or any other scheduling tool you prefer.

## Usage

1. By default, the script is set to perform a full backup to the home directory. Change the `$backup_type` variable to `'ftp'` if you want to send the backup via FTP.
2. Schedule the script to run at your desired intervals using the Cron Job feature in cPanel or any other scheduling tool you prefer.

## Support

For any issues or inquiries, feel free to open an issue on this repository, or contact us at [UnderHost](https://underhost.com/business-backup.php).

## License

This project is open-source and available under the MIT License. See `LICENSE` file for details.
