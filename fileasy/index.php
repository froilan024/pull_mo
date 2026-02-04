<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];
$success = '';
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $role = $_POST['role'] ?? 'student';

    if (empty($name)) $errors[] = 'Please enter your name.';
    // require at least one capital letter in name
    if (!empty($name) && !preg_match('/[A-Z]/', $name)) $errors[] = 'Name must contain at least one uppercase letter.';
    if (!$email) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';
    $allowedRoles = ['student','teacher','other','user','admin'];
    if (!in_array($role, $allowedRoles, true)) $role = 'student';

        if (empty($errors)) {
            // ensure DB schema supports the new fields/roles — attempt automated migration if needed
            try {
                // check for 'name' column and role enum values
                $colStmt = $pdo->prepare("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('name','role')");
                $colStmt->execute();
                $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
                $hasName = false;
                $roleType = null;
                foreach ($cols as $c) {
                    if ($c['COLUMN_NAME'] === 'name') $hasName = true;
                    if ($c['COLUMN_NAME'] === 'role') $roleType = $c['COLUMN_TYPE'];
                }

                if (!$hasName) {
                    // try to add name column
                    $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) DEFAULT NULL AFTER email");
                }

                $neededRoles = ["user","admin","student","teacher","other"];
                $needModify = false;
                if ($roleType === null) {
                    $needModify = true;
                } else {
                    foreach ($neededRoles as $r) {
                        if (strpos($roleType, "'".$r."'") === false) {
                            $needModify = true;
                            break;
                        }
                    }
                }
                if ($needModify) {
                    $enumList = implode(',', array_map(function($r){ return "'".$r."'"; }, $neededRoles));
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM($enumList) NOT NULL DEFAULT 'user'");
                }
            } catch (Exception $e) {
                // If migration fails (permissions), add a helpful error and continue; fallback insert will try without name/role modifications
                $errors[] = 'Database migration failed: ' . $e->getMessage() . ' — you may need to run the SQL migration manually (see README).';
            }

            // check existing
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email is already registered. Try logging in.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $created = false;
                // attempt to insert with name and role; if columns don't exist or enum rejects the role, fall back and give instructions
                try {
                    $ins = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
                    $ins->execute([$name, $email, $hash, $role]);
                    $created = ($pdo->lastInsertId() ? true : true); // if no exception, assume created
                } catch (PDOException $pe) {
                    // fallback: try without name/role
                    try {
                        $ins = $pdo->prepare('INSERT INTO users (email, password) VALUES (?, ?)');
                        $ins->execute([$email, $hash]);
                        $created = ($pdo->lastInsertId() ? true : true);
                        $errors[] = 'Note: account created but name/role were not saved because the database schema is missing required columns — please run the migration SQL to add them.';
                    } catch (PDOException $pe2) {
                        $errors[] = 'Registration failed: ' . $pe2->getMessage();
                    }
                }

                if ($created && empty($errors)) {
                    // success
                    $_SESSION['flash_success'] = 'Account created successfully. Please log in.';
                    header('Location: index.php');
                    exit();
                }
                // if created but with a note, still redirect to login with success and warning
                if ($created && !empty($errors)) {
                    $_SESSION['flash_success'] = 'Account created. Please log in. Note: ' . implode(' ', $errors);
                    header('Location: index.php');
                    exit();
                }
                // otherwise fall through and show errors
            }
        }

    } elseif ($action === 'login') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email) $errors[] = 'Please enter a valid email address.';
        if (!$password) $errors[] = 'Please enter your password.';

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('SELECT id, password, name FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
                $row = false;
            }

            if ($row && password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['email'] = $email;
                if (!empty($row['name'])) {
                    $_SESSION['name'] = $row['name'];
                } else {
                    unset($_SESSION['name']);
                }
                // update last login
                $upd = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $upd->execute([$row['id']]);
                header('Location: home.php');
                exit();
            } else {
                $errors[] = 'Invalid email or password.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FILEASY – Login</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Left Panel - Branded Hero */
        .hero-panel {
            width: 45%;
            background: linear-gradient(135deg, #1a2332 0%, #1f2a3a 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .hero-panel::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(107, 75, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -200px;
            right: -200px;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .logo-section {
            margin-bottom: 60px;
        }

        .logo-circle {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #6b4bff, #8b6bff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .logo-text h1 {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .logo-text p {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        .tagline {
            font-size: 36px;
            line-height: 1.3;
            margin: 80px 0 40px 0;
            font-weight: 600;
        }

        .features-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .feature-icon {
            font-size: 20px;
        }

        .back-link {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
            align-self: flex-start;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: white;
        }

        /* Right Panel - Auth Form */
        .auth-panel {
            width: 55%;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-container {
            max-width: 420px;
            margin: 0 auto;
            width: 100%;
        }

        .auth-header {
            margin-bottom: 30px;
        }

        .auth-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 8px;
        }

        .auth-subtitle {
            font-size: 14px;
            color: #999;
        }

        .auth-subtitle a {
            color: #6b4bff;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
        }

        .auth-subtitle a:hover {
            text-decoration: underline;
        }

        /* Messages */
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .message.success {
            background: #f0fff4;
            color: #1f7a3a;
            border: 1px solid #c8e6c9;
        }

        .message.error {
            background: #fff2f2;
            color: #7a1f1f;
            border: 1px solid #ffcdd2;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6b4bff;
            box-shadow: 0 0 0 3px rgba(107, 75, 255, 0.1);
        }

        .form-group input::placeholder {
            color: #ccc;
        }

        .role-options {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .role-option {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            cursor: pointer;
        }

        .role-option input[type="radio"] {
            width: auto;
            padding: 0;
            border: none;
            cursor: pointer;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #6b4bff, #8b6bff);
            color: white;
            margin-top: 20px;
        }

        .btn-primary:hover {
            box-shadow: 0 4px 15px rgba(107, 75, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-secondary {
            width: 100%;
            background: white;
            color: #6b4bff;
            border: 2px solid #6b4bff;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #f5f7fb;
        }

        .form-toggle {
            display: none;
        }

        .form-toggle.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-panel {
                width: 40%;
            }

            .auth-panel {
                width: 60%;
                padding: 40px 30px;
            }

            .tagline {
                font-size: 28px;
                margin: 60px 0 30px 0;
            }

            .hero-panel::before {
                width: 350px;
                height: 350px;
                bottom: -150px;
                right: -150px;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .hero-panel {
                width: 100%;
                padding: 30px 20px;
                min-height: 300px;
            }

            .auth-panel {
                width: 100%;
                padding: 30px 20px;
                min-height: 100vh;
            }

            .tagline {
                font-size: 24px;
                margin: 40px 0 20px 0;
            }

            .back-link {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Left Panel -->
    <div class="hero-panel">
        <div>
            <a href="home.php" class="back-link">← Back to website</a>
            <div class="logo-section">
                <div class="logo-circle">F</div>
                <div class="logo-text">
                    <h1>FILEASY</h1>
                    <p>Study • Learn • Excel</p>
                </div>
            </div>
        </div>

        <div class="hero-content">
            <div class="tagline">Capturing Moments, Creating Memories</div>
            <ul class="features-list">
                <li class="feature-item">
                    <span class="feature-icon">📄</span>
                    <span>Summarize documents & presentations</span>
                </li>
                <li class="feature-item">
                    <span class="feature-icon">🎯</span>
                    <span>Generate mock quizzes instantly</span>
                </li>
                <li class="feature-item">
                    <span class="feature-icon">✨</span>
                    <span>Master your studies smarter</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-panel">
        <div class="auth-container">
            <!-- Login Form -->
            <div id="loginForm" class="form-toggle active">
                <div class="auth-header">
                    <div class="auth-title">Welcome Back</div>
                    <div class="auth-subtitle">Don't have an account? <a id="switchToRegister">Create one</a></div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="message error"><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="your@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <input type="hidden" name="action" value="login">
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>
            </div>

            <!-- Register Form -->
            <div id="registerForm" class="form-toggle">
                <div class="auth-header">
                    <div class="auth-title">Create an account</div>
                    <div class="auth-subtitle">Already have an account? <a id="switchToLogin">Sign in</a></div>
                </div>

                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="message error"><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" placeholder="Fletcher" required pattern=".*[A-Z].*" title="Name must contain at least one uppercase letter">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="your@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Create a password (min 6 chars)" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password2" placeholder="Confirm password" required>
                    </div>
                    <div class="form-group">
                        <label>I am a:</label>
                        <div class="role-options">
                            <label class="role-option">
                                <input type="radio" name="role" value="student" checked>
                                <span>Student</span>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="teacher">
                                <span>Teacher</span>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="other">
                                <span>Other</span>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="action" value="register">
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const switchToRegister = document.getElementById('switchToRegister');
        const switchToLogin = document.getElementById('switchToLogin');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');

        switchToRegister && switchToRegister.addEventListener('click', (e) => {
            e.preventDefault();
            loginForm.classList.remove('active');
            registerForm.classList.add('active');
        });

        switchToLogin && switchToLogin.addEventListener('click', (e) => {
            e.preventDefault();
            registerForm.classList.remove('active');
            loginForm.classList.add('active');
        });
    })();
</script>

</body>
</html>
