<?php
// cPanel credentials and details
$cpanel_host = 'your_cpanel_host.com'; // Replace with your cPanel domain or IP (use hostname if you use CloudFlare)
$cpanel_user = 'your_cpanel_username'; // Replace with your cPanel username
$cpanel_token = 'your_api_token';      // Replace with your API token

// FTP credentials
$ftp_host = 'ftp.example.com';         // Replace with your FTP host
$ftp_user = 'ftp_username';           // Replace with your FTP username
$ftp_pass = 'ftp_password';           // Replace with your FTP password
$ftp_path = '/backups';               // Path on the FTP server to store backups

// Backup settings
$backup_type = 'ftp'; // Choose between 'homedir' or 'ftp'

// cPanel API URL
$backup_url = "https://$cpanel_host:2083/json-api/cpanel";

// Set up the backup parameters
$post_data = [
    'cpanel_jsonapi_version' => '2',
    'cpanel_jsonapi_module' => 'Backup',
    'cpanel_jsonapi_func' => 'fullbackup',
    'email' => '', // Set your email if you want to receive backup completion notifications
];

if ($backup_type === 'ftp') {
    $post_data['dest'] = 'ftp';
    $post_data['server'] = $ftp_host;
    $post_data['user'] = $ftp_user;
    $post_data['pass'] = $ftp_pass;
    $post_data['port'] = '21';
    $post_data['rdir'] = $ftp_path;
} else if ($backup_type === 'homedir') {
    $post_data['dest'] = 'homedir';
}

// Prepare the cURL session
$curl = curl_init();
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_URL, $backup_url);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Authorization: cpanel $cpanel_user:$cpanel_token"
]);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);

// Execute the cURL request
$response = curl_exec($curl);
if (curl_errno($curl)) {
    echo 'Error:' . curl_error($curl) . "\n";
} else {
    $decoded_response = json_decode($response, true);
    if (isset($decoded_response['cpanelresult']['data'][0]['result']) && $decoded_response['cpanelresult']['data'][0]['result'] == 1) {
        echo "Backup request successfully initiated!\n";
    } else {
        echo "Backup request failed: " . json_encode($decoded_response) . "\n";
    }
}

// Close the cURL session
curl_close($curl);
?>
