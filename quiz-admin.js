/**
 * Quiz App v0.3 - JS Admin (création / édition)
 * - Ajout/suppression questions
 * - Ajout/suppression réponses
 * - Suppression via AJAX des questions existantes
 */

let questionCount = 0;

// Création: ajouter une question vide (4 réponses par défaut)
function addQuestion() {
    const container = document.getElementById('questions-container');
    const qIndex = questionCount;
    questionCount++;

    const div = document.createElement('div');
    div.className = 'question-item';
    div.dataset.questionIndex = qIndex;
    div.innerHTML = `
        <div class="question-header">
            <h3>Question ${qIndex+1}</h3>
            <button type="button" class="btn-danger outline small" onclick="removeQuestion(this)">Supprimer</button>
        </div>
        <div class="form-group">
            <label>Texte *</label>
            <textarea name="questions[${qIndex}][text]" required placeholder="Saisissez la question"></textarea>
        </div>
        <div class="form-group">
            <label>Commentaire</label>
            <input type="text" name="questions[${qIndex}][comment]" placeholder="Optionnel">
        </div>
        <div class="answers-section">
            <label>Réponses *</label>
            <div class="answers-container">
                ${generateAnswerInputs(qIndex, 4)}
            </div>
            <button type="button" class="btn-secondary small" onclick="addAnswer(this)">Ajouter une réponse</button>
        </div>
    `;
    container.appendChild(div);
    updateQuestionCount();
}

function generateAnswerInputs(qIndex, count) {
    let html = '';
    for (let i=0; i<count; i++) {
        html += `
        <div class="answer-item">
            <input type="radio" name="questions[${qIndex}][correct_answer]" value="${i}" required title="Bonne réponse">
            <input type="text" name="questions[${qIndex}][answers][${i}]" placeholder="Réponse ${String.fromCharCode(65+i)}" required>
            <button type="button" class="btn-danger outline small" onclick="removeAnswer(this)">X</button>
        </div>`;
    }
    return html;
}

function addAnswer(btn) {
    const cont = btn.previousElementSibling;
    const qItem = btn.closest('.question-item');
    const qIndex = qItem.dataset.questionIndex;
    const i = cont.children.length;
    if (i >= 8) { alert('Maximum 8 réponses.'); return; }
    const div = document.createElement('div');
    div.className = 'answer-item';
    div.innerHTML = `
        <input type="radio" name="questions[${qIndex}][correct_answer]" value="${i}" required title="Bonne réponse">
        <input type="text" name="questions[${qIndex}][answers][${i}]" placeholder="Réponse ${String.fromCharCode(65+i)}" required>
        <button type="button" class="btn-danger outline small" onclick="removeAnswer(this)">X</button>
    `;
    cont.appendChild(div);
}

function removeAnswer(btn) {
    const cont = btn.closest('.answers-container');
    if (cont.children.length <= 2) { alert('Au moins 2 réponses requises.'); return; }
    btn.closest('.answer-item').remove();
    // réindexation des noms
    const qIndex = btn.closest('.question-item').dataset.questionIndex;
    Array.from(cont.children).forEach((node, idx) => {
        node.querySelector('input[type="radio"]').value = idx;
        node.querySelector('input[type="radio"]').name = `questions[${qIndex}][correct_answer]`;
        node.querySelector('input[type="text"]').name = `questions[${qIndex}][answers][${idx}]`;
        node.querySelector('input[type="text"]').placeholder = `Réponse ${String.fromCharCode(65+idx)}`;
        const hid = node.querySelector('input[type="hidden"]');
        if (hid) hid.name = `questions[${qIndex}][answer_ids][${idx}]`;
    });
}

function removeQuestion(btn) {
    btn.closest('.question-item').remove();
    questionCount--;
    renumberQuestions();
    updateQuestionCount();
}

function renumberQuestions() {
    const items = document.querySelectorAll('.question-item');
    items.forEach((it, idx) => {
        it.dataset.questionIndex = idx;
        const h3 = it.querySelector('h3');
        if (h3) h3.textContent = 'Question ' + (idx+1);
        const inputs = it.querySelectorAll('input, textarea');
        inputs.forEach(inp => {
            if (inp.name) {
                inp.name = inp.name.replace(/questions\[\d+\]/, `questions[${idx}]`);
            }
        });
        const ansInputs = it.querySelectorAll('.answers-container .answer-item');
        ansInputs.forEach((row, ai) => {
            const rd = row.querySelector('input[type="radio"]');
            const tx = row.querySelector('input[type="text"]');
            rd.value = ai;
            rd.name = `questions[${idx}][correct_answer]`;
            tx.name = `questions[${idx}][answers][${ai}]`;
            tx.placeholder = `Réponse ${String.fromCharCode(65+ai)}`;
            const hid = row.querySelector('input[type="hidden"]');
            if (hid) hid.name = `questions[${idx}][answer_ids][${ai}]`;
        });
    });
}

function updateQuestionCount() {
    const c = document.querySelector('.question-count');
    if (c) c.textContent = `(${document.querySelectorAll('.question-item').length})`;
}

// Edition : suppression AJAX d'une question existante
function deleteExistingQuestion(questionId, btn) {
    if (!confirm('Supprimer cette question et ses réponses ?')) return;
    btn.disabled = true;
    btn.textContent = '...';
    fetch(window.location.href.split('?')[0], {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=delete_question&question_id=' + encodeURIComponent(questionId)
    }).then(r=>r.json()).then(j=>{
        if (j.success) {
            const item = btn.closest('.question-item');
            item.remove();
            questionCount--;
            renumberQuestions();
            updateQuestionCount();
        } else {
            alert('Suppression impossible');
            btn.disabled = false;
            btn.textContent = 'Supprimer';
        }
    }).catch(()=>{
        alert('Erreur réseau');
        btn.disabled = false;
        btn.textContent = 'Supprimer';
    });
}

// Validation basique à la soumission
document.addEventListener('DOMContentLoaded', () => {
    // Comptage init si en édition
    const exist = document.querySelectorAll('.question-item').length;
    if (exist > 0) {
        questionCount = exist;
        updateQuestionCount();
    } else {
        // en création: au moins une question par défaut
        if (document.getElementById('quiz-form')) addQuestion();
    }

    const form = document.getElementById('quiz-form') || document.getElementById('quiz-edit-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const items = document.querySelectorAll('.question-item');
            if (items.length === 0) {
                e.preventDefault();
                alert('Ajoutez au moins une question.');
                return;
            }
            for (let it of items) {
                const text = it.querySelector('textarea[name*="[text]"]');
                if (!text || text.value.trim().length < 3) {
                    e.preventDefault();
                    alert('Chaque question doit avoir un texte (min 3 caractères).');
                    return;
                }
                const answers = it.querySelectorAll('.answers-container .answer-item');
                if (answers.length < 2) {
                    e.preventDefault();
                    alert('Chaque question doit avoir au moins 2 réponses.');
                    return;
                }
                const checked = it.querySelector('input[type="radio"]:checked');
                if (!checked) {
                    e.preventDefault();
                    alert('Sélectionnez la bonne réponse pour chaque question.');
                    return;
                }
                const rightText = it.querySelector(`input[name*="[answers][${checked.value}]"]`);
                if (!rightText || !rightText.value.trim()) {
                    e.preventDefault();
                    alert('La bonne réponse ne peut pas être vide.');
                    return;
                }
            }
        });
    }
});