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

$isLoggedIn = isset($_SESSION['user_id']);
$userDisplay = $_SESSION['name'] ?? $_SESSION['email'] ?? 'User';
$current = basename($_SERVER['PHP_SELF']);

// load files for selection
$files = [];
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare('SELECT id, original_name, filename FROM files WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 50');
        $stmt->execute([$_SESSION['user_id']]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $files = [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FILEASY — Mock Quiz</title>
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

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .form-group input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
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

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin: 16px 0;
            font-size: 14px;
        }

        .message.error {
            background: #ffe0e0;
            color: #cc0000;
            border: 1px solid #ffcccc;
        }

        .message.success {
            background: #e0ffe0;
            color: #006600;
            border: 1px solid #ccffcc;
        }

        .message.info {
            background: #f0f8ff;
            color: #0066cc;
            border: 1px solid #ccebff;
        }

        .quiz-container {
            display: none;
        }

        .quiz-question {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 16px;
            border-left: 4px solid #6b4bff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .quiz-question h3 {
            font-size: 16px;
            color: #1a2332;
            margin-bottom: 12px;
        }

        .quiz-option {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: #f9f9f9;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quiz-option:hover {
            background: #f0f2f7;
        }

        .quiz-option input[type="radio"] {
            margin-right: 10px;
            cursor: pointer;
        }

        .quiz-option label {
            flex: 1;
            cursor: pointer;
            margin: 0;
            font-weight: normal;
        }

        .submit-btn {
            position: sticky;
            bottom: 0;
            width: 100%;
            margin-top: 24px;
        }

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

            .topbar {
                padding: 15px 20px;
            }

            .topbar h1 {
                font-size: 18px;
            }

            .content {
                padding: 20px;
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
            <div style="display:flex;align-items:center;gap:16px">
                <h1 style="margin:0">Mock Quiz</h1>
                <a href="home.php?open_upload=1" class="btn btn-secondary" style="padding:8px 12px;border-radius:6px;text-decoration:none;color:#6b4bff">Upload File</a>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($userDisplay, 0, 1)); ?></div>
                <div class="user-name">Hello, <?php echo htmlspecialchars($userDisplay); ?></div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (!$isLoggedIn): ?>
                <div class="message error">
                    You need to <a href="index.php">log in</a> to generate quizzes.
                </div>
            <?php else: ?>
                <!-- Quiz Setup Form -->
                <div id="setupSection">
                    <div class="section-title">Generate a Mock Quiz</div>
                    
                    <div class="form-group">
                        <label for="fileSelect">Select a File:</label>
                        <select id="fileSelect">
                            <option value="">-- Choose a file --</option>
                            <?php foreach ($files as $f): ?>
                                <option value="<?php echo (int)$f['id']; ?>"><?php echo htmlspecialchars($f['original_name'] ?: $f['filename']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($files)): ?>
                            <p style="margin-top: 8px; color: #999; font-size: 13px;">
                                No files available. <a href="home.php">Upload a file first</a>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="questionCount">Number of Questions:</label>
                        <input type="number" id="questionCount" min="1" max="20" value="5" />
                    </div>

                    <div id="setupMessage" class="message" style="display: none;"></div>

                    <div style="display:flex;gap:8px;align-items:center">
                        <button id="generateBtn" class="btn btn-primary">Generate Quiz</button>
                        <!-- Preview removed -->
                        <button id="deleteBtn" class="btn" style="background:#ff4d4d;color:white">Delete File</button>
                    </div>
                </div>

                <!-- Quiz Container -->
                <div id="quizContainer" class="quiz-container">
                    <div class="section-title">Answer the following questions:</div>
                    <div id="quizContent"></div>
                    <button id="submitBtn" class="btn btn-primary submit-btn">Submit Answers</button>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    <?php if ($isLoggedIn): ?>
    const generateBtn = document.getElementById('generateBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    const submitBtn = document.getElementById('submitBtn');
    const fileSelect = document.getElementById('fileSelect');
    const questionCount = document.getElementById('questionCount');
    const setupSection = document.getElementById('setupSection');
    const quizContainer = document.getElementById('quizContainer');
    const quizContent = document.getElementById('quizContent');
    const setupMessage = document.getElementById('setupMessage');

    let currentQuiz = null;

    function showMessage(message, type) {
        setupMessage.textContent = message;
        setupMessage.className = 'message ' + type;
        setupMessage.style.display = 'block';
    }

    generateBtn.addEventListener('click', async () => {
        const fileId = fileSelect.value;
        const count = parseInt(questionCount.value);

        if (!fileId) {
            showMessage('Please select a file.', 'error');
            return;
        }

        generateBtn.disabled = true;
        generateBtn.textContent = 'Generating...';
        setupMessage.style.display = 'none';

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
            setupSection.style.display = 'none';
            quizContainer.style.display = 'block';
        } catch (err) {
            showMessage('Error: ' + err.message, 'error');
            generateBtn.disabled = false;
            generateBtn.textContent = 'Generate Quiz';
        }
    });

    // preview removed per user request

    deleteBtn && deleteBtn.addEventListener('click', async () => {
        const fileId = fileSelect.value;
        if (!fileId) {
            showMessage('Select a file first to delete.', 'error');
            return;
        }
        if (!confirm('Delete this file and all associated data? This cannot be undone.')) return;
        try {
            const resp = await fetch('api/delete_file.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file_id: fileId })
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Delete failed');
            // reload to refresh available files
            location.reload();
        } catch (err) {
            showMessage('Error: ' + err.message, 'error');
        }
    });

    function renderQuiz(data) {
        quizContent.innerHTML = '';
        
        if (!data.questions || data.questions.length === 0) {
            quizContent.innerHTML = '<div class="message error">No questions were generated. Try again.</div>';
            return;
        }

        data.questions.forEach((q, idx) => {
            const questionDiv = document.createElement('div');
            questionDiv.className = 'quiz-question';

            const titleDiv = document.createElement('h3');
            titleDiv.textContent = `Q${idx + 1}: ${q.q}`;
            questionDiv.appendChild(titleDiv);

            const options = q.options || [];
            options.forEach((opt, oIdx) => {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'quiz-option';

                const radioInput = document.createElement('input');
                radioInput.type = 'radio';
                radioInput.name = `q${idx}`;
                radioInput.value = opt;
                radioInput.id = `q${idx}_${oIdx}`;

                const label = document.createElement('label');
                label.htmlFor = `q${idx}_${oIdx}`;
                label.textContent = opt;

                optionDiv.appendChild(radioInput);
                optionDiv.appendChild(label);
                questionDiv.appendChild(optionDiv);
            });

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

        const total = currentQuiz.questions.length;
        const percentage = Math.round((score / total) * 100);
        
        const message = `Quiz Complete!\n\nYou scored: ${score}/${total} (${percentage}%)\n\n${
            percentage >= 80 ? '🎉 Great job!' :
            percentage >= 60 ? '👍 Good effort! Keep practicing.' :
            '📚 Try again to improve your score.'
        }`;

        alert(message);
        
        // Reset
        setupSection.style.display = 'block';
        quizContainer.style.display = 'none';
        fileSelect.value = '';
        questionCount.value = '5';
        quizContent.innerHTML = '';
        currentQuiz = null;
        generateBtn.textContent = 'Generate Quiz';
        generateBtn.disabled = false;
    });
    <?php endif; ?>
</script>

</body>
</html>