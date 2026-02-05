<?php
require_once __DIR__ . "/../core/db.php";
require_once __DIR__ . "/../core/expiry_engine.php";

run_expiry_engine($pdo);
