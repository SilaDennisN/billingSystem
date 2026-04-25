<?php
require_once __DIR__ . "/../core/db.php";
require_once __DIR__ . "/../core/expiry_engine.php";

$lockFile = __DIR__ . '/expiry.lock';

if (file_exists($lockFile)) {
    exit; // another instance running
}

file_put_contents($lockFile, getmypid());

register_shutdown_function(function () use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

run_expiry_engine($pdo);
