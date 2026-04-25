<?php
require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SSH2;

function addWireguardPeer($publicKey, $vpnIp)
{
    $server   = "102.68.86.80";
    $username = "root";
    $password = "if@}t&?t11}WI7Na";

     $ssh = new SSH2($server);

    if (!$ssh->login($username, $password)) {
        throw new Exception("SSH login failed");
    }

    // STEP 1: ensure interface exists
    $ssh->exec("wg show wg0 >/dev/null 2>&1");

    // STEP 2: add peer (NO trailing semicolon!)
    $cmd = "wg set wg0 peer {$publicKey} allowed-ips {$vpnIp}/32";

    $output = $ssh->exec($cmd);

    // STEP 3: verify result immediately
    $check = $ssh->exec("wg show wg0");

    // STEP 4: persist config properly
    $ssh->exec("wg-quick save wg0");

    file_put_contents(
        "/tmp/wg_debug.log",
        date("c") . "\nCMD: $cmd\nOUTPUT:\n$output\nWG STATUS:\n$check\n\n",
        FILE_APPEND
    );

    return strpos($check, $publicKey) !== false;
}