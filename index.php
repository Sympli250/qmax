<?php
/**
 * Quiz App - Version 0.3
 * Front Controller (toutes les pages et les APIs)
 *
 * Règles importantes:
 * - Aucun output avant les endpoints API/AJAX (sinon JSON cassé)
 * - Les pages HTML émettent leur propre DOCTYPE dans les fonctions show*
 * - Toutes les fonctionnalités demandées sont incluses (édition, suppression, statuts, randomisations, saisie du nom)
 */

define('APP_VERSION', '0.3');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* -------------------------------------------------------------------------- */
/* Utils                                                                       */
/* -------------------------------------------------------------------------- */
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
/* API en premier (aucun HTML avant)                                           */
/* -------------------------------------------------------------------------- */
if (isset($_GET['api'])) {
    handleApiRequest();
    exit;
}

/* -------------------------------------------------------------------------- */
/* AJAX (aucun HTML avant)                                                     */
/* -------------------------------------------------------------------------- */
if (!empty($_POST['ajax_action'])) {
    handleAjaxRequest();
    exit;
}

/* -------------------------------------------------------------------------- */
/* Routing                                                                     */
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
/* API                                                                        */
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

/* ========================================================================== */
/* AJAX                                                                       */
/* ========================================================================== */
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

/* ========================================================================== */
/* Opérations data pour AJAX                                                  */
/* ========================================================================== */
function deleteQuiz(int $quiz_id): bool {
    global $pdo;
    if ($quiz_id <= 0) return false;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        // Supprime seulement si le quiz appartient à l'utilisateur
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

function deleteQuestion(int $question_id): bool {
    global $pdo;
    if ($question_id <= 0) return false;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        // Supprime seulement si la question appartient à un quiz de l'utilisateur
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

/* ========================================================================== */
/* PAGES (HTML)                                                               */
/* ========================================================================== */
function showHomePage(): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiz App v<?= htmlspecialchars(APP_VERSION) ?> - Accueil</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Quiz App v<?= htmlspecialchars(APP_VERSION) ?></h1>
    <div class="home-content">
        <h2>Rejoindre un quiz</h2>
        <form class="quiz-join-form" onsubmit="joinQuiz(event)">
            <input type="text" id="quiz-code" placeholder="Entrez le code du quiz" required maxlength="10">
            <button type="submit">Rejoindre</button>
        </form>
        <div class="admin-link">
            <a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin">Espace Administrateur</a>
        </div>
        <div class="demo-section">
            <h3>Quiz de démonstration</h3>
            <p>Code: DEMO01</p>
            <a href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=DEMO01" class="btn-primary">Essayer le quiz démo</a>
        </div>
    </div>
</div>
<script>
function joinQuiz(e){
    e.preventDefault();
    const code = (document.getElementById('quiz-code').value||'').trim().toUpperCase();
    if (!code){ alert('Veuillez entrer un code.'); return; }
    window.location.href = '<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=' + encodeURIComponent(code);
}
</script>
</body>
</html>
<?php }

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
<title>Administration - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container narrow">
    <h1>Connexion Administrateur</h1>
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required value="admin@quiz-app.com">
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required value="admin123">
        </div>
        <button type="submit" class="btn-primary">Se connecter</button>
    </form>
    <p class="center mt-16"><a href="<?= htmlspecialchars(getBaseUrl()) ?>">Retour</a></p>
</div>
</body>
</html>
<?php }

function showAppConfig(): void {
    if (!isLoggedIn() || !isSuperAdmin()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $key = trim($_POST['key'] ?? '');
        $value = trim($_POST['value'] ?? '');
        if ($key !== '') {
            $msg = setConfig($key, $value) ? 'Configuration enregistrée' : 'Erreur';
        }
    }
    $configs = getAllConfig();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuration - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Configuration de l'application</h1>
    <p><a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard">Retour</a></p>
    <?php if ($msg): ?><div class="panel center"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <h2>Ajouter / Modifier</h2>
    <form method="post" class="form">
        <div class="form-group"><label>Clé</label><input type="text" name="key" required></div>
        <div class="form-group"><label>Valeur</label><input type="text" name="value"></div>
        <button type="submit" class="btn-success">Enregistrer</button>
    </form>
    <h2>Configuration existante</h2>
    <div class="table-wrap">
        <table class="users-table">
            <thead><tr><th>Clé</th><th>Valeur</th></tr></thead>
            <tbody>
            <?php foreach ($configs as $k => $v): ?>
                <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php }

function showPermissionManagement(): void {
    if (!isLoggedIn() || !isSuperAdmin()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_permission') {
            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                $msg = createPermission($name) ? 'Permission créée' : 'Erreur création';
            }
        } elseif ($action === 'assign_permission') {
            $uid = (int)($_POST['user_id'] ?? 0);
            $pid = (int)($_POST['permission_id'] ?? 0);
            if ($uid > 0 && $pid > 0) {
                $msg = assignPermissionToUser($uid, $pid) ? 'Permission assignée' : 'Erreur assignation';
            }
        } elseif ($action === 'revoke_permission') {
            $uid = (int)($_POST['user_id'] ?? 0);
            $pid = (int)($_POST['permission_id'] ?? 0);
            if ($uid > 0 && $pid > 0) {
                $msg = revokePermissionFromUser($uid, $pid) ? 'Permission retirée' : 'Erreur retrait';
            }
        }
    }
    $users = getAllUsers();
    $permissions = getAllPermissions();
    $userPerms = [];
    foreach ($users as $u) {
        $userPerms[$u['id']] = getUserPermissions((int)$u['id']);
    }
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Permissions - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Gestion des permissions</h1>
    <p><a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard">Retour</a></p>
    <?php if ($msg): ?><div class="panel center"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <h2>Créer une permission</h2>
    <form method="post" class="form">
        <input type="hidden" name="action" value="create_permission">
        <div class="form-group"><label>Nom</label><input type="text" name="name" required></div>
        <button type="submit" class="btn-success">Créer</button>
    </form>
    <h2>Permissions par utilisateur</h2>
    <?php foreach ($users as $u): ?>
        <div class="panel">
            <h3><?= htmlspecialchars($u['username']) ?></h3>
            <ul>
            <?php foreach ($userPerms[$u['id']] as $p): ?>
                <li><?= htmlspecialchars($p['name']) ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="revoke_permission">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="permission_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn-danger">Retirer</button>
                    </form>
                </li>
            <?php endforeach; ?>
            </ul>
            <form method="post" class="form-inline">
                <input type="hidden" name="action" value="assign_permission">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <select name="permission_id">
                    <?php foreach ($permissions as $perm): ?>
                        <option value="<?= (int)$perm['id'] ?>"><?= htmlspecialchars($perm['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary">Ajouter</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
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
<title>Dashboard - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Tableau de bord</h1>

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
            <div class="stat-number"><?= htmlspecialchars(APP_VERSION) ?></div>
            <div class="stat-label">Version</div>
        </div>
    </div>

    <p class="center">
        <a class="btn-success" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=create-quiz">Créer un nouveau quiz</a>
        <?php if (isSuperAdmin()): ?>
            <a class="btn-info" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-users">Gestion utilisateurs</a>
            <a class="btn-warning" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-config">Configuration</a>
            <a class="btn-info" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-permissions">Permissions</a>
        <?php endif; ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=logout">Se déconnecter</a>
    </p>

    <h2>Import / Export</h2>
    <div class="panel center">
        <p>
            <a class="btn-info" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=import-quiz">Importer un quiz</a>
        </p>
        <div class="export-bar">
            <select id="export-format">
                <option value="json">JSON</option>
                <option value="csv">CSV</option>
                <option value="excel">Excel</option>
            </select>
            <button class="btn-secondary small" onclick="exportQuizzes(false)">Exporter sélection</button>
            <button class="btn-secondary small" onclick="exportQuizzes(true)">Exporter tout</button>
        </div>
        <p>Templates :
            <a href="templates/quiz-template.json" download>JSON</a> |
            <a href="templates/quiz-template.csv" download>CSV</a> |
            <a href="templates/quiz-template.xls" download>Excel</a>
        </p>
    </div>

    <h2>Mes quiz</h2>
    <?php if (!$quizzes): ?>
        <div class="panel center">
            <p>Aucun quiz pour le moment.</p>
            <a class="btn-success" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=create-quiz">Créer mon premier quiz</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="quizzes-table">
                <thead>
                    <tr>
                        <th class="select-col"><input type="checkbox" onclick="toggleSelectAll(this)"></th>
                        <th>Titre</th>
                        <th>Code</th>
                        <th>Statut</th>
                        <th>Options</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $qz): $opts = getQuizOptions((int)$qz['id']); ?>
                    <tr>
                        <td class="select-col"><input type="checkbox" class="quiz-select" value="<?= (int)$qz['id'] ?>"></td>
                        <td><?= htmlspecialchars($qz['title']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($qz['code']) ?></span></td>
                        <td>
                            <select class="status-select" data-quiz-id="<?= (int)$qz['id'] ?>" onchange="changeStatus(this)">
                                <option value="draft"  <?= $qz['status']==='draft'?'selected':'' ?>>Brouillon</option>
                                <option value="active" <?= $qz['status']==='active'?'selected':'' ?>>Actif</option>
                                <option value="closed" <?= $qz['status']==='closed'?'selected':'' ?>>Fermé</option>
                            </select>
                        </td>
                        <td>
                            <?php
                                $o = [];
                                if ($opts['randomize_questions']) $o[] = 'Questions mélangées';
                                if ($opts['randomize_answers'])   $o[] = 'Réponses mélangées';
                                echo htmlspecialchars(implode(' / ', $o));
                            ?>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($qz['created_at']))) ?></td>
                        <td class="actions">
                            <a class="btn-primary small" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=<?= urlencode($qz['code']) ?>">Voir</a>
                            <a class="btn-warning small" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=edit-quiz&id=<?= (int)$qz['id'] ?>">Modifier</a>
                            <a class="btn-info small" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz-results&code=<?= urlencode($qz['code']) ?>">Résultats</a>
                            <button class="btn-danger small" onclick="deleteQuiz(<?= (int)$qz['id'] ?>, this)">Supprimer</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script>
const baseUrl = '<?= htmlspecialchars(getBaseUrl()) ?>';
function changeStatus(sel){
    const id = sel.dataset.quizId;
    const val = sel.value;
    fetch(baseUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=change_quiz_status&quiz_id='+encodeURIComponent(id)+'&new_status='+encodeURIComponent(val),
        credentials:'same-origin'
    }).then(r=>r.json()).then(j=>{
        if(!j.success){ alert('Erreur mise à jour statut'); location.reload(); }
    }).catch(()=>{ alert('Erreur réseau'); location.reload(); });
}
function deleteQuiz(id, btn){
    if(!confirm('Supprimer définitivement ce quiz et toutes ses données (questions, réponses, résultats) ?')) return;
    btn.disabled = true;
    fetch(baseUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body: 'ajax_action=delete_quiz&quiz_id='+encodeURIComponent(id)
    }).then(r=>r.json()).then(j=>{
        if(j.success){ location.reload(); } else { alert('Suppression impossible'); btn.disabled=false; }
    }).catch(()=>{ alert('Erreur réseau'); btn.disabled=false; });
}
function exportQuizzes(all){
    const format = document.getElementById('export-format').value;
    let url = baseUrl+'?page=export-quizzes&format='+encodeURIComponent(format);
    if(!all){
        const ids = Array.from(document.querySelectorAll('.quiz-select:checked')).map(cb=>cb.value);
        if(ids.length===0){ alert('Sélectionnez au moins un quiz.'); return; }
        url += '&ids='+ids.join(',');
    }
    window.location.href = url;
}
function toggleSelectAll(cb){
    document.querySelectorAll('.quiz-select').forEach(c=>{ c.checked = cb.checked; });
}
</script>
</body>
</html>
<?php }

function showUserManagement(): void {
    if (!isLoggedIn() || !isSuperAdmin()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'admin';
            if ($username !== '' && $email !== '' && $password !== '') {
                $msg = createUser($username, $email, $password, $role) ? 'Utilisateur créé' : 'Erreur création';
            } else {
                $msg = 'Champs requis manquants';
            }
        } elseif ($action === 'update_role') {
            $uid = (int)($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? 'admin';
            if ($uid > 0) {
                $msg = updateUserRole($uid, $role) ? 'Rôle mis à jour' : 'Erreur mise à jour';
            }
        }
    }
    $users = getAllUsers();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion utilisateurs - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Gestion des utilisateurs</h1>
    <p><a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard">Retour</a></p>
    <?php if ($msg): ?><div class="panel center"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <h2>Ajouter un utilisateur</h2>
    <form method="post" class="form">
        <input type="hidden" name="action" value="create">
        <div class="form-group"><label>Nom d'utilisateur</label><input type="text" name="username" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Mot de passe</label><input type="password" name="password" required></div>
        <div class="form-group"><label>Rôle</label>
            <select name="role">
                <option value="admin">Admin</option>
                <option value="superadmin">Super Admin</option>
            </select>
        </div>
        <button type="submit" class="btn-success">Créer</button>
    </form>
    <h2>Utilisateurs existants</h2>
    <div class="table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Nom</th><th>Email</th><th>Rôle</th><th>Créé le</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <select name="role" onchange="this.form.submit()">
                                <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                                <option value="superadmin" <?= $u['role']==='superadmin'?'selected':'' ?>>Super Admin</option>
                            </select>
                        </form>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($u['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
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
            $success = 'Quiz créé. Code: ' . $code;
        } else {
            $error = 'Echec de création';
        }
    } ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer un quiz - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Création de quiz</h1>
    <p class="center"><a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard">Retour</a></p>
    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" id="quiz-form" class="form">
        <input type="hidden" name="action" value="create_quiz">
        <div class="panel">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" name="title" required maxlength="200" placeholder="Ex: Culture générale">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Description du quiz"></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="randomize_questions" value="1"> Mélanger les questions</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="randomize_answers" value="1"> Mélanger les réponses</label>
            </div>
        </div>

        <h2>Questions <span class="question-count">(0)</span></h2>
        <div id="questions-container"></div>
        <p><button type="button" class="btn-primary" onclick="addQuestion()">Ajouter une question</button></p>

        <p class="center"><button type="submit" class="btn-success">Créer le quiz</button></p>
    </form>
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
    if (!$quiz) { showNotFoundPage('Quiz introuvable'); return; }

    $success = ''; $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_quiz') {
        if (updateQuizFromForm($_POST, $quiz_id)) {
            $success = 'Quiz mis à jour';
            $stmt->execute([$quiz_id, $_SESSION['user_id'] ?? 0]);
            $quiz = $stmt->fetch();
        } else {
            $error = 'Echec de mise à jour';
        }
    }
    $questions = getQuizQuestionsForEdit($quiz_id);
    $options = getQuizOptions($quiz_id); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier le quiz - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Modifier le quiz</h1>
    <p class="center">
        <a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard">Retour</a>
        <a class="btn-primary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz&code=<?= urlencode($quiz['code']) ?>">Voir le quiz</a>
        <a class="btn-info" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=quiz-results&code=<?= urlencode($quiz['code']) ?>">Résultats</a>
    </p>
    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="panel">
        <p><strong>Code:</strong> <span class="badge"><?= htmlspecialchars($quiz['code']) ?></span></p>
        <p><strong>Statut:</strong> <?= htmlspecialchars($quiz['status']) ?></p>
        <p><strong>Créé le:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($quiz['created_at']))) ?></p>
    </div>

    <form method="post" id="quiz-edit-form" class="form">
        <input type="hidden" name="action" value="update_quiz">
        <div class="panel">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" name="title" required maxlength="200" value="<?= htmlspecialchars($quiz['title']) ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($quiz['description']) ?></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="randomize_questions" value="1" <?= $options['randomize_questions']?'checked':''; ?>> Mélanger les questions</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="randomize_answers" value="1" <?= $options['randomize_answers']?'checked':''; ?>> Mélanger les réponses</label>
            </div>
        </div>

        <h2>Questions <span class="question-count">(<?= count($questions) ?>)</span></h2>
        <div id="questions-container">
            <?php foreach ($questions as $i => $q): ?>
                <div class="question-item" data-question-index="<?= (int)$i ?>" data-question-id="<?= (int)$q['id'] ?>">
                    <div class="question-header">
                        <h3>Question <?= (int)($i+1) ?></h3>
                        <button type="button" class="btn-danger outline small" onclick="deleteExistingQuestion(<?= (int)$q['id'] ?>, this)">Supprimer</button>
                    </div>
                    <input type="hidden" name="questions[<?= (int)$i ?>][id]" value="<?= (int)$q['id'] ?>">
                    <div class="form-group">
                        <label>Texte *</label>
                        <textarea name="questions[<?= (int)$i ?>][text]" required><?= htmlspecialchars($q['text']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Commentaire</label>
                        <input type="text" name="questions[<?= (int)$i ?>][comment]" value="<?= htmlspecialchars($q['comment']) ?>">
                    </div>
                    <div class="answers-section">
                        <label>Réponses *</label>
                        <div class="answers-container">
                            <?php foreach ($q['answers'] as $ai => $ans): ?>
                                <div class="answer-item">
                                    <input type="radio" name="questions[<?= (int)$i ?>][correct_answer]" value="<?= (int)$ai ?>" <?= $ans['is_correct']?'checked':''; ?> required>
                                    <input type="text" name="questions[<?= (int)$i ?>][answers][<?= (int)$ai ?>]" value="<?= htmlspecialchars($ans['text']) ?>" required>
                                    <input type="hidden" name="questions[<?= (int)$i ?>][answer_ids][<?= (int)$ai ?>]" value="<?= (int)$ans['id'] ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p><button type="button" class="btn-primary" onclick="addQuestion()">Ajouter une question</button></p>
        <p class="center"><button type="submit" class="btn-success">Sauvegarder</button></p>
    </form>
</div>
<script src="quiz-admin.js"></script>
</body>
</html>
<?php }

function showImportQuiz(): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    $success = '';
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['quiz_file'])) {
        $format = $_POST['format'] ?? 'json';
        $tmp = $_FILES['quiz_file']['tmp_name'] ?? '';
        $data = null;
        if ($format === 'json') {
            $data = json_decode(@file_get_contents($tmp), true);
        } elseif ($format === 'csv') {
            if (($h = @fopen($tmp, 'r')) !== false) {
                fgetcsv($h);
                $title = '';$desc='';$questions=[];
                while (($r = fgetcsv($h)) !== false) {
                    if ($title==='') { $title=$r[0]??''; $desc=$r[1]??''; }
                    $questions[] = [
                        'text' => $r[2] ?? '',
                        'correct_answer' => (int)($r[3] ?? 0),
                        'answers' => array_slice($r,4,4),
                        'comment' => $r[8] ?? ''
                    ];
                }
                fclose($h);
                $data = ['title'=>$title,'description'=>$desc,'questions'=>$questions];
            }
        } elseif ($format === 'excel') {
            $xml = @simplexml_load_file($tmp);
            if ($xml) {
                $rows=[];
                foreach ($xml->Worksheet->Table->Row as $row) {
                    $cells=[];
                    foreach ($row->Cell as $c) { $cells[] = (string)$c->Data; }
                    $rows[]=$cells;
                }
                array_shift($rows);
                $title='';$desc='';$questions=[];
                foreach ($rows as $r) {
                    if ($title==='') { $title=$r[0]??''; $desc=$r[1]??''; }
                    $questions[] = [
                        'text' => $r[2] ?? '',
                        'correct_answer' => (int)($r[3] ?? 0),
                        'answers' => array_slice($r,4,4),
                        'comment' => $r[8] ?? ''
                    ];
                }
                $data = ['title'=>$title,'description'=>$desc,'questions'=>$questions];
            }
        }
        if (is_array($data) && !empty($data['title'])) {
            $id = createQuizFromForm($data);
            if ($id) {
                $success = 'Quiz importé avec succès.';
            } else {
                $error = 'Import échoué.';
            }
        } else {
            $error = 'Fichier invalide.';
        }
    } ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Importer un quiz - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Importer un quiz</h1>
    <p class="center"><a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard">Retour</a></p>
    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form">
        <div class="form-group">
            <label>Format</label>
            <select name="format" required>
                <option value="json">JSON</option>
                <option value="csv">CSV</option>
                <option value="excel">Excel (XLS)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Fichier</label>
            <input type="file" name="quiz_file" required>
        </div>
        <button type="submit" class="btn-primary">Importer</button>
    </form>
    <p class="mt-16">Modèles :
        <a href="templates/quiz-template.json" download>JSON</a> |
        <a href="templates/quiz-template.csv" download>CSV</a> |
        <a href="templates/quiz-template.xls" download>Excel</a>
    </p>
</div>
</body>
</html>
<?php }

function handleExportQuizzes(string $format): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '?page=admin');
        exit;
    }
    global $pdo;
    $userId = $_SESSION['user_id'] ?? 0;
    $idsParam = $_GET['ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $idsParam)));
    if ($ids) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE user_id = ? AND id IN ($in)");
        $stmt->execute(array_merge([$userId], $ids));
    } else {
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = [];
    foreach ($quizzes as $q) {
        $questions = getQuizQuestionsForEdit((int)$q['id']);
        $qs = [];
        foreach ($questions as $qu) {
            $answers = array_map(fn($a) => $a['text'], $qu['answers']);
            $correct = 0;
            foreach ($qu['answers'] as $idx => $ans) {
                if ($ans['is_correct']) { $correct = $idx; break; }
            }
            $qs[] = [
                'text' => $qu['text'],
                'comment' => $qu['comment'],
                'answers' => $answers,
                'correct_answer' => $correct
            ];
        }
        $data[] = [
            'title' => $q['title'],
            'description' => $q['description'],
            'questions' => $qs
        ];
    }
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="quizzes.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['title','description','question','correct_answer','answer1','answer2','answer3','answer4','comment']);
        foreach ($data as $quiz) {
            foreach ($quiz['questions'] as $q) {
                $row = [$quiz['title'],$quiz['description'],$q['text'],$q['correct_answer']];
                for ($i=0;$i<4;$i++) $row[] = $q['answers'][$i] ?? '';
                $row[] = $q['comment'];
                fputcsv($out, $row);
            }
        }
        fclose($out);
    } elseif ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="quizzes.xls"');
        echo '<?xml version="1.0"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Quiz"><Table>';
        echo '<Row>';
        foreach (["title","description","question","correct_answer","answer1","answer2","answer3","answer4","comment"] as $h) {
            echo '<Cell><Data ss:Type="String">'.htmlspecialchars($h).'</Data></Cell>';
        }
        echo '</Row>';
        foreach ($data as $quiz) {
            foreach ($quiz['questions'] as $q) {
                echo '<Row>';
                $row = [$quiz['title'],$quiz['description'],$q['text'],$q['correct_answer']];
                for ($i=0;$i<4;$i++) $row[] = $q['answers'][$i] ?? '';
                $row[] = $q['comment'];
                foreach ($row as $cell) {
                    $type = is_numeric($cell) ? 'Number' : 'String';
                    echo '<Cell><Data ss:Type="'.$type.'">'.htmlspecialchars((string)$cell).'</Data></Cell>';
                }
                echo '</Row>';
            }
        }
        echo '</Table></Worksheet></Workbook>';
    } else {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="quizzes.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function showQuizPage(string $quiz_code): void {
    $quiz = getQuizByCode($quiz_code);
    if (!$quiz) { showNotFoundPage("Code de quiz invalide ou inactif."); return; } ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($quiz['title']) ?> - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div id="quiz-container">
        <div class="quiz-intro panel center">
            <h1><?= htmlspecialchars($quiz['title']) ?></h1>
            <p><?= htmlspecialchars($quiz['description']) ?></p>
            <div class="participant-form">
                <h3>Avant de commencer</h3>
                <div class="form-group">
                    <label>Votre nom/pseudo</label>
                    <input type="text" id="participant-name" maxlength="50" required>
                </div>
                <button class="btn-primary" onclick="startQuizWithName('<?= htmlspecialchars($quiz['code']) ?>')">Commencer le quiz</button>
            </div>
        </div>
    </div>
</div>
<script>
window.baseUrl = '<?= htmlspecialchars(getBaseUrl()) ?>';
function startQuizWithName(code){
    const name = (document.getElementById('participant-name').value||'').trim();
    if(!name){ alert('Veuillez entrer votre nom.'); return; }
    if (typeof QuizEngine !== 'undefined'){
        window.quizEngine = new QuizEngine(code, name);
    } else {
        const s = document.createElement('script');
        s.src = 'quiz_engine.js';
        s.onload = ()=>{ window.quizEngine = new QuizEngine(code, name); };
        s.onerror = ()=>alert('Erreur chargement moteur de quiz');
        document.head.appendChild(s);
    }
}
</script>
<script src="quiz_engine.js"></script>
</body>
</html>
<?php }

function showQuizResults(string $code): void {
    $quiz = getQuizByCode($code);
    if (!$quiz) { showNotFoundPage('Quiz introuvable'); return; }
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
<div class="container">
    <h1>Résultats</h1>
    <h2><?= htmlspecialchars($quiz['title']) ?> (<?= htmlspecialchars($quiz['code']) ?>)</h2>
    <?php if (!$rows): ?>
        <div class="panel center">Aucun participant.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="results-table">
                <thead><tr><th>Rang</th><th>Participant</th><th>Score</th><th>Pourcentage</th><th>Terminé le</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r):
                    $total = (int)$r['total_answers']; $ok = (int)$r['correct_answers'];
                    $pct = $total>0 ? round($ok*100/$total) : 0; ?>
                    <tr>
                        <td><?= (int)($i+1) ?></td>
                        <td><?= htmlspecialchars($r['nickname']) ?></td>
                        <td><?= $ok ?>/<?= $total ?></td>
                        <td><?= $pct ?>%</td>
                        <td><?= $r['completed_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['completed_at']))) : 'En cours' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php
        $stats = getQuizQuestionStats((int)$quiz['id']);
        if ($stats):
    ?>
        <h3>Détails par question</h3>
        <div class="table-wrap">
            <table class="results-table">
                <thead><tr><th>Question</th><th>Réponses correctes</th><th>Total réponses</th><th>Pourcentage</th></tr></thead>
                <tbody>
                <?php foreach ($stats as $s):
                    $total = (int)$s['total_answers']; $ok = (int)$s['correct_answers'];
                    $pct = $total>0 ? round($ok*100/$total) : 0; ?>
                    <tr>
                        <td><?= htmlspecialchars($s['text']) ?></td>
                        <td><?= $ok ?></td>
                        <td><?= $total ?></td>
                        <td><?= $pct ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="center mt-16"><a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=admin-dashboard">Retour</a></p>
</div>
</body>
</html>
<?php }

function showNotFoundPage(string $message = 'Page non trouvée'): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Erreur - Quiz App v<?= htmlspecialchars(APP_VERSION) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container narrow center">
    <h1>Erreur</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <p class="mt-16"><a class="btn-primary" href="<?= htmlspecialchars(getBaseUrl()) ?>">Retour à l'accueil</a></p>
</div>
</body>
</html>
<?php }

/* ========================================================================== */
/* LOGIQUE / DB                                                               */
/* ========================================================================== */
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
        // Vérifier que la question appartient au même quiz que le participant
        // et que la réponse est liée à cette question
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
            // Marquer terminé si toutes les questions répondues
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

function getQuizQuestionStats(int $quiz_id): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT
                q.id,
                q.text,
                COUNT(pp.id) AS total_answers,
                SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS correct_answers
            FROM questions q
            LEFT JOIN participant_progress pp ON pp.question_id = q.id
            LEFT JOIN answers a ON a.id = pp.chosen_answer_id
            WHERE q.quiz_id = ?
            GROUP BY q.id
            ORDER BY q.order_index
        ");
        $stmt->execute([$quiz_id]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getQuizQuestionStats: ' . $e->getMessage());
        return [];
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

function updateQuizFromForm(array $data, int $quiz_id): bool {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, description = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        $stmt->execute([trim($data['title'] ?? ''), trim($data['description'] ?? ''), $quiz_id, $_SESSION['user_id'] ?? 0]);

        saveQuizOptions($quiz_id, $data);

        if (!empty($data['questions']) && is_array($data['questions'])) {
            foreach ($data['questions'] as $idx => $q) {
                $text = trim($q['text'] ?? '');
                if ($text === '') continue;

                if (!empty($q['id'])) {
                    updateQuestion((int)$q['id'], $q);
                } else {
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
                    if (count($answers) >= 2) addQuestion($quiz_id, $text, trim($q['comment'] ?? ''), $answers, (int)$idx+1);
                }
            }
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('updateQuizFromForm: ' . $e->getMessage());
        return false;
    }
}

function updateQuestion(int $question_id, array $q): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE questions SET text = ?, comment = ? WHERE id = ?");
        $stmt->execute([trim($q['text'] ?? ''), trim($q['comment'] ?? ''), $question_id]);

        $processedIds = [];
        if (!empty($q['answers']) && is_array($q['answers'])) {
            foreach ($q['answers'] as $ai => $aText) {
                $is_correct = ((string)$ai === (string)($q['correct_answer'] ?? '')) ? 1 : 0;
                if (!empty($q['answer_ids'][$ai])) {
                    $aid = (int)$q['answer_ids'][$ai];
                    $u = $pdo->prepare("UPDATE answers SET text = ?, is_correct = ? WHERE id = ?");
                    $u->execute([trim((string)$aText), $is_correct, $aid]);
                    $processedIds[] = $aid;
                } else {
                    $ins = $pdo->prepare("INSERT INTO answers (question_id, text, is_correct) VALUES (?, ?, ?)");
                    $ins->execute([$question_id, trim((string)$aText), $is_correct]);
                    $processedIds[] = (int)$pdo->lastInsertId();
                }
            }
        }

        // supprimer les réponses retirées
        $existing = $pdo->prepare("SELECT id FROM answers WHERE question_id = ?");
        $existing->execute([$question_id]);
        $existingIds = array_map('intval', $existing->fetchAll(PDO::FETCH_COLUMN, 0));
        $toDelete = array_diff($existingIds, $processedIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $del = $pdo->prepare("DELETE FROM answers WHERE id IN ($placeholders)");
            $del->execute($toDelete);
        }

        return true;
    } catch (Throwable $e) {
        error_log('updateQuestion: ' . $e->getMessage());
        return false;
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

/* Fin du fichier */