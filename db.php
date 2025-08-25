<?php
/**
 * Connexion SQLite + création du schéma si nécessaire
 * Chemin: ./quiz_app.db (même dossier que les PHP)
 */

$db_file = __DIR__ . '/quiz_app.db';

try {
    $isNew = !file_exists($db_file);

    $pdo = new PDO('sqlite:' . $db_file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("PRAGMA foreign_keys = ON");

    if ($isNew) {
        createDatabase($pdo);
        insertSampleData($pdo);
    } else {
        // Vérifier tables essentielles
        if (!tableExists($pdo, 'users')) {
            createDatabase($pdo);
            insertSampleData($pdo);
        } else {
            if (!tableExists($pdo, 'quiz_options')) {
                $pdo->exec("
                    CREATE TABLE quiz_options (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        quiz_id INTEGER NOT NULL,
                        randomize_questions INTEGER NOT NULL DEFAULT 0,
                        randomize_answers   INTEGER NOT NULL DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
                        UNIQUE(quiz_id)
                    );
                    CREATE INDEX IF NOT EXISTS idx_quiz_options_quiz_id ON quiz_options(quiz_id);
                ");
            }
        }
    }
} catch (Throwable $e) {
    die('Erreur DB: ' . $e->getMessage());
}

function tableExists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

function createDatabase(PDO $pdo): void {
    $sql = "
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email    TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role     TEXT CHECK(role IN ('admin','superadmin')) NOT NULL DEFAULT 'admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX idx_users_email ON users(email);

    CREATE TABLE quizzes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        code TEXT NOT NULL UNIQUE,
        user_id INTEGER NOT NULL,
        status TEXT CHECK(status IN ('draft','active','closed')) NOT NULL DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    CREATE INDEX idx_quizzes_code ON quizzes(code);
    CREATE INDEX idx_quizzes_user_id ON quizzes(user_id);
    CREATE INDEX idx_quizzes_status ON quizzes(status);

    CREATE TABLE quiz_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        randomize_questions INTEGER NOT NULL DEFAULT 0,
        randomize_answers   INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        UNIQUE(quiz_id)
    );
    CREATE INDEX idx_quiz_options_quiz_id ON quiz_options(quiz_id);

    CREATE TABLE questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        text TEXT NOT NULL,
        comment TEXT,
        order_index INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    );
    CREATE INDEX idx_questions_quiz_id ON questions(quiz_id);
    CREATE INDEX idx_questions_order ON questions(quiz_id, order_index);

    CREATE TABLE answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question_id INTEGER NOT NULL,
        text TEXT NOT NULL,
        is_correct INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    );
    CREATE INDEX idx_answers_question_id ON answers(question_id);
    CREATE INDEX idx_answers_correct ON answers(question_id, is_correct);

    CREATE TABLE participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        nickname TEXT NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    );
    CREATE INDEX idx_participants_quiz_id ON participants(quiz_id);

    CREATE TABLE participant_progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        participant_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        chosen_answer_id INTEGER NOT NULL,
        answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
        FOREIGN KEY (chosen_answer_id) REFERENCES answers(id) ON DELETE CASCADE,
        UNIQUE(participant_id, question_id)
    );
    CREATE INDEX idx_progress_participant_id ON participant_progress(participant_id);
    ";
    $pdo->exec($sql);
}

function insertSampleData(PDO $pdo): void {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username,email,password,role) VALUES (?,?,?,?)")
        ->execute(['admin','admin@quiz-app.com',$hash,'superadmin']);

    // DEMO quiz
    $pdo->prepare("INSERT INTO quizzes (title,description,code,user_id,status) VALUES (?,?,?,?,?)")
        ->execute(['Quiz de démonstration',"Un quiz d'exemple",'DEMO01',1,'active']);
    $pdo->prepare("INSERT INTO quiz_options (quiz_id,randomize_questions,randomize_answers) VALUES (?,?,?)")
        ->execute([1,1,1]);

    // Questions
    $pdo->prepare("INSERT INTO questions (quiz_id,text,comment,order_index) VALUES (?,?,?,?)")
        ->execute([1,'Quelle est la capitale de la France ?','',1]);
    $pdo->prepare("INSERT INTO questions (quiz_id,text,comment,order_index) VALUES (?,?,?,?)")
        ->execute([1,'Combien font 2 + 2 ?','',2]);
    $pdo->prepare("INSERT INTO questions (quiz_id,text,comment,order_index) VALUES (?,?,?,?)")
        ->execute([1,'Langage utilisé côté serveur ici ?','',3]);

    // Réponses
    $ins = $pdo->prepare("INSERT INTO answers (question_id,text,is_correct) VALUES (?,?,?)");
    // Q1
    $ins->execute([1,'Paris',1]);
    $ins->execute([1,'Lyon',0]);
    $ins->execute([1,'Marseille',0]);
    $ins->execute([1,'Toulouse',0]);
    // Q2
    $ins->execute([2,'3',0]);
    $ins->execute([2,'4',1]);
    $ins->execute([2,'5',0]);
    $ins->execute([2,'22',0]);
    // Q3
    $ins->execute([3,'PHP',1]);
    $ins->execute([3,'Python',0]);
    $ins->execute([3,'Java',0]);
    $ins->execute([3,'Ruby',0]);
}