<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/router_stats.php';
require_once __DIR__ . '/../core/live_sessions.php';
require_once __DIR__ . '/../core/expiry_engine.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Factory;
use React\Socket\SocketServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;

/* ------------------------------------------------------------------ */
class MonitorServer implements MessageComponentInterface
{
    protected $clients;
    protected $subscriptions     = [];
    protected $liveSubscriptions = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data) return;

        $type = $data['type'] ?? '';

        if ($type === 'subscribe') {
            $routerId = (int)$data['router_id'];
            $this->subscriptions[$routerId][] = $from;
            $from->router_id = $routerId;
            echo "Client {$from->resourceId} subscribed to router {$routerId}\n";
        }

        if ($type === 'subscribe_live') {
            $userId = (int)$data['user_id'];
            $this->liveSubscriptions[$userId][] = $from;
            $from->live_user_id = $userId;
            echo "Client {$from->resourceId} subscribed to live users for user {$userId}\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        echo "Connection {$conn->resourceId} closed\n";
        $this->clients->detach($conn);

        if (isset($conn->router_id)) {
            $rid = $conn->router_id;
            $this->subscriptions[$rid] = array_filter(
                $this->subscriptions[$rid] ?? [],
                fn($c) => $c !== $conn
            );
        }

        if (isset($conn->live_user_id)) {
            $uid = $conn->live_user_id;
            $this->liveSubscriptions[$uid] = array_filter(
                $this->liveSubscriptions[$uid] ?? [],
                fn($c) => $c !== $conn
            );
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcast($routerId, $payload) {
        $payload['router_id'] = $routerId;
        foreach ($this->subscriptions[$routerId] ?? [] as $client) {
            $client->send(json_encode($payload));
        }
    }

    public function broadcastLive($userId, $payload) {
        foreach ($this->liveSubscriptions[$userId] ?? [] as $client) {
            $client->send(json_encode($payload));
        }
    }

    /* Send to every connected client regardless of subscription */
    public function broadcastAll($payload) {
        foreach ($this->clients as $client) {
            $client->send(json_encode($payload));
        }
    }

    public function getRouterSubscriptions() { return $this->subscriptions; }
    public function getLiveSubscriptions()    { return $this->liveSubscriptions; }
}

/* ------------------------------------------------------------------ */
$loop    = Factory::create();
$monitor = new MonitorServer();

/* ══ TIMER 1 — Router stats every 5s (subscribed routers only) ══ */
$loop->addPeriodicTimer(5, function () use ($monitor, $pdo) {
    $activeRouterIds = array_keys($monitor->getRouterSubscriptions());
    if (empty($activeRouterIds)) return;

    $in   = implode(',', array_fill(0, count($activeRouterIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT router_id FROM routers WHERE status = 'active' AND router_id IN ($in)"
    );
    $stmt->execute($activeRouterIds);
    $routers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($routers as $router_id) {
        echo "Updating router {$router_id}\n";
        $data = getRouterStats($pdo, (int)$router_id);
        $monitor->broadcast((int)$router_id, $data);
    }
});

/* ══ TIMER 2 — Live users every 5s (subscribed users only) ══ */
$loop->addPeriodicTimer(5, function () use ($monitor, $pdo) {
    $activeUserIds = array_keys($monitor->getLiveSubscriptions());
    if (empty($activeUserIds)) return;

    foreach ($activeUserIds as $userId) {
        echo "Fetching live users for user {$userId}\n";
        try {
            $users = get_live_users_with_speed($pdo, $userId);
            $monitor->broadcastLive((int)$userId, [
                'type'  => 'live_users',
                'users' => $users,
                'time'  => date('H:i:s'),
            ]);
        } catch (\Throwable $e) {
            echo "Live users error for user {$userId}: {$e->getMessage()}\n";
        }
    }
});

/* ══ TIMER 3 — Expiry engine every 60s (ALL active routers) ══
   Runs unconditionally — no clients need to be connected.
   Uses expiry_engine.php (cron version) which writes to logs/.
   Broadcasts expiry_result to all connected clients when users expire.
════════════════════════════════════════════════════════════════ */
$loop->addPeriodicTimer(60, function () use ($monitor, $pdo) {
    echo "[Expiry] Checking for expired users — " . date('H:i:s') . "\n";

    try {
        $stmt   = $pdo->query("SELECT router_id FROM routers WHERE status = 'active'");
        $allIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($allIds)) {
            echo "[Expiry] No active routers\n";
            return;
        }

        $results      = run_expiry_engine($pdo, $allIds);
        $expiredCount = count($results['expired'] ?? []);
        $failedCount  = count($results['failed']  ?? []);

        echo "[Expiry] Expired: {$expiredCount} | Failed: {$failedCount}\n";

        /* Only push to clients when something actually changed */
        if ($expiredCount > 0 || $failedCount > 0) {
            $monitor->broadcastAll([
                'type'    => 'expiry_result',
                'expired' => $results['expired'] ?? [],
                'failed'  => $results['failed']  ?? [],
                'time'    => date('H:i:s'),
            ]);
        }

    } catch (\Throwable $e) {
        echo "[Expiry] Engine error: {$e->getMessage()}\n";
    }
});

/* ══ SERVER ══ */
$socket = new SocketServer('0.0.0.0:8080', [], $loop);
$server = new IoServer(
    new HttpServer(new WsServer($monitor)),
    $socket,
    $loop
);

echo "WebSocket server running on port 8080\n";
echo "Timers: router stats 5s | live users 5s | expiry engine 60s\n";
$loop->run();
