<?php
 
declare(strict_types=1);
 
/**
 * UnderHost — cPanel Backup Script
 *
 * Initiates a full cPanel backup via the UAPI and transfers it to a
 * remote FTP server or stores it in the cPanel home directory.
 *
 * Usage:  php backup.php [--config=/path/to/config.php] [--dry-run]
 * Cron:   0 2 * * *  /usr/local/bin/php /home/user/backups/backup.php
 *
 * @version 1.0.0
 * @link    https://github.com/UnderHost/cPanel-Backup
 * @license MIT
 */
 
// ─── Bootstrap ───────────────────────────────────────────────────────────────
 
// Ensure we are running from CLI only
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.' . PHP_EOL);
}
 
// Minimum requirements check
if (!extension_loaded('curl')) {
    fwrite(STDERR, '[FATAL] The PHP cURL extension is required but not loaded.' . PHP_EOL);
    exit(1);
}
 
if (PHP_VERSION_ID < 70400) {
    fwrite(STDERR, '[FATAL] PHP 7.4 or higher is required (current: ' . PHP_VERSION . ').' . PHP_EOL);
    exit(1);
}
 
// ─── Parse CLI arguments ──────────────────────────────────────────────────────
 
$options    = getopt('', ['config:', 'dry-run']);
$configPath = $options['config'] ?? __DIR__ . '/config.php';
$dryRun     = isset($options['dry-run']);
 
// ─── Load configuration ───────────────────────────────────────────────────────
 
if (!file_exists($configPath)) {
    fwrite(STDERR, "[FATAL] Config file not found: {$configPath}" . PHP_EOL);
    fwrite(STDERR, "Copy config.php.example to config.php and fill in your credentials." . PHP_EOL);
    exit(1);
}
 
$config = require $configPath;
 
if (!is_array($config)) {
    fwrite(STDERR, "[FATAL] Config file must return an array." . PHP_EOL);
    exit(1);
}
 
// ─── Ensure logs directory exists ────────────────────────────────────────────
 
$logFile = $config['logging']['file'] ?? (__DIR__ . '/logs/backup.log');
$logDir  = dirname($logFile);
 
if (!is_dir($logDir) && !mkdir($logDir, 0750, true) && !is_dir($logDir)) {
    fwrite(STDERR, "[FATAL] Cannot create log directory: {$logDir}" . PHP_EOL);
    exit(1);
}
 
// ─── Functions ────────────────────────────────────────────────────────────────
 
/**
 * Write a timestamped message to STDOUT and optionally to a log file.
 */
function log_message(string $message, bool $isError = false): void
{
    global $config;
 
    $level     = $isError ? 'ERROR' : 'INFO';
    $timestamp = date('Y-m-d H:i:s');
    $line      = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
 
    // Write to appropriate stream
    $isError ? fwrite(STDERR, $line) : fwrite(STDOUT, $line);
 
    if (!($config['logging']['enabled'] ?? true)) {
        return;
    }
 
    $logFile = $config['logging']['file'] ?? (__DIR__ . '/logs/backup.log');
    $maxSize = (int)($config['logging']['max_size'] ?? 2097152);
    $keep    = max(1, (int)($config['logging']['keep'] ?? 5));
 
    // Rotate if file exceeds max size
    if (file_exists($logFile) && filesize($logFile) >= $maxSize) {
        rotate_logs($logFile, $keep);
    }
 
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
 
/**
 * Rotate log files, keeping the last $keep copies.
 * e.g. backup.log.4 → backup.log.5, …, backup.log → backup.log.1
 */
function rotate_logs(string $logFile, int $keep): void
{
    // Remove oldest
    $oldest = "{$logFile}.{$keep}";
    if (file_exists($oldest)) {
        unlink($oldest);
    }
 
    // Shift existing rotated files
    for ($i = $keep - 1; $i >= 1; $i--) {
        $src = "{$logFile}.{$i}";
        $dst = "{$logFile}." . ($i + 1);
        if (file_exists($src)) {
            rename($src, $dst);
        }
    }
 
    // Rotate current log
    if (file_exists($logFile)) {
        rename($logFile, "{$logFile}.1");
    }
}
 
/**
 * Send an email notification.
 * Returns true on success, false on failure.
 */
function send_notification(string $subject, string $body): bool
{
    global $config;
 
    $to   = $config['notification']['email'] ?? '';
    $from = $config['notification']['from']  ?? "backup-noreply@{$config['cpanel']['host']}";
 
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_message("Skipping notification — invalid email address: {$to}", true);
        return false;
    }
 
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = "backup-noreply@localhost";
    }
 
    $headers  = "From: {$from}\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: UnderHost-Backup-Script/1.0\r\n";
 
    $result = mail($to, $subject, $body, $headers);
 
    if (!$result) {
        log_message("Failed to send notification email to {$to}", true);
    }
 
    return $result;
}
 
/**
 * Validate that all required configuration keys are present and non-empty.
 * Returns an array of error strings (empty = config is valid).
 */
function validate_config(array $config): array
{
    $errors = [];
 
    $required = [
        'cpanel.host'  => 'cPanel host',
        'cpanel.user'  => 'cPanel username',
        'cpanel.token' => 'cPanel API token',
    ];
 
    foreach ($required as $path => $label) {
        [$section, $key] = explode('.', $path);
        if (empty($config[$section][$key])) {
            $errors[] = "Missing required config: {$label} ({$path})";
        }
    }
 
    $backupType = $config['backup']['type'] ?? 'ftp';
 
    if (!in_array($backupType, ['ftp', 'homedir'], true)) {
        $errors[] = "Invalid backup type '{$backupType}'. Must be 'ftp' or 'homedir'.";
    }
 
    if ($backupType === 'ftp') {
        foreach (['host', 'user', 'pass'] as $key) {
            if (empty($config['ftp'][$key])) {
                $errors[] = "Missing required FTP config: ftp.{$key}";
            }
        }
    }
 
    $email = $config['notification']['email'] ?? '';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid notification email: {$email}";
    }
 
    return $errors;
}
 
/**
 * Execute the cPanel UAPI backup request.
 *
 * @throws RuntimeException on cURL or API error
 */
function trigger_backup(array $config, bool $dryRun = false): array
{
    $cpanel     = $config['cpanel'];
    $backupType = $config['backup']['type'] ?? 'ftp';
    $sslVerify  = (bool)($cpanel['ssl_verify'] ?? true);
 
    // Build the UAPI endpoint
    if ($backupType === 'ftp') {
        $endpoint = 'Backup/fullbackup_to_ftp';
        $postData = [
            'host'     => $config['ftp']['host'],
            'username' => $config['ftp']['user'],
            'password' => $config['ftp']['pass'],
            'port'     => (int)($config['ftp']['port'] ?? 21),
            'rdir'     => $config['ftp']['path'] ?? '/',
            'passive'  => ($config['ftp']['passive'] ?? true) ? 1 : 0,
            'email'    => $config['notification']['email'] ?? '',
        ];
    } else {
        // homedir backup — no FTP params needed
        $endpoint = 'Backup/fullbackup_to_homedir';
        $postData = [
            'email' => $config['notification']['email'] ?? '',
        ];
    }
 
    $url = "https://{$cpanel['host']}:{$cpanel['port']}/execute/{$endpoint}";
 
    if ($dryRun) {
        log_message("[DRY-RUN] Would POST to: {$url}");
        log_message("[DRY-RUN] Payload: " . json_encode($postData, JSON_UNESCAPED_SLASHES));
        return ['status' => 1, 'data' => [], 'errors' => [], 'dry_run' => true];
    }
 
    $curl = curl_init();
 
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_HTTPHEADER     => [
            "Authorization: cpanel {$cpanel['user']}:{$cpanel['token']}",
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => $sslVerify,
        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        CURLOPT_TIMEOUT        => (int)($cpanel['timeout'] ?? 300),
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FAILONERROR    => false, // We handle HTTP errors manually
    ]);
 
    $response   = curl_exec($curl);
    $curlErrNo  = curl_errno($curl);
    $curlErrMsg = curl_error($curl);
    $httpCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
 
    curl_close($curl);
 
    if ($curlErrNo !== 0) {
        throw new RuntimeException("cURL error #{$curlErrNo}: {$curlErrMsg}");
    }
 
    if ($response === false || $response === '') {
        throw new RuntimeException("Empty response from cPanel API (HTTP {$httpCode})");
    }
 
    $decoded = json_decode($response, true);
 
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException(
            "Failed to parse cPanel API response (HTTP {$httpCode}). " .
            "Raw: " . substr($response, 0, 300)
        );
    }
 
    if ($httpCode !== 200) {
        $apiErrors = implode('; ', $decoded['errors'] ?? []);
        throw new RuntimeException("cPanel API returned HTTP {$httpCode}. Errors: {$apiErrors}");
    }
 
    return $decoded;
}
 
// ─── Main ─────────────────────────────────────────────────────────────────────
 
$startTime = microtime(true);
$exitCode  = 0;
 
log_message("════════════════════════════════════════");
log_message("UnderHost cPanel Backup — starting");
if ($dryRun) {
    log_message("DRY-RUN mode — no changes will be made");
}
 
try {
    // 1. Validate configuration
    $errors = validate_config($config);
    if (!empty($errors)) {
        throw new InvalidArgumentException(
            "Configuration errors:\n  • " . implode("\n  • ", $errors)
        );
    }
 
    $backupType = $config['backup']['type'] ?? 'ftp';
    $destination = $backupType === 'ftp'
        ? "FTP ({$config['ftp']['host']}:{$config['ftp']['port']})"
        : 'cPanel home directory';
 
    log_message("Backup type    : {$backupType}");
    log_message("Destination    : {$destination}");
    log_message("cPanel host    : {$config['cpanel']['host']}:{$config['cpanel']['port']}");
    log_message("cPanel user    : {$config['cpanel']['user']}");
    log_message("SSL verify     : " . ($config['cpanel']['ssl_verify'] ?? true ? 'enabled' : 'DISABLED (insecure)'));
 
    if (!($config['cpanel']['ssl_verify'] ?? true)) {
        log_message("WARNING: SSL verification is disabled. This is insecure in production.", true);
    }
 
    // 2. Trigger backup
    log_message("Sending backup request to cPanel UAPI…");
    $response = trigger_backup($config, $dryRun);
 
    // 3. Check API response
    if (!empty($response['errors'])) {
        $apiErrors = implode("\n  • ", $response['errors']);
        throw new RuntimeException("cPanel API reported errors:\n  • {$apiErrors}");
    }
 
    $elapsed = round(microtime(true) - $startTime, 2);
 
    if ($dryRun) {
        log_message("DRY-RUN complete — no backup was actually triggered.");
    } else {
        log_message("Backup request accepted by cPanel (cPanel will transfer in the background).");
    }
 
    log_message("Completed in {$elapsed}s");
    log_message("════════════════════════════════════════");
 
    // 4. Success notification
    $subject = $config['notification']['subject_success']
        ?? '[Backup] cPanel backup completed successfully';
 
    $body = implode(PHP_EOL, [
        'cPanel Backup — Success',
        str_repeat('─', 40),
        '',
        "Host        : {$config['cpanel']['host']}",
        "User        : {$config['cpanel']['user']}",
        "Type        : {$backupType}",
        "Destination : {$destination}",
        "Time        : " . date('Y-m-d H:i:s'),
        "Duration    : {$elapsed}s",
        '',
        'cPanel will send a separate notification when the backup transfer completes.',
        '',
        '─────────────────────────────────────────',
        'UnderHost Backup Script — https://underhost.com',
    ]);
 
    send_notification($subject, $body);
 
} catch (Throwable $e) {
    $exitCode = 1;
    $elapsed  = round(microtime(true) - $startTime, 2);
    $errorMsg = $e->getMessage();
 
    log_message("BACKUP FAILED: {$errorMsg}", true);
    log_message("Failed after {$elapsed}s", true);
    log_message("════════════════════════════════════════");
 
    $subject = $config['notification']['subject_failure']
        ?? '[Backup] ALERT — cPanel backup failed';
 
    $body = implode(PHP_EOL, [
        'cPanel Backup — FAILED',
        str_repeat('─', 40),
        '',
        "Host  : {$config['cpanel']['host']}",
        "User  : {$config['cpanel']['user']}",
        "Time  : " . date('Y-m-d H:i:s'),
        '',
        'Error:',
        $errorMsg,
        '',
        'Please check the backup log for details.',
        '',
        '─────────────────────────────────────────',
        'UnderHost Backup Script — https://underhost.com',
    ]);
 
    send_notification($subject, $body);
}
 
exit($exitCode);
