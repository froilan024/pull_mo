<?php
session_start();
require_once __DIR__ . '/db.php';

// Support logout via ?action=logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

// We'll allow guests to view the dashboard-like home page; show personalized text when logged in.
$isLoggedIn = isset($_SESSION['user_id']);
$userDisplay = $_SESSION['name'] ?? $_SESSION['email'] ?? 'User';
$current = basename($_SERVER['PHP_SELF']);

// dynamic stats (per-user)
$uploadedCount = 0;
$quizCount = 0;
$summariesCount = 0;
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM files WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $uploadedCount = (int)$stmt->fetchColumn();

        // quizzes table may record generated quizzes per user
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quizzes WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $quizCount = (int)$stmt->fetchColumn();

        // summaries joined to files to ensure per-user
        $stmt = $pdo->prepare('SELECT COUNT(s.id) FROM summaries s JOIN files f ON s.file_id = f.id WHERE f.user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $summariesCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // leave counts as 0 on error
        $uploadedCount = $uploadedCount ?: 0;
        $quizCount = $quizCount ?: 0;
        $summariesCount = $summariesCount ?: 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FILEASY — Home</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #333;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a2332 0%, #1f2a3a 100%);
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .logo {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
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
            margin: 0 auto 12px;
        }

        .logo h2 {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .logo p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            padding: 14px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding-left: 24px;
        }

        .nav-item.active {
            background: rgba(107, 75, 255, 0.2);
            color: #8b6bff;
            border-left: 3px solid #6b4bff;
            padding-left: 17px;
        }

        .nav-icon {
            font-size: 16px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .storage-info {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .storage-bar {
            background: rgba(255, 255, 255, 0.1);
            height: 4px;
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }

        .storage-fill {
            background: linear-gradient(90deg, #6b4bff, #8b6bff);
            height: 100%;
            width: 24%;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            background: rgba(255, 59, 48, 0.2);
            color: #ff3b30;
            border: 1px solid rgba(255, 59, 48, 0.3);
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 59, 48, 0.3);
            border-color: rgba(255, 59, 48, 0.5);
        }

        /* Main Content */
        .main {
            margin-left: 250px;
            flex: 1;
            padding: 0;
        }

        .topbar {
            background: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .topbar h1 {
            font-size: 24px;
            color: #1a2332;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6b4bff, #8b6bff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .user-name {
            font-size: 14px;
            color: #333;
        }

        .content {
            padding: 40px;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }

        .stat-icon.pending {
            background: rgba(255, 159, 64, 0.2);
            color: #ff9f40;
        }

        .stat-icon.completed {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }

        .stat-icon.total {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }

        .stat-content h3 {
            font-size: 32px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 6px;
        }

        .stat-content p {
            font-size: 13px;
            color: #999;
        }

        /* Section Title */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title a {
            font-size: 12px;
            color: #6b4bff;
            text-decoration: none;
            transition: color 0.3s;
        }

        .section-title a:hover {
            color: #8b6bff;
        }

        /* Files Section */
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 40px;
        }

        .file-card {
            background: white;
            padding: 16px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-card:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }

        .file-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .file-name {
            font-weight: 600;
            font-size: 13px;
            color: #1a2332;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-date {
            font-size: 11px;
            color: #999;
        }

        .file-actions {
            display: flex;
            gap: 6px;
            margin-top: 10px;
        }

        .file-btn {
            flex: 1;
            padding: 6px;
            background: #f5f7fb;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            color: #6b4bff;
            font-weight: 600;
            transition: all 0.3s;
        }

        .file-btn:hover {
            background: #6b4bff;
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6b4bff, #8b6bff);
            color: white;
        }

        .btn-primary:hover {
            box-shadow: 0 4px 15px rgba(107, 75, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: #6b4bff;
            border: 2px solid #6b4bff;
        }

        .btn-secondary:hover {
            background: #f5f7fb;
        }

        /* Empty State */
        .empty-state {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            color: #999;
        }

        .empty-state p {
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 15px 0;
            }

            .main {
                margin-left: 70px;
            }

            .logo-circle {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            .logo h2, .logo p, .nav-item span {
                display: none;
            }

            .logo {
                padding: 0 10px 15px;
            }

            .nav-item {
                padding: 12px;
                justify-content: center;
            }

            .topbar {
                padding: 15px 20px;
            }

            .topbar h1 {
                font-size: 18px;
            }

            .content {
                padding: 20px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .files-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <div class="logo-circle">F</div>
            <h2>FILEASY</h2>
            <p>Study • Learn • Excel</p>
        </div>

        <ul class="nav-menu">
            <li class="nav-item <?php echo ($current === 'home.php') ? 'active' : '' ?>">
                <a href="home.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">📊</span>
                    <span>Home</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current === 'my_files.php') ? 'active' : '' ?>">
                <a href="my_files.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">📄</span>
                    <span>My Files</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current === 'mock_quiz.php') ? 'active' : '' ?>">
                <a href="mock_quiz.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">🎯</span>
                    <span>Mock Quiz</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current === 'summaries.php') ? 'active' : '' ?>">
                <a href="summaries.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">📋</span>
                    <span>Summaries</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current === 'timer.php') ? 'active' : '' ?>">
                <a href="timer.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">⏱️</span>
                    <span>Study Timer</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current === 'flashcards.php') ? 'active' : '' ?>">
                <a href="flashcards.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">📚</span>
                    <span>Flashcards</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current === 'history.php') ? 'active' : '' ?>">
                <a href="history.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">📊</span>
                    <span>History</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current === 'settings.php') ? 'active' : '' ?>">
                <a href="settings.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:12px;width:100%">
                    <span class="nav-icon">⚙️</span>
                    <span>Settings</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="?action=logout" style="text-decoration: none;">
                <button class="logout-btn">🚪 Logout</button>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
        <!-- Topbar -->
        <div class="topbar">
            <h1>Home</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($userDisplay, 0, 1)); ?></div>
                <div class="user-name">Hello, <?php echo htmlspecialchars($userDisplay); ?></div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-primary" id="openQuizBtn">+ Generate Quiz</button>
                <button class="btn btn-secondary" id="openUploadBtn">📤 Upload File</button>
                <button class="btn btn-secondary">📝 Summarize</button>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon pending">📋</div>
                    <div class="stat-content">
                        <h3><?php echo (int)$uploadedCount; ?></h3>
                        <p>Files uploaded</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed">✓</div>
                    <div class="stat-content">
                        <h3><?php echo (int)$quizCount; ?></h3>
                        <p>Mock quizzes generated</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon total">📊</div>
                    <div class="stat-content">
                        <h3><?php echo (int)$summariesCount; ?></h3>
                        <p>Summarized files</p>
                    </div>
                </div>
            </div>

            <!-- Recent Files -->
            <div class="section-title">
                Recent Files
                <a href="#">View All</a>
            </div>
            <div class="files-grid">
                <?php
                $recentFiles = [];
                if ($isLoggedIn) {
                    try {
                        $fstmt = $pdo->prepare('SELECT id, original_name, filename, uploaded_at FROM files WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 8');
                        $fstmt->execute([$_SESSION['user_id']]);
                        $recentFiles = $fstmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $recentFiles = [];
                    }
                }
                ?>

                <?php if (!empty($recentFiles)): ?>
                    <?php foreach ($recentFiles as $rf): ?>
                        <div class="file-card">
                            <div class="file-icon">📄</div>
                            <div class="file-name" title="<?php echo htmlspecialchars($rf['original_name'] ?: $rf['filename']); ?>">
                                <?php echo htmlspecialchars(substr($rf['original_name'] ?: $rf['filename'], 0, 20)); ?>
                            </div>
                            <div class="file-date"><?php echo htmlspecialchars($rf['uploaded_at']); ?></div>
                            <div class="file-actions">
                                <button class="file-btn" onclick="window.location='summarize.php?file_id=<?php echo (int)$rf['id']; ?>'">Summarize</button>
                                <button class="file-btn" onclick="window.location='mock_quiz.php?file_id=<?php echo (int)$rf['id']; ?>'">Quiz</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteFile(<?= (int)$rf['id'] ?>)">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <p>📁 No files yet. Upload your first file to get started!</p>
                        <button class="btn btn-primary" id="openQuizBtn" style="margin: 0 auto;">Upload File</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

    <!-- Quiz Modal -->
    <div id="quizModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.3);z-index:1000;overflow:auto">
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;border-radius:16px;width:90%;max-width:480px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h2 style="margin:0;font-size:18px">Generate Mock Quiz</h2>
                <button id="closeQuizBtn" style="background:none;border:none;font-size:24px;cursor:pointer">&times;</button>
            </div>

            <div id="loginPrompt" style="display:none;padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;color:#333;font-size:13px">
                <strong>You need to log in</strong> to use this feature. <a href="index.php" style="color:#0066cc">Click here to login</a>
            </div>

            <div id="uploadSection" style="display:block">
                <div style="margin-bottom:12px">
                    <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Step 1: Upload a File</label>
                    <input id="fileInput" type="file" accept=".pdf,.txt,.docx,.doc,.pptx" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:8px;font-size:12px" />
                    <button id="uploadFileBtn" style="width:100%;background:#6b4bff;color:white;padding:8px;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px">Upload File</button>
                </div>

                <div style="text-align:center;margin:10px 0;font-size:12px">
                    <span style="color:#999">OR</span>
                </div>

                <div style="margin-bottom:12px">
                    <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Choose from existing files:</label>
                    <select id="fileSelect" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px">
                        <option value="">-- Loading files... --</option>
                    </select>
                </div>

                <div style="margin-bottom:12px">
                    <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px">Number of questions:</label>
                    <input id="questionCount" type="number" min="1" max="20" value="5" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px" />
                </div>

                <div id="uploadStatus" style="margin-bottom:12px;display:none;padding:10px;background:#f0f8ff;border-radius:6px;color:#0066cc;font-size:12px"></div>

                <button id="generateBtn" style="width:100%;background:linear-gradient(135deg, #6b4bff, #8b6bff);color:white;padding:10px;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px">Generate Quiz</button>
            </div>

            <div id="quizSection" style="display:none;max-height:60vh;overflow-y:auto">
                <div id="quizContent" style="margin-bottom:12px"></div>
                <button id="submitBtn" style="width:100%;background:linear-gradient(135deg, #6b4bff, #8b6bff);color:white;padding:10px;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;position:sticky;bottom:0;margin-top:12px">Submit Answers</button>
            </div>
        </div>
    </div>

    <script>
        const openBtn = document.getElementById('openQuizBtn');
    const openUploadBtn = document.getElementById('openUploadBtn');
        const closeBtn = document.getElementById('closeQuizBtn');
        const modal = document.getElementById('quizModal');
        const fileInput = document.getElementById('fileInput');
        const uploadFileBtn = document.getElementById('uploadFileBtn');
        const fileSelect = document.getElementById('fileSelect');
        const questionCount = document.getElementById('questionCount');
        const generateBtn = document.getElementById('generateBtn');
        const submitBtn = document.getElementById('submitBtn');
        const uploadSection = document.getElementById('uploadSection');
        const quizSection = document.getElementById('quizSection');
        const uploadStatus = document.getElementById('uploadStatus');
        const quizContent = document.getElementById('quizContent');
        const loginPrompt = document.getElementById('loginPrompt');

        let currentQuiz = null;
        let isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

        openBtn.addEventListener('click', () => {
            modal.style.display = 'block';
            
            if (!isLoggedIn) {
                uploadSection.style.display = 'none';
                quizSection.style.display = 'none';
                loginPrompt.style.display = 'block';
            } else {
                loginPrompt.style.display = 'none';
                uploadSection.style.display = 'block';
                loadUserFiles();
            }
        });

        // If page opened with ?open_upload=1 or #openUpload, open the upload modal automatically
        (function checkOpenUpload() {
            try {
                const params = new URLSearchParams(window.location.search);
                if (params.get('open_upload') === '1' || window.location.hash === '#openUpload') {
                    // emulate clicking the upload button
                    openUploadBtn && openUploadBtn.click();
                }
            } catch (e) {
                // ignore
            }
        })();

        // Open upload modal from the homepage 'Upload File' action button
        openUploadBtn && openUploadBtn.addEventListener('click', () => {
            // reuse the same modal behavior as Generate Quiz
            modal.style.display = 'block';
            if (!isLoggedIn) {
                uploadSection.style.display = 'none';
                quizSection.style.display = 'none';
                loginPrompt.style.display = 'block';
            } else {
                loginPrompt.style.display = 'none';
                uploadSection.style.display = 'block';
                loadUserFiles();
                // focus the file input to prompt user to pick a file
                try { fileInput.focus(); } catch (e) {}
            }
        });

        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
            uploadSection.style.display = 'block';
            quizSection.style.display = 'none';
            quizContent.innerHTML = '';
            fileInput.value = '';
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
                uploadSection.style.display = 'block';
                quizSection.style.display = 'none';
            }
        });

        // Upload file handler
        uploadFileBtn.addEventListener('click', async () => {
            if (!fileInput.files.length) {
                uploadStatus.style.display = 'block';
                uploadStatus.style.background = '#ffe0e0';
                uploadStatus.style.color = '#cc0000';
                uploadStatus.textContent = 'Please select a file to upload.';
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            uploadStatus.style.display = 'block';
            uploadStatus.style.background = '#f0f8ff';
            uploadStatus.style.color = '#0066cc';
            uploadStatus.textContent = 'Uploading file... Please wait.';
            uploadFileBtn.disabled = true;

            try {
                const response = await fetch('api/upload_and_summarize.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Upload failed');
                }

                uploadStatus.style.background = '#e0ffe0';
                uploadStatus.style.color = '#006600';
                uploadStatus.textContent = 'File uploaded successfully! Reloading files...';
                
                fileInput.value = '';
                setTimeout(() => {
                    loadUserFiles();
                }, 1000);
            } catch (err) {
                console.error('Upload error:', err);
                uploadStatus.style.background = '#ffe0e0';
                uploadStatus.style.color = '#cc0000';
                uploadStatus.textContent = 'Upload failed: ' + err.message;
                uploadFileBtn.disabled = false;
            }
        });

        async function loadUserFiles() {
            try {
                const response = await fetch('api/files.php');
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Failed to load files');
                }

                fileSelect.innerHTML = '<option value="">-- Choose a file --</option>';
                if (data.files && data.files.length > 0) {
                    data.files.forEach(file => {
                        const option = document.createElement('option');
                        option.value = file.id;
                        option.textContent = file.original_name || file.filename;
                        fileSelect.appendChild(option);
                    });
                } else {
                    fileSelect.innerHTML += '<option disabled>No files available - upload one first</option>';
                }
            } catch (err) {
                console.error('Error loading files:', err);
                fileSelect.innerHTML = '<option disabled>Error loading files</option>';
                uploadStatus.style.display = 'block';
                uploadStatus.style.background = '#ffe0e0';
                uploadStatus.style.color = '#cc0000';
                uploadStatus.textContent = 'Error loading files: ' + err.message;
            }
        }

        // Delete a file (from dashboard file cards)
        async function deleteFile(fileId) {
            if (!confirm('Delete this file and all associated data? This cannot be undone.')) return;
            try {
                const resp = await fetch('api/delete_file.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ file_id: fileId })
                });
                const data = await resp.json();
                if (!resp.ok) throw new Error(data.error || 'Failed to delete');
                // remove the card from DOM if present
                // simple approach: reload the page to reflect changes
                location.reload();
            } catch (err) {
                alert('Delete failed: ' + err.message);
            }
        }

        // preview feature removed per user request

        generateBtn.addEventListener('click', async () => {
            const fileId = fileSelect.value;
            const count = parseInt(questionCount.value);

            if (!fileId) {
                uploadStatus.style.display = 'block';
                uploadStatus.style.background = '#ffe0e0';
                uploadStatus.style.color = '#cc0000';
                uploadStatus.textContent = 'Please select a file.';
                return;
            }

            uploadStatus.style.display = 'block';
            uploadStatus.style.background = '#f0f8ff';
            uploadStatus.style.color = '#0066cc';
            uploadStatus.textContent = 'Generating quiz... Please wait.';
            generateBtn.disabled = true;

            try {
                const response = await fetch('api/generate_quiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ file_id: fileId, count })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to generate quiz');
                }

                currentQuiz = data;
                renderQuiz(data);
                uploadSection.style.display = 'none';
                quizSection.style.display = 'block';
                uploadStatus.style.display = 'none';
            } catch (err) {
                console.error('Error generating quiz:', err);
                uploadStatus.style.background = '#ffe0e0';
                uploadStatus.style.color = '#cc0000';
                uploadStatus.textContent = 'Error: ' + err.message;
                generateBtn.disabled = false;
            }
        });

        function renderQuiz(data) {
            quizContent.innerHTML = '';
            if (!data.questions || data.questions.length === 0) {
                quizContent.innerHTML = '<div style="color:#cc0000;font-size:13px">No questions were generated. Try again.</div>';
                return;
            }

            data.questions.forEach((q, idx) => {
                const questionDiv = document.createElement('div');
                questionDiv.style.cssText = 'margin-bottom:14px;padding:12px;background:#f9f9f9;border-radius:6px;border-left:3px solid #6b4bff';

                const qText = document.createElement('div');
                qText.style.cssText = 'font-weight:700;margin-bottom:8px;font-size:13px;color:#223';
                qText.textContent = `Q${idx + 1}: ${q.q}`;

                const optionsDiv = document.createElement('div');
                optionsDiv.style.cssText = 'display:flex;flex-direction:column;gap:6px';

                const options = q.options || [];
                options.forEach((opt, oIdx) => {
                    const label = document.createElement('label');
                    label.style.cssText = 'display:flex;align-items:center;cursor:pointer;padding:6px 8px;border-radius:4px;transition:background 0.2s;background:#fff;font-size:12px';
                    
                    label.addEventListener('mouseenter', () => label.style.background = '#f0f2f7');
                    label.addEventListener('mouseleave', () => label.style.background = '#fff');

                    const input = document.createElement('input');
                    input.type = 'radio';
                    input.name = `q${idx}`;
                    input.value = opt;
                    input.style.marginRight = '8px';
                    input.style.cursor = 'pointer';

                    const text = document.createTextNode(opt);

                    label.appendChild(input);
                    label.appendChild(text);
                    optionsDiv.appendChild(label);
                });

                questionDiv.appendChild(qText);
                questionDiv.appendChild(optionsDiv);
                quizContent.appendChild(questionDiv);
            });
        }

        submitBtn.addEventListener('click', () => {
            if (!currentQuiz || !currentQuiz.questions) return;

            let score = 0;
            currentQuiz.questions.forEach((q, idx) => {
                const correct = q.answer || q.correct_answer;
                const checked = document.querySelector(`input[name="q${idx}"]:checked`);

                if (checked && checked.value === correct) {
                    score++;
                }
            });

            const percentage = Math.round((score / currentQuiz.questions.length) * 100);
            const message = `Quiz Complete!\n\nYou scored: ${score}/${currentQuiz.questions.length} (${percentage}%)\n\n${
                percentage >= 80 ? 'Great job! 🎉' :
                percentage >= 60 ? 'Good effort! Keep practicing.' :
                'Try again to improve your score.'
            }`;

            alert(message);
            modal.style.display = 'none';
            uploadSection.style.display = 'block';
            quizSection.style.display = 'none';
            quizContent.innerHTML = '';
        });
    </script>

</body>
</html>
