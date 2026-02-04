<?php
// Quick helper to list users for verification in development only.
// Do NOT leave this on a production server.
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query('SELECT id, name, email, role, created_at, last_login FROM users ORDER BY id DESC LIMIT 200');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "Error reading users table: " . htmlspecialchars($e->getMessage());
    exit;
}
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Users - Fileasy (dev)</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;margin:20px}table{border-collapse:collapse;width:100%}th,td{padding:8px;border:1px solid #ddd;text-align:left}th{background:#f6f8fb}</style>
</head>
<body>
<h2>Users (development view)</h2>
<p>Only for local verification. Password hashes are intentionally not shown.</p>
<table>
    <thead>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created at</th><th>Last login</th></tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr><td colspan="6">No users found.</td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><?php echo htmlspecialchars($r['id']); ?></td>
            <td><?php echo htmlspecialchars($r['name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['email']); ?></td>
            <td><?php echo htmlspecialchars($r['role'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['created_at'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['last_login'] ?? ''); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</body>
</html>