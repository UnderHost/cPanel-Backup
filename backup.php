<?php

declare(strict_types=1);

/**
 * UnderHost — cPanel Backup Script
 *
 * Beginner-friendly backup runner for shared hosting.
 * Supports:
 * - cPanel UAPI backup trigger (when available)
 * - Native PHP backup fallback (files + MySQL dump) when UAPI is unavailable
 * - Upload destinations: local, FTP, SFTP, and rclone remotes (Google Drive/S3/Dropbox/etc.)
 *
 * Usage:
 *   php backup.php [--config=/path/config.php] [--dry-run]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.' . PHP_EOL);
}

if (PHP_VERSION_ID < 70400) {
    fwrite(STDERR, '[FATAL] PHP 7.4 or higher is required.' . PHP_EOL);
    exit(1);
}

$options = getopt('', ['config:', 'dry-run']);
$configPath = $options['config'] ?? __DIR__ . '/config.php';
$dryRun = isset($options['dry-run']);

if (!file_exists($configPath)) {
    fwrite(STDERR, "[FATAL] Config file not found: {$configPath}" . PHP_EOL);
    exit(1);
}

$config = require $configPath;
if (!is_array($config)) {
    fwrite(STDERR, "[FATAL] Config file must return an array." . PHP_EOL);
    exit(1);
}

$defaults = [
    'engine' => 'auto', // auto|uapi|native
    'cpanel' => [
        'host' => '',
        'user' => '',
        'token' => '',
        'port' => 2083,
        'timeout' => 300,
        'ssl_verify' => true,
    ],
    'native' => [
        'home_dir' => getenv('HOME') ?: dirname(__DIR__),
        'include_paths' => ['public_html'],
        'exclude_paths' => ['tmp', '.trash', 'logs'],
        'databases' => [],
    ],
    'storage' => [
        'local_dir' => __DIR__ . '/artifacts',
        'temp_dir' => __DIR__ . '/tmp',
        'keep_local' => 7,
    ],
    'destinations' => [
        ['type' => 'local', 'enabled' => true],
    ],
    'notification' => [
        'email' => '',
        'from' => '',
        'subject_success' => '[Backup] Success',
        'subject_failure' => '[Backup] FAILED',
    ],
    'logging' => [
        'enabled' => true,
        'file' => __DIR__ . '/logs/backup.log',
        'max_size' => 2097152,
        'keep' => 5,
    ],
];

$config = array_replace_recursive($defaults, $config);

$logFile = $config['logging']['file'];
$logDir = dirname($logFile);
if (!is_dir($logDir) && !mkdir($logDir, 0750, true) && !is_dir($logDir)) {
    fwrite(STDERR, "[FATAL] Cannot create log directory: {$logDir}" . PHP_EOL);
    exit(1);
}

function log_message(string $message, bool $isError = false): void
{
    global $config;

    $level = $isError ? 'ERROR' : 'INFO';
    $line = '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}" . PHP_EOL;

    $isError ? fwrite(STDERR, $line) : fwrite(STDOUT, $line);

    if (!($config['logging']['enabled'] ?? true)) {
        return;
    }

    $logFile = $config['logging']['file'];
    $maxSize = (int)$config['logging']['max_size'];
    $keep = max(1, (int)$config['logging']['keep']);

    if (file_exists($logFile) && filesize($logFile) >= $maxSize) {
        rotate_logs($logFile, $keep);
    }

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function rotate_logs(string $logFile, int $keep): void
{
    $oldest = "{$logFile}.{$keep}";
    if (file_exists($oldest)) {
        @unlink($oldest);
    }

    for ($i = $keep - 1; $i >= 1; $i--) {
        $src = "{$logFile}.{$i}";
        $dst = "{$logFile}." . ($i + 1);
        if (file_exists($src)) {
            @rename($src, $dst);
        }
    }

    if (file_exists($logFile)) {
        @rename($logFile, "{$logFile}.1");
    }
}

function send_notification(string $subject, string $body): void
{
    global $config;

    $to = trim((string)($config['notification']['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $from = trim((string)($config['notification']['from'] ?? ''));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'backup-noreply@localhost';
    }

    $headers = "From: {$from}\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($to, $subject, $body, $headers);
}

function validate_config(array $config): array
{
    $errors = [];

    if (!in_array($config['engine'], ['auto', 'uapi', 'native'], true)) {
        $errors[] = "engine must be auto, uapi, or native";
    }

    if (empty($config['destinations']) || !is_array($config['destinations'])) {
        $errors[] = 'destinations must be a non-empty array';
    }

    return $errors;
}

function can_use_uapi(array $config): bool
{
    return !empty($config['cpanel']['host'])
        && !empty($config['cpanel']['user'])
        && !empty($config['cpanel']['token'])
        && extension_loaded('curl');
}

function trigger_uapi_homedir_backup(array $config, bool $dryRun): array
{
    $cpanel = $config['cpanel'];
    $url = "https://{$cpanel['host']}:{$cpanel['port']}/execute/Backup/fullbackup_to_homedir";

    if ($dryRun) {
        log_message("[DRY-RUN] UAPI request: {$url}");
        return ['status' => 'ok', 'mode' => 'uapi', 'message' => 'Dry run'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => ["Authorization: cpanel {$cpanel['user']}:{$cpanel['token']}"] ,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['email' => $config['notification']['email'] ?? '']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => (bool)$cpanel['ssl_verify'],
        CURLOPT_SSL_VERIFYHOST => (bool)$cpanel['ssl_verify'] ? 2 : 0,
        CURLOPT_TIMEOUT => (int)$cpanel['timeout'],
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $errNo = curl_errno($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo !== 0) {
        throw new RuntimeException("UAPI cURL error {$errNo}: {$err}");
    }

    $decoded = json_decode((string)$response, true);
    if ($http !== 200 || !is_array($decoded)) {
        throw new RuntimeException("UAPI failed with HTTP {$http}");
    }

    if (!empty($decoded['errors'])) {
        throw new RuntimeException('UAPI errors: ' . implode('; ', $decoded['errors']));
    }

    return ['status' => 'ok', 'mode' => 'uapi', 'message' => 'Backup request accepted'];
}

function resolve_path(string $homeDir, string $path): string
{
    if ($path === '') {
        return $homeDir;
    }

    if ($path[0] === '/') {
        return $path;
    }

    return rtrim($homeDir, '/') . '/' . ltrim($path, '/');
}

function copy_recursive(string $src, string $dst, array $exclude): void
{
    if (!file_exists($src)) {
        return;
    }

    if (is_dir($src)) {
        if (!is_dir($dst)) {
            mkdir($dst, 0750, true);
        }

        $items = scandir($src);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $src . '/' . $item;
            foreach ($exclude as $ex) {
                $ex = trim((string)$ex, '/');
                if ($ex !== '' && strpos(trim($srcPath, '/'), $ex) !== false) {
                    continue 2;
                }
            }

            copy_recursive($srcPath, $dst . '/' . $item, $exclude);
        }

        return;
    }

    @copy($src, $dst);
}

function dump_mysql_database(array $db, string $outputFile): void
{
    $mysqli = @new mysqli(
        $db['host'] ?? 'localhost',
        $db['user'] ?? '',
        $db['pass'] ?? '',
        $db['name'] ?? '',
        (int)($db['port'] ?? 3306)
    );

    if ($mysqli->connect_errno) {
        throw new RuntimeException('MySQL connection failed for ' . ($db['name'] ?? 'unknown') . ': ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');

    $fp = fopen($outputFile, 'wb');
    if ($fp === false) {
        throw new RuntimeException('Cannot write SQL dump: ' . $outputFile);
    }

    fwrite($fp, "-- SQL dump generated by backup.php\n");
    fwrite($fp, 'SET FOREIGN_KEY_CHECKS=0;' . "\n\n");

    $tablesRes = $mysqli->query('SHOW TABLES');
    if ($tablesRes instanceof mysqli_result) {
        while ($row = $tablesRes->fetch_array()) {
            $table = $row[0];
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");

            $createRes = $mysqli->query("SHOW CREATE TABLE `{$table}`");
            if ($createRes instanceof mysqli_result) {
                $createRow = $createRes->fetch_assoc();
                $createSql = $createRow['Create Table'] ?? '';
                fwrite($fp, $createSql . ";\n\n");
                $createRes->free();
            }

            $dataRes = $mysqli->query("SELECT * FROM `{$table}`");
            if ($dataRes instanceof mysqli_result) {
                while ($data = $dataRes->fetch_assoc()) {
                    $cols = array_map(static fn($col) => "`{$col}`", array_keys($data));
                    $vals = array_map(
                        static function ($v) use ($mysqli): string {
                            if ($v === null) {
                                return 'NULL';
                            }
                            return "'" . $mysqli->real_escape_string((string)$v) . "'";
                        },
                        array_values($data)
                    );

                    fwrite($fp, 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
                }
                fwrite($fp, "\n");
                $dataRes->free();
            }
        }
        $tablesRes->free();
    }

    fwrite($fp, 'SET FOREIGN_KEY_CHECKS=1;' . "\n");
    fclose($fp);
    $mysqli->close();
}

function create_archive_from_directory(string $sourceDir, string $targetBase): string
{
    if (class_exists('ZipArchive')) {
        $zipPath = $targetBase . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create zip archive');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            $relPath = substr($filePath, strlen($sourceDir) + 1);
            if ($file->isDir()) {
                $zip->addEmptyDir($relPath);
            } else {
                $zip->addFile($filePath, $relPath);
            }
        }

        $zip->close();
        return $zipPath;
    }

    $tarPath = $targetBase . '.tar';
    $gzPath = $targetBase . '.tar.gz';

    if (file_exists($tarPath)) {
        @unlink($tarPath);
    }
    if (file_exists($gzPath)) {
        @unlink($gzPath);
    }

    $phar = new PharData($tarPath);
    $phar->buildFromDirectory($sourceDir);
    $phar->compress(Phar::GZ);
    unset($phar);

    @unlink($tarPath);

    return $gzPath;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function create_native_backup(array $config, bool $dryRun): array
{
    $homeDir = rtrim((string)$config['native']['home_dir'], '/');
    $localDir = rtrim((string)$config['storage']['local_dir'], '/');
    $tempDir = rtrim((string)$config['storage']['temp_dir'], '/');

    $stamp = date('Ymd_His');
    $baseName = ($config['cpanel']['user'] ?: 'cpanel') . '_backup_' . $stamp;

    $workspace = $tempDir . '/' . $baseName;
    $contentDir = $workspace . '/content';
    $filesDir = $contentDir . '/files';
    $dbDir = $contentDir . '/databases';

    if ($dryRun) {
        log_message('[DRY-RUN] Native mode would create archive from configured files/databases.');
        return ['status' => 'ok', 'mode' => 'native', 'dry_run' => true];
    }

    foreach ([$localDir, $tempDir, $filesDir, $dbDir] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }
    }

    $exclude = (array)($config['native']['exclude_paths'] ?? []);

    foreach ((array)$config['native']['include_paths'] as $path) {
        $resolved = resolve_path($homeDir, (string)$path);
        $label = trim(str_replace('/', '_', (string)$path), '_');
        $label = $label !== '' ? $label : 'root';
        $target = $filesDir . '/' . $label;
        log_message('Copying files: ' . $resolved);
        copy_recursive($resolved, $target, $exclude);
    }

    foreach ((array)$config['native']['databases'] as $db) {
        if (empty($db['name'])) {
            continue;
        }

        $out = $dbDir . '/' . $db['name'] . '.sql';
        log_message('Dumping database: ' . $db['name']);
        dump_mysql_database($db, $out);
    }

    $manifest = [
        'created_at' => date('c'),
        'engine' => 'native',
        'host' => $config['cpanel']['host'] ?? '',
        'user' => $config['cpanel']['user'] ?? '',
        'include_paths' => $config['native']['include_paths'],
        'database_count' => count((array)$config['native']['databases']),
    ];
    file_put_contents($contentDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $archiveBase = $localDir . '/' . $baseName;
    $archivePath = create_archive_from_directory($contentDir, $archiveBase);

    rrmdir($workspace);

    return [
        'status' => 'ok',
        'mode' => 'native',
        'archive_path' => $archivePath,
        'archive_size' => file_exists($archivePath) ? filesize($archivePath) : 0,
    ];
}

function upload_via_ftp(string $filePath, array $destination, bool $dryRun): array
{
    if (!function_exists('ftp_connect')) {
        throw new RuntimeException('FTP extension not available in PHP.');
    }

    $host = (string)($destination['host'] ?? '');
    $user = (string)($destination['user'] ?? '');
    $pass = (string)($destination['pass'] ?? '');
    $port = (int)($destination['port'] ?? 21);
    $remotePath = rtrim((string)($destination['path'] ?? '/'), '/');

    if ($dryRun) {
        return ['ok' => true, 'message' => "[DRY-RUN] FTP upload to {$host}{$remotePath}"];
    }

    $conn = @ftp_connect($host, $port, 30);
    if (!$conn) {
        throw new RuntimeException('Could not connect to FTP host: ' . $host);
    }

    if (!@ftp_login($conn, $user, $pass)) {
        ftp_close($conn);
        throw new RuntimeException('FTP login failed for user: ' . $user);
    }

    @ftp_pasv($conn, (bool)($destination['passive'] ?? true));

    $remoteFile = $remotePath . '/' . basename($filePath);
    if (!@ftp_put($conn, $remoteFile, $filePath, FTP_BINARY)) {
        ftp_close($conn);
        throw new RuntimeException('FTP upload failed: ' . $remoteFile);
    }

    ftp_close($conn);

    return ['ok' => true, 'message' => 'Uploaded via FTP: ' . $remoteFile];
}

function upload_via_sftp(string $filePath, array $destination, bool $dryRun): array
{
    if (!function_exists('ssh2_connect')) {
        throw new RuntimeException('ssh2 extension not available for SFTP.');
    }

    $host = (string)($destination['host'] ?? '');
    $port = (int)($destination['port'] ?? 22);
    $user = (string)($destination['user'] ?? '');
    $pass = (string)($destination['pass'] ?? '');
    $remotePath = rtrim((string)($destination['path'] ?? '/'), '/');

    if ($dryRun) {
        return ['ok' => true, 'message' => "[DRY-RUN] SFTP upload to {$host}{$remotePath}"];
    }

    $connection = @ssh2_connect($host, $port);
    if (!$connection) {
        throw new RuntimeException('Cannot connect to SFTP server: ' . $host);
    }

    if (!@ssh2_auth_password($connection, $user, $pass)) {
        throw new RuntimeException('SFTP auth failed for user: ' . $user);
    }

    $sftp = @ssh2_sftp($connection);
    if (!$sftp) {
        throw new RuntimeException('Cannot initialize SFTP subsystem.');
    }

    $remoteFile = $remotePath . '/' . basename($filePath);
    $stream = @fopen('ssh2.sftp://' . intval($sftp) . $remoteFile, 'w');
    if ($stream === false) {
        throw new RuntimeException('Cannot open remote SFTP file: ' . $remoteFile);
    }

    $data = file_get_contents($filePath);
    if ($data === false) {
        fclose($stream);
        throw new RuntimeException('Cannot read local file for SFTP upload.');
    }

    fwrite($stream, $data);
    fclose($stream);

    return ['ok' => true, 'message' => 'Uploaded via SFTP: ' . $remoteFile];
}

function upload_via_rclone(string $filePath, array $destination, bool $dryRun): array
{
    $remote = (string)($destination['remote'] ?? '');
    $path = trim((string)($destination['path'] ?? ''), '/');

    if ($remote === '') {
        throw new RuntimeException('rclone destination requires remote.');
    }

    $target = rtrim($remote, ':') . ':' . ($path !== '' ? '/' . $path : '');
    $cmd = 'rclone copy ' . escapeshellarg($filePath) . ' ' . escapeshellarg($target) . ' --stats=0 --checkers=4 --transfers=1';

    if ($dryRun) {
        return ['ok' => true, 'message' => '[DRY-RUN] ' . $cmd];
    }

    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("rclone failed ({$code}): " . implode("\n", $out));
    }

    return ['ok' => true, 'message' => 'Uploaded via rclone to ' . $target];
}

function apply_retention(string $dir, int $keep): void
{
    $files = glob(rtrim($dir, '/') . '/*.{zip,gz,tar}', GLOB_BRACE);
    if (!is_array($files)) {
        return;
    }

    usort(
        $files,
        static fn($a, $b) => filemtime($b) <=> filemtime($a)
    );

    if (count($files) <= $keep) {
        return;
    }

    foreach (array_slice($files, $keep) as $old) {
        @unlink($old);
    }
}

$start = microtime(true);
$exitCode = 0;
$summary = [];

log_message('════════════════════════════════════════');
log_message('UnderHost cPanel Backup — starting');
log_message('Engine mode: ' . $config['engine']);
if ($dryRun) {
    log_message('DRY-RUN mode enabled.');
}

try {
    $errors = validate_config($config);
    if ($errors !== []) {
        throw new InvalidArgumentException("Configuration errors:\n - " . implode("\n - ", $errors));
    }

    $result = null;
    $engineUsed = $config['engine'];

    if ($config['engine'] === 'uapi' || ($config['engine'] === 'auto' && can_use_uapi($config))) {
        try {
            $result = trigger_uapi_homedir_backup($config, $dryRun);
            $engineUsed = 'uapi';
            $summary[] = 'UAPI backup trigger accepted by cPanel.';
        } catch (Throwable $uapiError) {
            if ($config['engine'] === 'uapi') {
                throw $uapiError;
            }
            log_message('UAPI unavailable. Falling back to native mode: ' . $uapiError->getMessage(), true);
            $result = create_native_backup($config, $dryRun);
            $engineUsed = 'native';
        }
    } else {
        $result = create_native_backup($config, $dryRun);
        $engineUsed = 'native';
    }

    $archivePath = $result['archive_path'] ?? null;

    if ($archivePath !== null) {
        log_message('Created archive: ' . $archivePath);
        $summary[] = 'Archive: ' . basename($archivePath) . ' (' . round(((int)($result['archive_size'] ?? 0)) / 1024 / 1024, 2) . ' MB)';

        foreach ((array)$config['destinations'] as $idx => $destination) {
            if (($destination['enabled'] ?? true) === false) {
                continue;
            }

            $type = strtolower((string)($destination['type'] ?? 'local'));
            $label = $destination['name'] ?? "destination#{$idx}";

            try {
                if ($type === 'local') {
                    $summary[] = "{$label}: kept locally";
                    log_message("{$label}: local destination (no upload)");
                    continue;
                }

                if ($type === 'ftp') {
                    $res = upload_via_ftp($archivePath, $destination, $dryRun);
                } elseif ($type === 'sftp') {
                    $res = upload_via_sftp($archivePath, $destination, $dryRun);
                } elseif (in_array($type, ['rclone', 'google_drive', 'dropbox', 's3'], true)) {
                    $res = upload_via_rclone($archivePath, $destination, $dryRun);
                } else {
                    throw new RuntimeException('Unsupported destination type: ' . $type);
                }

                $summary[] = "{$label}: " . $res['message'];
                log_message("{$label}: " . $res['message']);
            } catch (Throwable $uploadError) {
                $summary[] = "{$label}: FAILED — " . $uploadError->getMessage();
                log_message("{$label}: upload failed — " . $uploadError->getMessage(), true);
            }
        }

        apply_retention((string)$config['storage']['local_dir'], (int)$config['storage']['keep_local']);
    }

    $elapsed = round(microtime(true) - $start, 2);
    log_message("Backup completed in {$elapsed}s using {$engineUsed} mode.");
    log_message('════════════════════════════════════════');

    $subject = (string)$config['notification']['subject_success'];
    $body = "Backup completed successfully\n\n" . implode("\n", $summary) . "\n\nTime: " . date('Y-m-d H:i:s');
    send_notification($subject, $body);
} catch (Throwable $e) {
    $exitCode = 1;
    $elapsed = round(microtime(true) - $start, 2);
    log_message('BACKUP FAILED: ' . $e->getMessage(), true);
    log_message("Failed after {$elapsed}s", true);
    log_message('════════════════════════════════════════');

    $subject = (string)$config['notification']['subject_failure'];
    $body = "Backup failed\n\nError:\n" . $e->getMessage() . "\n\nTime: " . date('Y-m-d H:i:s');
    send_notification($subject, $body);
}

exit($exitCode);
