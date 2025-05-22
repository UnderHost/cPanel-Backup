<?php
/**
 * cPanel to FTP Backup Script
 * 
 * This script initiates a full cPanel backup and transfers it to a remote FTP server.
 * It includes error handling, logging, and email notifications.
 */

// ==================== CONFIGURATION ====================
// cPanel credentials and details
$config = [
    'cpanel' => [
        'host' => 'your_cpanel_host.com',      // cPanel hostname or IP
        'user' => 'your_cpanel_username',      // cPanel username
        'token' => 'your_cpanel_api_token',   // cPanel API token
        'port' => 2083,                       // cPanel port (usually 2083 for SSL)
        'timeout' => 300                      // cURL timeout in seconds
    ],
    
    'ftp' => [
        'host' => 'your_ftp_host.com',         // FTP server hostname or IP
        'user' => 'your_ftp_username',         // FTP username
        'pass' => 'your_ftp_password',         // FTP password
        'port' => 21,                          // FTP port (usually 21)
        'path' => '/your_backup_directory',    // Remote directory for backups
        'passive' => true                      // Use passive mode (recommended)
    ],
    
    'notification' => [
        'email' => 'your_email@example.com',   // Email for notifications
        'subject_success' => 'Backup Success', // Email subject for success
        'subject_failure' => 'Backup Failed'   // Email subject for failure
    ],
    
    'logging' => [
        'enabled' => true,                     // Enable logging
        'file' => 'backup_log.txt',             // Log file name
        'max_size' => 1048576                   // Max log file size (1MB)
    ]
];

// ==================== FUNCTIONS ====================
/**
 * Log messages to file and/or output
 */
function log_message($message, $is_error = false) {
    global $config;
    
    $timestamp = date('Y-m-d H:i:s');
    $formatted_msg = "[$timestamp] " . ($is_error ? "ERROR: " : "") . "$message\n";
    
    // Output to console
    echo $formatted_msg;
    
    // Log to file if enabled
    if ($config['logging']['enabled']) {
        // Rotate log if it's too big
        if (file_exists($config['logging']['file']) && 
            filesize($config['logging']['file']) > $config['logging']['max_size']) {
            rename($config['logging']['file'], $config['logging']['file'] . '.old');
        }
        
        file_put_contents($config['logging']['file'], $formatted_msg, FILE_APPEND);
    }
}

/**
 * Send email notification
 */
function send_notification($subject, $message) {
    global $config;
    
    $headers = "From: backup-script@" . $_SERVER['SERVER_NAME'] . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($config['notification']['email'], $subject, $message, $headers);
}

/**
 * Validate configuration
 */
function validate_config() {
    global $config;
    
    $errors = [];
    
    if (empty($config['cpanel']['host']) || empty($config['cpanel']['user']) || empty($config['cpanel']['token'])) {
        $errors[] = "cPanel configuration is incomplete";
    }
    
    if (empty($config['ftp']['host']) || empty($config['ftp']['user']) || empty($config['ftp']['pass'])) {
        $errors[] = "FTP configuration is incomplete";
    }
    
    if (!filter_var($config['notification']['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid notification email address";
    }
    
    return $errors;
}

// ==================== MAIN SCRIPT ====================
try {
    log_message("Starting cPanel backup process");
    
    // Validate configuration
    $config_errors = validate_config();
    if (!empty($config_errors)) {
        throw new Exception("Configuration errors:\n" . implode("\n", $config_errors));
    }
    
    // Prepare the backup request
    $post_data = [
        'username' => $config['ftp']['user'],
        'host' => $config['ftp']['host'],
        'email' => $config['notification']['email'],
        'password' => $config['ftp']['pass'],
        'port' => $config['ftp']['port'],
        'rdir' => $config['ftp']['path'],
        'passive' => $config['ftp']['passive'] ? 1 : 0
    ];
    
    $uapi_url = "https://{$config['cpanel']['host']}:{$config['cpanel']['port']}/execute/Backup/fullbackup_to_ftp";
    
    // Initialize cURL
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $uapi_url,
        CURLOPT_HTTPHEADER => [
            "Authorization: cpanel {$config['cpanel']['user']}:{$config['cpanel']['token']}"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // For self-signed certificates
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => $config['cpanel']['timeout'],
        CURLOPT_CONNECTTIMEOUT => 30
    ]);
    
    // Execute the request
    $response = curl_exec($curl);
    
    if (curl_errno($curl)) {
        throw new Exception("cURL error: " . curl_error($curl));
    }
    
    $decoded_response = json_decode($response, true);
    
    if (isset($decoded_response['errors']) && !empty($decoded_response['errors'])) {
        $error_message = "API errors:\n";
        foreach ($decoded_response['errors'] as $error) {
            $error_message .= "- " . $error . "\n";
        }
        throw new Exception($error_message);
    }
    
    if (!isset($decoded_response['status']) || $decoded_response['status'] != 1) {
        throw new Exception("Unexpected API response: " . json_encode($decoded_response, JSON_PRETTY_PRINT));
    }
    
    // Success
    $success_message = "Backup request successfully initiated! Response: " . json_encode($decoded_response, JSON_PRETTY_PRINT);
    log_message($success_message);
    send_notification($config['notification']['subject_success'], $success_message);
    
} catch (Exception $e) {
    $error_message = "Backup failed: " . $e->getMessage();
    log_message($error_message, true);
    send_notification($config['notification']['subject_failure'], $error_message);
    
} finally {
    if (isset($curl)) {
        curl_close($curl);
    }
    
    log_message("Backup process completed");
}
?>
