<?php
// Configurations
$cpuser = "your_cpanel_username";
$cppass = "your_cpanel_password";
$domain = "your_domain.com";
$backup_type = 'homedir';  // Change to 'ftp' for FTP backup
$ftp_server = "ftp.yourbackupserver.com";
$ftp_username = "your_ftp_username";
$ftp_password = "your_ftp_password";
$email = "your_email@example.com";

// Function to initiate backup
function initiate_backup() {
    global $cpuser, $cppass, $domain, $backup_type, $ftp_server, $ftp_username, $ftp_password;

    $auth = base64_encode($cpuser.":".$cppass);
    $url = "https://".$domain.":2083/execute/Backup/";

    if ($backup_type == 'homedir') {
        $url .= "fullbackup_to_homedir";
    } else {
        $url .= "fullbackup_to_ftp";
        $url .= "?ftp_server=".$ftp_server."&ftp_user=".$ftp_username."&ftp_pass=".$ftp_password;
    }

    $options = [
        "http" => [
            "header" => "Authorization: Basic $auth",
        ],
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) { 
        return "Error occurred";
    }

    return $response;
}

// Function to send email notification
function send_notification($message) {
    global $email;
    mail($email, "Backup Completion Notification", $message);
}

// Main Execution
$response = initiate_backup();
send_notification($response);
?>

