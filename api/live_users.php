<?php
require_once __DIR__ . "/../core/db.php";
require_once __DIR__ . "/../core/live_sessions.php";

header('Content-Type: application/json');

$liveUsers = get_live_users($pdo); // fetch all hotspot + PPPoE users

echo json_encode($liveUsers);
