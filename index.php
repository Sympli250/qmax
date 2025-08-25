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

    case 'create-quiz':
        showCreateQuiz();
        break;

    case 'edit-quiz':
        $quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        showEditQuiz($quiz_id);
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
                echo json_encode(['success' => (bool)$ok]);
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

    <p class="center"><a class="btn-success" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=create-quiz">Créer un nouveau quiz</a> <a class="btn-secondary" href="<?= htmlspecialchars(getBaseUrl()) ?>?page=logout">Se déconnecter</a></p>

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
                            <button class="btn-danger small" onclick="deleteQuiz(<?= (int)$qz['id'] ?>)">Supprimer</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script>
function changeStatus(sel){
    const id = sel.dataset.quizId;
    const val = sel.value;
    fetch('<?= htmlspecialchars(getBaseUrl()) ?>', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=change_quiz_status&quiz_id='+encodeURIComponent(id)+'&new_status='+encodeURIComponent(val)
    }).then(r=>r.json()).then(j=>{
        if(!j.success){ alert('Erreur mise à jour statut'); location.reload(); }
    }).catch(()=>{ alert('Erreur réseau'); location.reload(); });
}
function deleteQuiz(id){
    if(!confirm('Supprimer définitivement ce quiz et toutes ses données (questions, réponses, résultats) ?')) return;
    fetch('<?= htmlspecialchars(getBaseUrl()) ?>', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=delete_quiz&quiz_id='+encodeURIComponent(id)
    }).then(r=>r.json()).then(j=>{
        if(j.success){ location.reload(); } else { alert('Suppression impossible'); }
    }).catch(()=>alert('Erreur réseau'));
}
</script>
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

        if (!empty($q['answers']) && is_array($q['answers']) && !empty($q['answer_ids']) && is_array($q['answer_ids'])) {
            foreach ($q['answers'] as $ai => $aText) {
                if (!isset($q['answer_ids'][$ai])) continue;
                $aid = (int)$q['answer_ids'][$ai];
                $is_correct = ((string)$ai === (string)($q['correct_answer'] ?? '')) ? 1 : 0;
                $u = $pdo->prepare("UPDATE answers SET text = ?, is_correct = ? WHERE id = ?");
                $u->execute([trim((string)$aText), $is_correct, $aid]);
            }
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