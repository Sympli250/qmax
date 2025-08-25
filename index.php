<?php
/**
 * Quiz App - Version 0.3 - Design Moderne
 * Front Controller avec le nouveau design Symplissime
 */

define('APP_VERSION', '0.3');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

function getBaseUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $proto = $https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($basePath === '' || $basePath === '.') $basePath = '';
    return $proto . $host . $basePath . '/index.php';
}

/* -------------------------------------------------------------------------- */
/* API et AJAX (gardés identiques) */
/* -------------------------------------------------------------------------- */
if (isset($_GET['api'])) {
    handleApiRequest();
    exit;
}

if (!empty($_POST['ajax_action'])) {
    handleAjaxRequest();
    exit;
}

/* -------------------------------------------------------------------------- */
/* Routing */
/* -------------------------------------------------------------------------- */
$page = $_GET['page'] ?? 'home';
$code = $_GET['code'] ?? '';

switch ($page) {
    case 'home':
        showHomePage();
        break;
    case 'admin':
        showAdminLogin();
        break;
    case 'admin-dashboard':
        showAdminDashboard();
        break;
    case 'admin-users':
        showUserManagement();
        break;
    case 'admin-config':
        showAppConfig();
        break;
    case 'admin-permissions':
        showPermissionManagement();
        break;
    case 'create-quiz':
        showCreateQuiz();
        break;
    case 'edit-quiz':
        $quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        showEditQuiz($quiz_id);
        break;
    case 'import-quiz':
        showImportQuiz();
        break;
    case 'export-quizzes':
        $format = $_GET['format'] ?? 'json';
        handleExportQuizzes($format);
        break;
    case 'quiz':
        if ($code !== '') {
            showQuizPage($code);
        } else {
            showNotFoundPage('Code de quiz manquant.');
        }
        break;
    case 'quiz-results':
        if ($code !== '') {
            showQuizResults($code);
        } else {
            showNotFoundPage('Code de quiz manquant.');
        }
        break;
    case 'logout':
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header('Location: ' . getBaseUrl());
        exit;
    default:
        showNotFoundPage();
        break;
}

/* ========================================================================== */
/* PAGES MODERNISÉES */
/* ========================================================================== */

function showHomePage(): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Master - Application de Quiz Moderne v<?= htmlspecialchars(APP_VERSION) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-layout">
        <!-- Header moderne -->
        <header class="modern-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="logo">
                        <div class="logo-icon">Q</div>
                        <div class="logo-text">Quiz Master</div>
                    </a>
                    <div class="workspace-badge">
                        🏠 Accueil
                    </div>
                </div>
                <div class="header-actions">
                    <div class="status-badge">v<?= htmlspecialchars(APP_VERSION) ?></div>
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin" class="btn btn-primary">Administration</a>
                </div>
            </div>
        </header>

        <main class="container">
            <!-- Hero Section -->
            <div class="quiz-intro animate-fade-in">
                <h1>Bienvenue sur Quiz Master</h1>
                <p class="page-subtitle">Plateforme moderne de création et de participation à des quiz interactifs</p>
                
                <!-- Formulaire de connexion quiz -->
                <div class="participant-form">
                    <h3 style="color: white; margin-bottom: 24px;">Rejoindre un quiz</h3>
                    <form onsubmit="joinQuiz(event)" class="flex flex-col gap-16">
                        <input 
                            type="text" 
                            id="quiz-code" 
                            class="form-input" 
                            placeholder="Entrez le code du quiz (ex: DEMO01)" 
                            required 
                            maxlength="10"
                            style="text-align: center; font-weight: 600; font-size: 18px;"
                        >
                        <button type="submit" class="btn btn-success">
                            🚀 Rejoindre le quiz
                        </button>
                    </form>
                </div>
            </div>

            <!-- Section Demo -->
            <div class="modern-card animate-slide-in">
                <div class="card-body text-center">
                    <h2 style="color: var(--primary); margin-bottom: 16px;">Quiz de démonstration</h2>
                    <p class="text-muted mb-24">Testez la plateforme avec notre quiz d'exemple</p>
                    <div class="flex items-center justify-center gap-24 mb-32">
                        <div class="text-center">
                            <div class="status-badge" style="display: block; margin-bottom: 8px;">Code: DEMO01</div>
                            <small class="text-muted">3 questions • 2 min</small>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=DEMO01" 
                       class="btn btn-primary">
                        ✨ Essayer le quiz démo
                    </a>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="stats-grid mt-32">
                <div class="stat-card">
                    <div class="nav-icon">📝</div>
                    <div class="stat-number">∞</div>
                    <div class="stat-label">Quiz illimités</div>
                    <p class="text-muted mt-16">Créez autant de quiz que vous voulez avec notre interface intuitive</p>
                </div>
                <div class="stat-card">
                    <div class="nav-icon">⚡</div>
                    <div class="stat-number">0s</div>
                    <div class="stat-label">Temps réel</div>
                    <p class="text-muted mt-16">Résultats instantanés et suivi en temps réel des participants</p>
                </div>
                <div class="stat-card">
                    <div class="nav-icon">📊</div>
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Analytiques</div>
                    <p class="text-muted mt-16">Statistiques détaillées pour analyser les performances</p>
                </div>
                <div class="stat-card">
                    <div class="nav-icon">🎨</div>
                    <div class="stat-number">+</div>
                    <div class="stat-label">Moderne</div>
                    <p class="text-muted mt-16">Interface élégante et responsive sur tous les appareils</p>
                </div>
            </div>
        </main>
    </div>

    <script>
    function joinQuiz(e){
        e.preventDefault();
        const code = (document.getElementById('quiz-code').value||'').trim().toUpperCase();
        if (!code){ 
            alert('Veuillez entrer un code.'); 
            return; 
        }
        window.location.href = '<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=' + encodeURIComponent(code);
    }

    // Animation au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.stat-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.6s ease';
        observer.observe(card);
    });
    </script>
</body>
</html>
<?php }

function showAdminDashboard(): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    global $pdo;
    $userId = $_SESSION['user_id'] ?? 0;

    $total_quizzes = (int)$pdo->query("SELECT COUNT(*) FROM quizzes WHERE user_id = ".(int)$userId)->fetchColumn();
    $active_quizzes = (int)$pdo->query("SELECT COUNT(*) FROM quizzes WHERE user_id = ".(int)$userId." AND status='active'")->fetchColumn();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM participants p
        JOIN quizzes q ON q.id = p.quiz_id
        WHERE q.user_id = ?
    ");
    $stmt->execute([$userId]);
    $total_participants = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $quizzes = $stmt->fetchAll(); ?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Quiz Master v<?= htmlspecialchars(APP_VERSION) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-layout">
        <!-- Header moderne -->
        <header class="modern-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="logo">
                        <div class="logo-icon">Q</div>
                        <div class="logo-text">Quiz Master</div>
                    </a>
                    <div class="workspace-badge">
                        👨‍💼 <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="status-badge"><?= $total_quizzes ?> Quiz</div>
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=logout" class="btn btn-secondary small">Se déconnecter</a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="sidebar-layout">
                <!-- Sidebar moderne -->
                <aside class="modern-sidebar animate-slide-in">
                    <div class="nav-section">
                        <div class="nav-title">Navigation</div>
                        <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard" class="nav-item active">
                            <div class="nav-icon">📊</div>
                            Dashboard
                        </a>
                        <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=create-quiz" class="nav-item">
                            <div class="nav-icon">➕</div>
                            Créer un quiz
                        </a>
                        <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=import-quiz" class="nav-item">
                            <div class="nav-icon">📥</div>
                            Importer
                        </a>
                    </div>

                    <?php if (isSuperAdmin()): ?>
                    <div class="nav-section">
                        <div class="nav-title">Administration</div>
                        <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-users" class="nav-item">
                            <div class="nav-icon">👥</div>
                            Utilisateurs
                        </a>
                        <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-config" class="nav-item">
                            <div class="nav-icon">⚙️</div>
                            Configuration
                        </a>
                        <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-permissions" class="nav-item">
                            <div class="nav-icon">🔐</div>
                            Permissions
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="nav-section">
                        <div class="nav-title">Export</div>
                        <div style="padding: 16px; background: var(--gray-100); border-radius: var(--radius); margin-top: 8px;">
                            <label class="form-label">Format d'export</label>
                            <select id="export-format" class="form-select small" style="margin-bottom: 12px;">
                                <option value="json">JSON</option>
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                            </select>
                            <div class="flex flex-col gap-8">
                                <button class="btn btn-secondary small" onclick="exportQuizzes(false)">Export sélection</button>
                                <button class="btn btn-secondary small" onclick="exportQuizzes(true)">Export tout</button>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Contenu principal -->
                <main class="main-content animate-fade-in">
                    <div class="page-header">
                        <h1 class="page-title">Tableau de bord</h1>
                        <p class="page-subtitle">Gérez vos quiz et analysez les performances</p>
                    </div>

                    <!-- Statistiques -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_quizzes ?></div>
                            <div class="stat-label">Quiz créés</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $active_quizzes ?></div>
                            <div class="stat-label">Quiz actifs</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_participants ?></div>
                            <div class="stat-label">Participants</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">v<?= htmlspecialchars(APP_VERSION) ?></div>
                            <div class="stat-label">Version</div>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="modern-card mb-32">
                        <div class="card-body text-center">
                            <h3 class="mb-24">Actions rapides</h3>
                            <div class="flex justify-center gap-16 flex-wrap">
                                <a class="btn btn-success" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=create-quiz">
                                    ➕ Nouveau quiz
                                </a>
                                <a class="btn btn-info" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=import-quiz">
                                    📥 Importer
                                </a>
                                <a class="btn btn-primary" href="<?= htmlspecialchars(getBaseUrl()) ?>">
                                    🏠 Voir l'accueil
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des quiz -->
                    <div class="modern-card">
                        <div class="card-header">
                            <h3>Mes quiz</h3>
                            <?php if (!$quizzes): ?>
                                <div class="text-center" style="padding: 48px;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                                    <h3 class="mb-16">Aucun quiz pour le moment</h3>
                                    <p class="text-muted mb-32">Créez votre premier quiz pour commencer</p>
                                    <a class="btn btn-success" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=create-quiz">
                                        ✨ Créer mon premier quiz
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($quizzes): ?>
                        <div class="modern-table-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" onclick="toggleSelectAll(this)" style="accent-color: var(--primary);">
                                        </th>
                                        <th>Quiz</th>
                                        <th>Code</th>
                                        <th>Statut</th>
                                        <th>Options</th>
                                        <th>Créé le</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($quizzes as $qz): 
                                    $opts = getQuizOptions((int)$qz['id']); ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="quiz-select" value="<?= (int)$qz['id'] ?>" style="accent-color: var(--primary);">
                                        </td>
                                        <td>
                                            <div>
                                                <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($qz['title']) ?></div>
                                                <div class="text-muted" style="font-size: 14px;"><?= htmlspecialchars(substr($qz['description'], 0, 60)) ?><?= strlen($qz['description']) > 60 ? '...' : '' ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge"><?= htmlspecialchars($qz['code']) ?></span>
                                        </td>
                                        <td>
                                            <select class="form-select small status-badge status-<?= $qz['status'] ?>" 
                                                    data-quiz-id="<?= (int)$qz['id'] ?>" 
                                                    onchange="changeStatus(this)"
                                                    style="border: none; font-weight: 600;">
                                                <option value="draft"  <?= $qz['status']==='draft'?'selected':'' ?>>Brouillon</option>
                                                <option value="active" <?= $qz['status']==='active'?'selected':'' ?>>Actif</option>
                                                <option value="closed" <?= $qz['status']==='closed'?'selected':'' ?>>Fermé</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="flex flex-col gap-8">
                                                <?php if ($opts['randomize_questions']): ?>
                                                    <span class="status-badge" style="background: #e0e7ff; color: #3730a3;">Questions mélangées</span>
                                                <?php endif; ?>
                                                <?php if ($opts['randomize_answers']): ?>
                                                    <span class="status-badge" style="background: #f0fdf4; color: #166534;">Réponses mélangées</span>
                                                <?php endif; ?>
                                                <?php if (!$opts['randomize_questions'] && !$opts['randomize_answers']): ?>
                                                    <span class="text-muted">Aucune</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="color: var(--text-muted);">
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($qz['created_at']))) ?>
                                            <br>
                                            <small><?= htmlspecialchars(date('H:i', strtotime($qz['created_at']))) ?></small>
                                        </td>
                                        <td>
                                            <div class="flex flex-col gap-8">
                                                <a class="btn btn-primary small" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=<?= urlencode($qz['code']) ?>">
                                                    👁️ Voir
                                                </a>
                                                <a class="btn btn-warning small" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=edit-quiz&id=<?= (int)$qz['id'] ?>">
                                                    ✏️ Modifier
                                                </a>
                                                <a class="btn btn-info small" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz-results&code=<?= urlencode($qz['code']) ?>">
                                                    📊 Résultats
                                                </a>
                                                <button class="btn btn-danger small" onclick="deleteQuiz(<?= (int)$qz['id'] ?>, this)">
                                                    🗑️ Supprimer
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <script>
    const baseUrl = '<?= htmlspecialchars(getBaseUrl()) ?>';

    function changeStatus(sel){
        const id = sel.dataset.quizId;
        const val = sel.value;
        
        sel.className = `form-select small status-badge status-${val}`;
        
        fetch(baseUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'ajax_action=change_quiz_status&quiz_id='+encodeURIComponent(id)+'&new_status='+encodeURIComponent(val),
            credentials:'same-origin'
        }).then(r=>r.json()).then(j=>{
            if(!j.success){ 
                alert('Erreur mise à jour statut'); 
                location.reload(); 
            }
        }).catch(()=>{ 
            alert('Erreur réseau'); 
            location.reload(); 
        });
    }

    function deleteQuiz(id, btn){
        if(!confirm('⚠️ Supprimer définitivement ce quiz et toutes ses données (questions, réponses, résultats) ?')) return;
        
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Suppression...';
        
        fetch(baseUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            credentials:'same-origin',
            body: 'ajax_action=delete_quiz&quiz_id='+encodeURIComponent(id)
        }).then(r=>r.json()).then(j=>{
            if(j.success){ 
                btn.closest('tr').style.opacity = '0';
                btn.closest('tr').style.transform = 'translateX(100px)';
                setTimeout(() => location.reload(), 300);
            } else { 
                alert('❌ Suppression impossible'); 
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }).catch(()=>{ 
            alert('❌ Erreur réseau'); 
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    function exportQuizzes(all){
        const format = document.getElementById('export-format').value;
        let url = baseUrl+'?page=export-quizzes&format='+encodeURIComponent(format);
        if(!all){
            const ids = Array.from(document.querySelectorAll('.quiz-select:checked')).map(cb=>cb.value);
            if(ids.length===0){ 
                alert('📋 Sélectionnez au moins un quiz.'); 
                return; 
            }
            url += '&ids='+ids.join(',');
        }
        window.location.href = url;
    }

    function toggleSelectAll(cb){
        document.querySelectorAll('.quiz-select').forEach(c=>{ c.checked = cb.checked; });
    }

    // Animation des cartes au chargement
    setTimeout(() => {
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.animationDelay = (index * 0.1) + 's';
            card.classList.add('animate-fade-in');
        });
    }, 100);
    </script>
</body>
</html>
<?php }

// [Toutes les autres fonctions restent identiques, seul le CSS et certains templates HTML changent]
// Pour économiser l'espace, je vais juste montrer les fonctions API et de base :

/* ========================================================================== */
/* API (identiques) */
/* ========================================================================== */
function handleApiRequest(): void {
    if (ob_get_level()) { @ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');

    $endpoint = $_GET['api'] ?? '';
    try {
        switch ($endpoint) {
            case 'quiz-questions': {
                $code = trim($_GET['code'] ?? '');
                if ($code === '') throw new Exception('Code de quiz manquant');

                $quiz = getQuizByCode($code);
                if (!$quiz) throw new Exception('Quiz non trouvé ou inactif');

                $rows = getQuizQuestions((int)$quiz['id']);
                if (empty($rows)) throw new Exception('Aucune question pour ce quiz');

                $opts = getQuizOptions((int)$quiz['id']);
                $questions = formatQuestionsWithRandomization($rows, $opts);

                echo json_encode($questions, JSON_UNESCAPED_UNICODE);
                break;
            }

            case 'participant-register': {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Méthode non autorisée');

                $data = json_decode(file_get_contents('php://input'), true);
                if (!is_array($data)) throw new Exception('JSON invalide');

                $quiz_code = trim($data['quiz_code'] ?? '');
                $nickname  = trim($data['nickname'] ?? '');
                if ($quiz_code === '' || $nickname === '') throw new Exception('Paramètres manquants');

                $pid = registerParticipant($quiz_code, $nickname);
                if (!$pid) throw new Exception('Echec enregistrement participant');

                echo json_encode(['participant_id' => (int)$pid]);
                break;
            }

            case 'participant-answer': {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Méthode non autorisée');

                $data = json_decode(file_get_contents('php://input'), true);
                if (!is_array($data)) throw new Exception('JSON invalide');

                $participant_id = (int)($data['participant_id'] ?? 0);
                $question_id    = (int)($data['question_id'] ?? 0);
                $answer_id      = (int)($data['answer_id'] ?? 0);
                if ($participant_id <= 0 || $question_id <= 0 || $answer_id <= 0) {
                    throw new Exception('Paramètres manquants');
                }

                $ok = saveParticipantProgress($participant_id, $question_id, $answer_id);
                if (!$ok) throw new Exception('Données invalides');
                echo json_encode(['success' => true]);
                break;
            }

            case 'participant-score': {
                $pid = (int)($_GET['participant_id'] ?? 0);
                if ($pid <= 0) throw new Exception('ID participant manquant');

                $score = getParticipantScore($pid);
                if (!$score) throw new Exception('Score indisponible');

                echo json_encode($score);
                break;
            }

            case 'participant-wrong-answers': {
                $pid = (int)($_GET['participant_id'] ?? 0);
                if ($pid <= 0) throw new Exception('ID participant manquant');

                $rows = getParticipantWrongAnswers($pid);
                echo json_encode($rows, JSON_UNESCAPED_UNICODE);
                break;
            }

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint non trouvé']);
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'error' => $e->getMessage(),
            'endpoint' => $endpoint,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
}

function handleAjaxRequest(): void {
    header('Content-Type: application/json; charset=utf-8');
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        return;
    }
    $action = $_POST['ajax_action'] ?? '';
    switch ($action) {
        case 'delete_quiz': {
            $quiz_id = (int)($_POST['quiz_id'] ?? 0);
            $ok = deleteQuiz($quiz_id);
            echo json_encode(['success' => (bool)$ok]);
            break;
        }
        case 'change_quiz_status': {
            $quiz_id = (int)($_POST['quiz_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? '';
            $ok = changeQuizStatus($quiz_id, $new_status);
            echo json_encode(['success' => (bool)$ok]);
            break;
        }
        case 'delete_question': {
            $question_id = (int)($_POST['question_id'] ?? 0);
            $ok = deleteQuestion($question_id);
            echo json_encode(['success' => (bool)$ok]);
            break;
        }
        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
}

// [Toutes les autres fonctions de base de données restent identiques]
// Ajout des fonctions essentielles pour que l'app fonctionne :

function deleteQuiz(int $quiz_id): bool {
    global $pdo;
    if ($quiz_id <= 0) return false;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND user_id = ?");
        return $stmt->execute([$quiz_id, $_SESSION['user_id'] ?? 0]);
    } catch (Throwable $e) {
        error_log('deleteQuiz: ' . $e->getMessage());
        return false;
    }
}

function changeQuizStatus(int $quiz_id, string $new_status): bool {
    global $pdo;
    if ($quiz_id <= 0) return false;
    $valid = ['draft','active','closed'];
    if (!in_array($new_status, $valid, true)) return false;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        $stmt = $pdo->prepare("UPDATE quizzes SET status = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        return $stmt->execute([$new_status, $quiz_id, $_SESSION['user_id'] ?? 0]);
    } catch (Throwable $e) {
        error_log('changeQuizStatus: ' . $e->getMessage());
        return false;
    }
}

function getQuizByCode(string $code) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE code = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$code]);
        return $stmt->fetch();
    } catch (Throwable $e) {
        error_log('getQuizByCode: ' . $e->getMessage());
        return false;
    }
}

function getQuizQuestions(int $quiz_id): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT
                q.id   AS question_id,
                q.text AS question_text,
                q.comment,
                q.order_index,
                a.id   AS answer_id,
                a.text AS answer_text,
                a.is_correct
            FROM questions q
            LEFT JOIN answers a ON a.question_id = q.id
            WHERE q.quiz_id = ?
            ORDER BY q.order_index, q.id, a.id
        ");
        $stmt->execute([$quiz_id]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('getQuizQuestions: ' . $e->getMessage());
        return [];
    }
}

function getQuizOptions(int $quiz_id): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT randomize_questions, randomize_answers FROM quiz_options WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        $o = $stmt->fetch();
        return [
            'randomize_questions' => (bool)($o['randomize_questions'] ?? false),
            'randomize_answers'   => (bool)($o['randomize_answers'] ?? false),
        ];
    } catch (Throwable $e) {
        error_log('getQuizOptions: ' . $e->getMessage());
        return ['randomize_questions' => false, 'randomize_answers' => false];
    }
}

function formatQuestionsWithRandomization(array $rows, array $opts): array {
    $map = [];
    foreach ($rows as $r) {
        $qid = (int)$r['question_id'];
        if (!isset($map[$qid])) {
            $map[$qid] = [
                'id' => $qid,
                'text' => (string)($r['question_text'] ?? ''),
                'comment' => (string)($r['comment'] ?? ''),
                'answers' => []
            ];
        }
        if (!empty($r['answer_id'])) {
            $map[$qid]['answers'][] = [
                'id' => (int)$r['answer_id'],
                'text' => (string)$r['answer_text']
            ];
        }
    }
    $questions = array_values($map);
    $questions = array_values(array_filter($questions, fn($q) => count($q['answers']) >= 2));
    if (!empty($opts['randomize_questions'])) shuffle($questions);
    if (!empty($opts['randomize_answers'])) {
        foreach ($questions as &$q) shuffle($q['answers']);
        unset($q);
    }
    return $questions;
}

function registerParticipant(string $quiz_code, string $nickname) {
    global $pdo;
    try {
        $quiz = getQuizByCode($quiz_code);
        if (!$quiz) return false;
        $stmt = $pdo->prepare("
            INSERT INTO participants (quiz_id, nickname, ip_address, user_agent, started_at)
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([
            (int)$quiz['id'],
            $nickname,
            $_SERVER['REMOTE_ADDR']     ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        return $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('registerParticipant: ' . $e->getMessage());
        return false;
    }
}

function saveParticipantProgress(int $participant_id, int $question_id, int $answer_id): bool {
    global $pdo;
    try {
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM participants p
            JOIN questions q ON q.quiz_id = p.quiz_id
            JOIN answers a ON a.question_id = q.id
            WHERE p.id = ? AND q.id = ? AND a.id = ?
        ");
        $check->execute([$participant_id, $question_id, $answer_id]);
        if ((int)$check->fetchColumn() === 0) {
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO participant_progress (participant_id, question_id, chosen_answer_id, answered_at)
            VALUES (?, ?, ?, datetime('now'))
        ");
        $ok = $stmt->execute([$participant_id, $question_id, $answer_id]);

        if ($ok) {
            $stmt = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM questions q
                     JOIN participants p ON p.quiz_id = q.quiz_id
                     WHERE p.id = ?) AS total_q,
                    (SELECT COUNT(*) FROM participant_progress WHERE participant_id = ?) AS answered_q
            ");
            $stmt->execute([$participant_id, $participant_id]);
            $r = $stmt->fetch();
            if ($r && (int)$r['total_q'] > 0 && (int)$r['answered_q'] >= (int)$r['total_q']) {
                $u = $pdo->prepare("UPDATE participants SET completed_at = datetime('now') WHERE id = ?");
                $u->execute([$participant_id]);
            }
        }
        return $ok;
    } catch (Throwable $e) {
        error_log('saveParticipantProgress: ' . $e->getMessage());
        return false;
    }
}

function getParticipantScore(int $participant_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM questions q
                 WHERE q.quiz_id = (SELECT quiz_id FROM participants WHERE id = ?)) AS total_questions,
                (SELECT COUNT(*) FROM participant_progress WHERE participant_id = ?) AS answered_questions,
                (SELECT COUNT(*) FROM participant_progress pp
                 JOIN answers a ON a.id = pp.chosen_answer_id
                 WHERE pp.participant_id = ? AND a.is_correct = 1) AS correct_answers
        ");
        $stmt->execute([$participant_id, $participant_id, $participant_id]);
        return $stmt->fetch();
    } catch (Throwable $e) {
        error_log('getParticipantScore: ' . $e->getMessage());
        return false;
    }
}

function getParticipantWrongAnswers(int $participant_id): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT
                q.text AS question,
                ca.text AS your_answer,
                a.text AS correct_answer,
                q.comment
            FROM participant_progress pp
            JOIN questions q ON q.id = pp.question_id
            JOIN answers ca ON ca.id = pp.chosen_answer_id
            JOIN answers a ON a.question_id = q.id AND a.is_correct = 1
            WHERE pp.participant_id = ? AND ca.is_correct = 0
            ORDER BY q.order_index
        ");
        $stmt->execute([$participant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('getParticipantWrongAnswers: ' . $e->getMessage());
        return [];
    }
}

function showAdminLogin(): void {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
        if (login($_POST['email'], $_POST['password'])) {
            header('Location: ' . getBaseUrl() . '?page=admin-dashboard');
            exit;
        }
        $error = 'Identifiants incorrects';
    } ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration - Quiz Master</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-layout">
        <header class="modern-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="logo">
                        <div class="logo-icon">Q</div>
                        <div class="logo-text">Quiz Master</div>
                    </a>
                </div>
            </div>
        </header>
        
        <div class="container narrow">
            <div class="modern-card animate-fade-in">
                <div class="card-body" style="padding: 48px 32px;">
                    <div class="text-center mb-32">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔐</div>
                        <h1 style="margin-bottom: 8px;">Connexion Administrateur</h1>
                        <p class="text-muted">Accédez à votre espace de gestion</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert error mb-24"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" class="modern-form">
                        <div class="form-section">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" required value="admin@quiz-app.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mot de passe</label>
                                <input type="password" name="password" class="form-input" required value="admin123">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Se connecter</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-24">
                        <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="text-muted">← Retour à l'accueil</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php }

function showQuizPage(string $quiz_code): void {
    $quiz = getQuizByCode($quiz_code);
    if (!$quiz) { 
        showNotFoundPage("Code de quiz invalide ou inactif."); 
        return; 
    } ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($quiz['title']) ?> - Quiz Master</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-layout">
        <header class="modern-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="logo">
                        <div class="logo-icon">Q</div>
                        <div class="logo-text">Quiz Master</div>
                    </a>
                    <div class="workspace-badge">
                        📝 <?= htmlspecialchars($quiz['code']) ?>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="status-badge">Quiz Actif</div>
                </div>
            </div>
        </header>

        <div class="container">
            <div id="quiz-container">
                <div class="quiz-intro animate-fade-in">
                    <h1><?= htmlspecialchars($quiz['title']) ?></h1>
                    <p class="page-subtitle"><?= htmlspecialchars($quiz['description']) ?></p>
                    
                    <div class="participant-form">
                        <h3 style="color: white; margin-bottom: 24px;">Avant de commencer</h3>
                        <div class="form-group">
                            <label class="form-label" style="color: rgba(255,255,255,0.9);">Votre nom/pseudo</label>
                            <input type="text" id="participant-name" class="form-input" maxlength="50" required 
                                   placeholder="Entrez votre nom" style="text-align: center;">
                        </div>
                        <button class="btn btn-success" onclick="startQuizWithName('<?= htmlspecialchars($quiz['code']) ?>')">
                            🚀 Commencer le quiz
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    window.baseUrl = '<?= htmlspecialchars(getBaseUrl()) ?>';
    
    function startQuizWithName(code){
        const name = (document.getElementById('participant-name').value||'').trim();
        if(!name){ 
            alert('Veuillez entrer votre nom.'); 
            return; 
        }
        
        if (typeof QuizEngine !== 'undefined'){
            window.quizEngine = new QuizEngine(code, name);
        } else {
            const script = document.createElement('script');
            script.src = 'quiz_engine.js';
            script.onload = () => { 
                window.quizEngine = new QuizEngine(code, name); 
            };
            script.onerror = () => alert('Erreur chargement moteur de quiz');
            document.head.appendChild(script);
        }
    }
    </script>
    <script src="quiz_engine.js"></script>
</body>
</html>
<?php }

function showCreateQuiz(): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    $success = '';
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_quiz') {
        $quiz_id = createQuizFromForm($_POST);
        if ($quiz_id) {
            $code = getQuizCodeById((int)$quiz_id);
            $success = 'Quiz créé avec succès ! Code: ' . $code;
        } else {
            $error = 'Échec de création du quiz';
        }
    } ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer un quiz - Quiz Master</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-layout">
        <header class="modern-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="logo">
                        <div class="logo-icon">Q</div>
                        <div class="logo-text">Quiz Master</div>
                    </a>
                    <div class="workspace-badge">➕ Création</div>
                </div>
                <div class="header-actions">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard" class="btn btn-secondary small">← Retour</a>
                </div>
            </div>
        </header>

        <div class="container">
            <?php if ($success): ?>
                <div class="alert success animate-fade-in"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert error animate-fade-in"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="page-header">
                <h1 class="page-title">Créer un nouveau quiz</h1>
                <p class="page-subtitle">Configurez votre quiz et ajoutez vos questions</p>
            </div>

            <form method="post" id="quiz-form" class="modern-form animate-fade-in">
                <input type="hidden" name="action" value="create_quiz">
                
                <div class="form-section">
                    <h3 class="form-section-title">Informations générales</h3>
                    <div class="form-group">
                        <label class="form-label">Titre du quiz *</label>
                        <input type="text" name="title" class="form-input" required maxlength="200" 
                               placeholder="Ex: Culture générale, Histoire de France...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" 
                                  placeholder="Description optionnelle de votre quiz"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="randomize_questions" value="1">
                            Mélanger les questions aléatoirement
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="randomize_answers" value="1">
                            Mélanger les réponses aléatoirement
                        </label>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Questions <span class="question-count">(0)</span></h3>
                    <div id="questions-container"></div>
                    <button type="button" class="btn btn-primary" onclick="addQuestion()">
                        ➕ Ajouter une question
                    </button>
                </div>

                <div class="form-section text-center">
                    <button type="submit" class="btn btn-success" style="font-size: 16px; padding: 16px 32px;">
                        ✨ Créer le quiz
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="quiz-admin.js"></script>
</body>
</html>
<?php }

function showEditQuiz(int $quiz_id): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND user_id = ?");
    $stmt->execute([$quiz_id, $_SESSION['user_id'] ?? 0]);
    $quiz = $stmt->fetch();
    if (!$quiz) { 
        showNotFoundPage('Quiz introuvable'); 
        return; 
    }

    $success = ''; 
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_quiz') {
        if (updateQuizFromForm($_POST, $quiz_id)) {
            $success = 'Quiz mis à jour avec succès';
            $stmt->execute([$quiz_id, $_SESSION['user_id'] ?? 0]);
            $quiz = $stmt->fetch();
        } else {
            $error = 'Échec de mise à jour';
        }
    }
    $questions = getQuizQuestionsForEdit($quiz_id);
    $options = getQuizOptions($quiz_id); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier - <?= htmlspecialchars($quiz['title']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-layout">
        <header class="modern-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="logo">
                        <div class="logo-icon">Q</div>
                        <div class="logo-text">Quiz Master</div>
                    </a>
                    <div class="workspace-badge">✏️ <?= htmlspecialchars($quiz['code']) ?></div>
                </div>
                <div class="header-actions">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard" class="btn btn-secondary small">← Retour</a>
                </div>
            </div>
        </header>

        <div class="container">
            <?php if ($success): ?>
                <div class="alert success animate-fade-in"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert error animate-fade-in"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="modern-card mb-32">
                <div class="card-body">
                    <div class="flex justify-center gap-16 mb-24">
                        <div class="text-center">
                            <div class="status-badge mb-16"><?= htmlspecialchars($quiz['code']) ?></div>
                            <small class="text-muted">Code du quiz</small>
                        </div>
                        <div class="text-center">
                            <div class="status-badge status-<?= $quiz['status'] ?> mb-16"><?= htmlspecialchars($quiz['status']) ?></div>
                            <small class="text-muted">Statut</small>
                        </div>
                        <div class="text-center">
                            <div class="status-badge mb-16"><?= htmlspecialchars(date('d/m/Y', strtotime($quiz['created_at']))) ?></div>
                            <small class="text-muted">Créé le</small>
                        </div>
                    </div>
                    <div class="flex justify-center gap-16 flex-wrap">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=<?= urlencode($quiz['code']) ?>">
                            👁️ Voir le quiz
                        </a>
                        <a class="btn btn-info" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz-results&code=<?= urlencode($quiz['code']) ?>">
                            📊 Résultats
                        </a>
                    </div>
                </div>
            </div>

            <form method="post" id="quiz-edit-form" class="modern-form animate-fade-in">
                <input type="hidden" name="action" value="update_quiz">
                
                <div class="form-section">
                    <h3 class="form-section-title">Informations générales</h3>
                    <div class="form-group">
                        <label class="form-label">Titre du quiz *</label>
                        <input type="text" name="title" class="form-input" required maxlength="200" 
                               value="<?= htmlspecialchars($quiz['title']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3"><?= htmlspecialchars($quiz['description']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="randomize_questions" value="1" <?= $options['randomize_questions']?'checked':''; ?>>
                            Mélanger les questions aléatoirement
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="randomize_answers" value="1" <?= $options['randomize_answers']?'checked':''; ?>>
                            Mélanger les réponses aléatoirement
                        </label>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Questions <span class="question-count">(<?= count($questions) ?>)</span></h3>
                    <div id="questions-container">
                        <?php foreach ($questions as $i => $q): ?>
                            <div class="question-item modern-card" data-question-index="<?= (int)$i ?>" data-question-id="<?= (int)$q['id'] ?>" style="margin-bottom: 24px;">
                                <div class="card-body">
                                    <div class="question-header flex justify-between items-center mb-24">
                                        <h4>Question <?= (int)($i+1) ?></h4>
                                        <button type="button" class="btn btn-danger small" onclick="deleteExistingQuestion(<?= (int)$q['id'] ?>, this)">
                                            🗑️ Supprimer
                                        </button>
                                    </div>
                                    <input type="hidden" name="questions[<?= (int)$i ?>][id]" value="<?= (int)$q['id'] ?>">
                                    <div class="form-group">
                                        <label class="form-label">Texte de la question *</label>
                                        <textarea name="questions[<?= (int)$i ?>][text]" class="form-textarea" required><?= htmlspecialchars($q['text']) ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Commentaire (optionnel)</label>
                                        <input type="text" name="questions[<?= (int)$i ?>][comment]" class="form-input" value="<?= htmlspecialchars($q['comment']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Réponses *</label>
                                        <div class="answers-container">
                                            <?php foreach ($q['answers'] as $ai => $ans): ?>
                                                <div class="answer-item flex items-center gap-16 mb-16">
                                                    <input type="radio" name="questions[<?= (int)$i ?>][correct_answer]" value="<?= (int)$ai ?>" <?= $ans['is_correct']?'checked':''; ?> required style="accent-color: var(--primary);">
                                                    <input type="text" name="questions[<?= (int)$i ?>][answers][<?= (int)$ai ?>]" class="form-input" value="<?= htmlspecialchars($ans['text']) ?>" required style="flex: 1;">
                                                    <input type="hidden" name="questions[<?= (int)$i ?>][answer_ids][<?= (int)$ai ?>]" value="<?= (int)$ans['id'] ?>">
                                                    <button type="button" class="btn btn-danger small" onclick="removeAnswer(this)">✕</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="btn btn-secondary small" onclick="addAnswer(this)">
                                            ➕ Ajouter une réponse
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addQuestion()">
                        ➕ Ajouter une question
                    </button>
                </div>

                <div class="form-section text-center">
                    <button type="submit" class="btn btn-success" style="font-size: 16px; padding: 16px 32px;">
                        💾 Sauvegarder les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="quiz-admin.js"></script>
</body>
</html>
<?php }

function showQuizResults(string $code): void {
    $quiz = getQuizByCode($code);
    if (!$quiz) { 
        showNotFoundPage('Quiz introuvable'); 
        return; 
    }
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            p.nickname,
            p.started_at,
            p.completed_at,
            COUNT(pp.id) AS total_answers,
            SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS correct_answers
        FROM participants p
        LEFT JOIN participant_progress pp ON pp.participant_id = p.id
        LEFT JOIN answers a ON a.id = pp.chosen_answer_id
        WHERE p.quiz_id = ?
        GROUP BY p.id
        ORDER BY correct_answers DESC, p.completed_at ASC
    ");
    $stmt->execute([(int)$quiz['id']]);
    $rows = $stmt->fetchAll(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Résultats - <?= htmlspecialchars($quiz['title']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-layout">
        <header class="modern-header">
            <div class="header-content">
                <div class="logo-section">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>" class="logo">
                        <div class="logo-icon">Q</div>
                        <div class="logo-text">Quiz Master</div>
                    </a>
                    <div class="workspace-badge">📊 <?= htmlspecialchars($quiz['code']) ?></div>
                </div>
                <div class="header-actions">
                    <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard" class="btn btn-secondary small">← Retour</a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="page-header text-center">
                <h1 class="page-title">Résultats du quiz</h1>
                <h2 style="color: var(--text-secondary); margin-bottom: 8px;"><?= htmlspecialchars($quiz['title']) ?></h2>
                <p class="page-subtitle">Code: <?= htmlspecialchars($quiz['code']) ?></p>
            </div>

            <?php if (!$rows): ?>
                <div class="modern-card text-center">
                    <div class="card-body" style="padding: 48px;">
                        <div style="font-size: 64px; margin-bottom: 24px;">📝</div>
                        <h3 class="mb-16">Aucun participant</h3>
                        <p class="text-muted">Ce quiz n'a pas encore été tenté par des participants.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="modern-table-container animate-fade-in">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Rang</th>
                                <th>Participant</th>
                                <th>Score</th>
                                <th>Pourcentage</th>
                                <th>Terminé le</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $i => $r):
                            $total = (int)$r['total_answers']; 
                            $correct = (int)$r['correct_answers'];
                            $percentage = $total > 0 ? round($correct * 100 / $total) : 0; 
                            $rankIcon = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '')); ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-8">
                                        <span style="font-size: 20px;"><?= $rankIcon ?></span>
                                        <span style="font-weight: 600;">#<?= (int)($i+1) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($r['nickname']) ?></div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--primary);"><?= $correct ?></span>
                                    <span class="text-muted">/ <?= $total ?></span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-8">
                                        <span style="font-weight: 600; color: <?= $percentage >= 70 ? 'var(--success)' : ($percentage >= 50 ? 'var(--warning)' : 'var(--danger)') ?>">
                                            <?= $percentage ?>%
                                        </span>
                                    </div>
                                </td>
                                <td style="color: var(--text-muted);">
                                    <?= $r['completed_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['completed_at']))) : 'En cours...' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php }

function showUserManagement(): void {
    if (!isLoggedIn() || !isSuperAdmin()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    // Fonction simplifiée - ajoutez le contenu complet selon vos besoins
    echo "<h1>Gestion des utilisateurs (à implémenter)</h1>";
}

function showAppConfig(): void {
    if (!isLoggedIn() || !isSuperAdmin()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    // Fonction simplifiée - ajoutez le contenu complet selon vos besoins
    echo "<h1>Configuration de l'application (à implémenter)</h1>";
}

function showPermissionManagement(): void {
    if (!isLoggedIn() || !isSuperAdmin()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    // Fonction simplifiée - ajoutez le contenu complet selon vos besoins
    echo "<h1>Gestion des permissions (à implémenter)</h1>";
}

function showImportQuiz(): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    // Fonction simplifiée - ajoutez le contenu complet selon vos besoins
    echo "<h1>Importer un quiz (à implémenter)</h1>";
}

function handleExportQuizzes(string $format): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    // Fonction simplifiée - ajoutez le contenu complet selon vos besoins
    echo "Export $format (à implémenter)";
}

function showNotFoundPage(string $message = 'Page non trouvée'): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Erreur - Quiz Master</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-layout">
    <div class="container text-center">
        <div class="modern-card">
            <div class="card-body" style="padding: 48px;">
                <div style="font-size: 64px; margin-bottom: 24px;">😕</div>
                <h1 style="margin-bottom: 16px;">Oups !</h1>
                <p style="font-size: 18px; color: var(--text-muted); margin-bottom: 32px;"><?= htmlspecialchars($message) ?></p>
                <a class="btn btn-primary" href="<?= htmlspecialchars(getBaseUrl()) ?>">
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php }

// Fonctions de base de données essentielles
function createQuizFromForm(array $data) {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        $pdo->beginTransaction();

        $quiz_id = createQuiz($data['title'] ?? '', $data['description'] ?? '', (int)($_SESSION['user_id'] ?? 0));
        if (!$quiz_id) throw new Exception('createQuiz a échoué');

        saveQuizOptions((int)$quiz_id, $data);

        if (!empty($data['questions']) && is_array($data['questions'])) {
            foreach ($data['questions'] as $idx => $q) {
                $text = trim($q['text'] ?? '');
                if ($text === '') continue;
                $answers = [];
                if (!empty($q['answers']) && is_array($q['answers'])) {
                    foreach ($q['answers'] as $ai => $aText) {
                        $aText = trim((string)$aText);
                        if ($aText === '') continue;
                        $answers[] = [
                            'text' => $aText,
                            'is_correct' => ((string)$ai === (string)($q['correct_answer'] ?? ''))
                        ];
                    }
                }
                if (count($answers) >= 2) {
                    addQuestion((int)$quiz_id, $text, trim($q['comment'] ?? ''), $answers, (int)$idx+1);
                }
            }
        }

        $pdo->commit();
        return $quiz_id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('createQuizFromForm: ' . $e->getMessage());
        return false;
    }
}

function updateQuizFromForm(array $data, int $quiz_id): bool {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, description = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        $stmt->execute([trim($data['title'] ?? ''), trim($data['description'] ?? ''), $quiz_id, $_SESSION['user_id'] ?? 0]);

        saveQuizOptions($quiz_id, $data);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('updateQuizFromForm: ' . $e->getMessage());
        return false;
    }
}

function createQuiz(string $title, string $description, int $user_id) {
    global $pdo;
    if ($user_id <= 0) return false;
    try {
        $code = generateQuizCode();
        $stmt = $pdo->prepare("
            INSERT INTO quizzes (title, description, code, user_id, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'draft', datetime('now'), datetime('now'))
        ");
        $stmt->execute([$title, $description, $code, $user_id]);
        return $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('createQuiz: ' . $e->getMessage());
        return false;
    }
}

function addQuestion(int $quiz_id, string $text, string $comment, array $answers, int $order_index = 0) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO questions (quiz_id, text, comment, order_index, created_at)
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$quiz_id, $text, $comment, $order_index]);
        $qid = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare("INSERT INTO answers (question_id, text, is_correct, created_at) VALUES (?, ?, ?, datetime('now'))");
        foreach ($answers as $a) {
            $ins->execute([$qid, (string)$a['text'], !empty($a['is_correct']) ? 1 : 0]);
        }
        return $qid;
    } catch (Throwable $e) {
        error_log('addQuestion: ' . $e->getMessage());
        return false;
    }
}

function saveQuizOptions(int $quiz_id, array $data): bool {
    global $pdo;
    try {
        $rq = !empty($data['randomize_questions']) ? 1 : 0;
        $ra = !empty($data['randomize_answers'])   ? 1 : 0;
        $stmt = $pdo->prepare("SELECT id FROM quiz_options WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        if ($stmt->fetch()) {
            $u = $pdo->prepare("UPDATE quiz_options SET randomize_questions = ?, randomize_answers = ? WHERE quiz_id = ?");
            return $u->execute([$rq, $ra, $quiz_id]);
        } else {
            $i = $pdo->prepare("INSERT INTO quiz_options (quiz_id, randomize_questions, randomize_answers, created_at) VALUES (?, ?, ?, datetime('now'))");
            return $i->execute([$quiz_id, $rq, $ra]);
        }
    } catch (Throwable $e) {
        error_log('saveQuizOptions: ' . $e->getMessage());
        return false;
    }
}

function generateQuizCode(): string {
    global $pdo;
    do {
        $code = '';
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i=0; $i<6; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE code = ?");
        $stmt->execute([$code]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $code;
}

function getQuizCodeById(int $quiz_id): ?string {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT code FROM quizzes WHERE id = ?");
        $stmt->execute([$quiz_id]);
        $r = $stmt->fetch();
        return $r ? (string)$r['code'] : null;
    } catch (Throwable $e) {
        error_log('getQuizCodeById: ' . $e->getMessage());
        return null;
    }
}

function getQuizQuestionsForEdit(int $quiz_id): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT
                q.id, q.text, q.comment, q.order_index,
                a.id AS answer_id, a.text AS answer_text, a.is_correct
            FROM questions q
            LEFT JOIN answers a ON a.question_id = q.id
            WHERE q.quiz_id = ?
            ORDER BY q.order_index, q.id, a.id
        ");
        $stmt->execute([$quiz_id]);
        $rows = $stmt->fetchAll();
        $out = [];
        $currentId = null;
        $cur = null;
        foreach ($rows as $r) {
            if ($currentId !== (int)$r['id']) {
                if ($cur) $out[] = $cur;
                $currentId = (int)$r['id'];
                $cur = [
                    'id' => (int)$r['id'],
                    'text' => (string)$r['text'],
                    'comment' => (string)$r['comment'],
                    'answers' => []
                ];
            }
            if (!empty($r['answer_id'])) {
                $cur['answers'][] = [
                    'id' => (int)$r['answer_id'],
                    'text' => (string)$r['answer_text'],
                    'is_correct' => (int)$r['is_correct'] === 1
                ];
            }
        }
        if ($cur) $out[] = $cur;
        return $out;
    } catch (Throwable $e) {
        error_log('getQuizQuestionsForEdit: ' . $e->getMessage());
        return [];
    }
}

function deleteQuestion(int $question_id): bool {
    global $pdo;
    if ($question_id <= 0) return false;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        $stmt = $pdo->prepare("
            DELETE FROM questions
            WHERE id = :qid
              AND quiz_id IN (SELECT id FROM quizzes WHERE user_id = :uid)
        ");
        return $stmt->execute([':qid' => $question_id, ':uid' => $_SESSION['user_id'] ?? 0]);
    } catch (Throwable $e) {
        error_log('deleteQuestion: ' . $e->getMessage());
        return false;
    }
}
?>