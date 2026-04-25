<?php
// GitHub Webhook Auto Deploy (Windows)

$secret = 'Ds0740770212#';

// Get payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

$hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($hash, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// Windows paths
$repoPath = 'C:\\xampp\\htdocs\\inovatech\\billing';

// Git command
$cmd = "cd /d {$repoPath} && git fetch origin && git reset --hard origin/main 2>&1";

// Execute
$output = shell_exec($cmd);

// Log
file_put_contents(
    $repoPath . '\\deploy.log',
    date('Y-m-d H:i:s') . PHP_EOL . $output . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

echo "Deployment OK";
