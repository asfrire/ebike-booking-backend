<?php
require 'vendor/autoload.php';
$pdo = new PDO('sqlite:database/database.sqlite');
$pdo->exec('PRAGMA foreign_keys = OFF;');
$pdo->exec('CREATE TABLE rider_queue_temp AS SELECT * FROM rider_queue;');
$pdo->exec('DROP TABLE rider_queue;');
$pdo->exec('CREATE TABLE rider_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rider_id INTEGER NOT NULL,
    queue_position TEXT,
    is_online BOOLEAN DEFAULT 0,
    joined_at DATETIME,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (rider_id) REFERENCES users(id) ON DELETE CASCADE
);');
$pdo->exec('INSERT INTO rider_queue SELECT * FROM rider_queue_temp;');
$pdo->exec('DROP TABLE rider_queue_temp;');
$pdo->exec('PRAGMA foreign_keys = ON;');
echo "Done";
?>
