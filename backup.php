<?php
// ********* THE FOLLOWING ITEMS NEED TO BE CONFIGURED *********

// Info required for cPanel access
$cpuser = "your_cpanel_username"; // Username used to login to cPanel
$api_token = "your_api_token"; // API Token from cPanel (create in cPanel -> Security -> API Tokens)
$domain = "your_domain.com"; // Domain name where cPanel is run


// Info required for FTP host
$ftpuser = "your_ftp_username"; // Username for FTP account
$ftppass = "your_ftp_password"; // Password for FTP account
$ftphost = "your_ftp_host"; // Full hostname or IP address for FTP host

// Notification information
$notifyemail = "youremail@example.com"; // Email address to send results

// *********** NO CONFIGURATION ITEMS BELOW THIS LINE *********

// Set the backup destination to FTP
$backup_type = "ftp";

// Construct the API URL with the session ID and Jupiter theme
$url = $domain . "/frontend/" . $theme . "/backup/dofullbackup.html";

// API request parameters
$params = [
    'dest' => $backup_type,
    'email' => $notifyemail,
    'server' => $ftphost,
    'user' => $ftpuser,
    'pass' => $ftppass
];

// Set the headers with the API token for authentication
$headers = [
    "Authorization: cpanel $cpuser:$api_token"
];

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute cURL request
$response = curl_exec($ch);

// Check for errors
if ($response === FALSE) {
    die("cURL Error: " . curl_error($ch));
}

// Close cURL connection
curl_close($ch);

// Display the response or send an email notification
echo $response;

// Send notification email
mail($notifyemail, "Backup Completion Notification", $response);

?>
