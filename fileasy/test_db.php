<?php
require_once __DIR__ . '/db.php';
try {
    $row = $pdo->query('SELECT 1 AS ok')->fetch();
    echo ($row && $row['ok']==1) ? 'DB OK' : 'DB ERROR';
} catch (Exception $e) {
    echo 'DB ERROR: ' . $e->getMessage();
}