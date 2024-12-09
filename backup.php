<?php
// cPanel credentials and details
$cpanel_host = 'your_cpanel_host.com'; // Replace with your cPanel domain or IP (use hostname if behind CloudFlare)
$cpanel_user = 'your_cpanel_username'; // Replace with your cPanel username
$cpanel_token = 'your_cpanel_api_token'; // Replace with your API token

// FTP credentials
$ftp_host = 'your_ftp_host.com';       // Replace with your FTP host
$ftp_user = 'your_ftp_username';      // Replace with your FTP username
$ftp_pass = 'your_ftp_password';      // Replace with your FTP password
$ftp_path = '/your_backup_directory'; // Path on the FTP server to store backups

// cPanel UAPI URL
$uapi_url = "https://$cpanel_host:2083/execute/Backup/fullbackup_to_ftp";

// Set up the backup parameters
$post_data = [
    'username' => $ftp_user,         // FTP username
    'host' => $ftp_host,             // FTP server host
    'email' => 'your_email@example.com', // Email for notifications
    'password' => $ftp_pass,         // FTP password
    'port' => '21',                  // FTP port
    'rdir' => $ftp_path              // Remote directory for backups
];

// Prepare the cURL session
$curl = curl_init();
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_URL, $uapi_url);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Authorization: cpanel $cpanel_user:$cpanel_token"
]);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));

// Execute the cURL request
$response = curl_exec($curl);
if (curl_errno($curl)) {
    echo 'Error: ' . curl_error($curl) . "\n";
} else {
    $decoded_response = json_decode($response, true);
    if (isset($decoded_response['errors'])) {
        echo "Backup request failed: " . implode(', ', $decoded_response['errors']) . "\n";
    } elseif (isset($decoded_response['status']) && $decoded_response['status'] == 1) {
        echo "Backup request successfully initiated!\n";
    } else {
        echo "Unknown error: " . json_encode($decoded_response) . "\n";
    }
}

// Close the cURL session
curl_close($curl);
?>
