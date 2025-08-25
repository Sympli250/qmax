/**
 * Quiz App v0.3 - Moteur de quiz moderne
 * Design inspiré de Symplissime AI
 */
class QuizEngine {
    constructor(quizCode, participantName) {
        this.quizCode = quizCode;
        this.participantName = participantName || 'Participant';
        this.baseUrl = window.baseUrl || 'index.php';
        this.questions = [];
        this.index = 0;
        this.participantId = null;
        this.startTime = null;
        this.timerInterval = null;
        this.selectedAnswers = new Map();
        this.init();
    }

    async init() {
        try {
            this.showLoading();
            await this.loadQuestions();
            await this.register();
            this.startTime = new Date();
            this.renderQuestion();
        } catch (e) {
            this.showError('Impossible de charger le quiz: ' + (e?.message || e));
        }
    }

    showLoading() {
        const container = document.getElementById('quiz-container');
        container.innerHTML = `
            <div class="modern-card animate-fade-in">
                <div class="card-body text-center" style="padding: 64px 32px;">
                    <div class="spinner animate-pulse"></div>
                    <h3 style="margin: 24px 0 16px; color: var(--primary);">Préparation du quiz</h3>
                    <p class="text-muted">Chargement des questions en cours...</p>
                </div>
            </div>
        `;
    }

    async loadQuestions() {
        const response = await fetch(`${this.baseUrl}?api=quiz-questions&code=${encodeURIComponent(this.quizCode)}`);
        if (!response.ok) {
            const error = await response.text();
            throw new Error(error);
        }
        const data = await response.json();
        if (!Array.isArray(data) || data.length === 0) {
            throw new Error('Aucune question trouvée pour ce quiz');
        }
        this.questions = data;
    }

    async register() {
        const response = await fetch(`${this.baseUrl}?api=participant-register`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                quiz_code: this.quizCode,
                nickname: this.participantName
            })
        });

        if (!response.ok) {
            const error = await response.text();
            throw new Error(error);
        }

        const result = await response.json();
        this.participantId = result.participant_id;
        
        if (!this.participantId) {
            throw new Error('Erreur lors de l\'enregistrement du participant');
        }
    }

    renderQuestion() {
        if (this.index >= this.questions.length) {
            this.showResults();
            return;
        }

        const question = this.questions[this.index];
        const container = document.getElementById('quiz-container');
        const progress = Math.round((this.index) * 100 / this.questions.length);

        container.innerHTML = `
            <div class="question-card animate-fade-in">
                <div class="question-header">
                    <div class="question-meta">
                        <div class="question-badge">Question ${this.index + 1}/${this.questions.length}</div>
                        <div class="question-badge" id="quiz-timer">⏱️ 0:00</div>
                        <div class="question-badge">👤 ${this.escape(this.participantName)}</div>
                    </div>
                </div>
                
                <div class="question-content">
                    <h2 class="question-text">${this.escape(question.text)}</h2>
                    
                    ${question.comment ? `
                        <div class="alert" style="background: #f0f9ff; border-color: var(--info); color: #0369a1; margin-bottom: 32px;">
                            💡 ${this.escape(question.comment)}
                        </div>
                    ` : ''}
                    
                    <div class="answers-grid">
                        ${question.answers.map((answer, index) => `
                            <button class="answer-option" data-answer-id="${answer.id}" onclick="quizEngine.selectAnswer(${answer.id}, this)">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="
                                        width: 32px; 
                                        height: 32px; 
                                        background: linear-gradient(135deg, var(--primary), var(--secondary)); 
                                        border-radius: 50%; 
                                        display: flex; 
                                        align-items: center; 
                                        justify-content: center; 
                                        color: white; 
                                        font-weight: 700;
                                        font-size: 14px;
                                    ">
                                        ${String.fromCharCode(65 + index)}
                                    </div>
                                    <div style="flex: 1; text-align: left;">
                                        ${this.escape(answer.text)}
                                    </div>
                                </div>
                            </button>
                        `).join('')}
                    </div>
                </div>

                <div class="progress-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 14px; color: var(--text-muted);">Progression</span>
                        <span style="font-size: 14px; font-weight: 600; color: var(--primary);">${progress}%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress}%"></div>
                    </div>
                </div>
            </div>
        `;

        this.startTimer();
        this.animateAnswers();
    }

    selectAnswer(answerId, buttonElement) {
        // Désactiver tous les boutons
        const allButtons = document.querySelectorAll('.answer-option');
        allButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        });

        // Mettre en évidence la réponse sélectionnée
        buttonElement.classList.add('selected');
        buttonElement.style.opacity = '1';
        buttonElement.style.transform = 'scale(1.02)';

        // Animation de confirmation
        const checkmark = document.createElement('div');
        checkmark.innerHTML = '✅';
        checkmark.style.position = 'absolute';
        checkmark.style.top = '10px';
        checkmark.style.right = '10px';
        checkmark.style.fontSize = '24px';
        checkmark.style.animation = 'fadeIn 0.3s ease';
        buttonElement.style.position = 'relative';
        buttonElement.appendChild(checkmark);

        // Sauvegarder et passer à la question suivante
        setTimeout(async () => {
            await this.submitAnswer(answerId);
            this.index++;
            this.renderQuestion();
        }, 800);
    }

    async submitAnswer(answerId) {
        const questionId = this.questions[this.index].id;
        
        try {
            const response = await fetch(`${this.baseUrl}?api=participant-answer`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    participant_id: this.participantId,
                    question_id: questionId,
                    answer_id: answerId
                })
            });

            if (!response.ok) {
                throw new Error('Erreur lors de la sauvegarde');
            }

            this.selectedAnswers.set(questionId, answerId);
        } catch (error) {
            console.error('Erreur lors de la soumission:', error);
            this.showError('Erreur lors de la sauvegarde de votre réponse');
        }
    }

    async showResults() {
        try {
            // Récupérer le score
            const scoreResponse = await fetch(`${this.baseUrl}?api=participant-score&participant_id=${this.participantId}`);
            if (!scoreResponse.ok) throw new Error('Impossible de récupérer le score');
            const score = await scoreResponse.json();

            // Récupérer les erreurs
            const wrongResponse = await fetch(`${this.baseUrl}?api=participant-wrong-answers&participant_id=${this.participantId}`);
            const wrongAnswers = wrongResponse.ok ? await wrongResponse.json() : [];

            const percentage = score.total_questions > 0 ? Math.round((score.correct_answers * 100) / score.total_questions) : 0;
            const duration = this.getDuration(this.startTime, new Date());
            
            clearInterval(this.timerInterval);

            // Déterminer le message de félicitation
            let congratsMessage = '';
            let congratsEmoji = '';
            if (percentage >= 90) {
                congratsMessage = 'Excellent ! Vous maîtrisez parfaitement le sujet !';
                congratsEmoji = '🏆';
            } else if (percentage >= 70) {
                congratsMessage = 'Très bien ! Vous avez une bonne compréhension.';
                congratsEmoji = '🎉';
            } else if (percentage >= 50) {
                congratsMessage = 'Pas mal ! Il y a encore quelques points à revoir.';
                congratsEmoji = '👍';
            } else {
                congratsMessage = 'Il serait bon de réviser le contenu avant de recommencer.';
                congratsEmoji = '📚';
            }

            const container = document.getElementById('quiz-container');
            container.innerHTML = `
                <div class="results-card animate-fade-in">
                    <div class="results-header">
                        <div style="font-size: 64px; margin-bottom: 16px;">${congratsEmoji}</div>
                        <h2 class="results-title">Quiz terminé !</h2>
                        <p style="font-size: 18px; opacity: 0.95;">
                            Bravo ${this.escape(this.participantName)} !
                        </p>
                    </div>

                    <div style="padding: 32px;">
                        <div class="score-display">
                            <div class="score-item">
                                <div class="score-number">${score.correct_answers}</div>
                                <div class="score-label">Bonnes réponses</div>
                            </div>
                            <div class="score-item">
                                <div class="score-number">${score.total_questions}</div>
                                <div class="score-label">Total questions</div>
                            </div>
                            <div class="score-item">
                                <div class="score-number" style="color: ${percentage >= 70 ? 'var(--success)' : percentage >= 50 ? 'var(--warning)' : 'var(--danger)'}">${percentage}%</div>
                                <div class="score-label">Score final</div>
                            </div>
                            <div class="score-item">
                                <div class="score-number" style="font-size: 24px;">${duration}</div>
                                <div class="score-label">Temps total</div>
                            </div>
                        </div>

                        <div class="alert success" style="margin: 32px 0;">
                            ${congratsMessage}
                        </div>

                        ${wrongAnswers.length > 0 ? `
                            <div style="margin-top: 32px;">
                                <h3 style="color: var(--primary); margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                                    📝 Révision des erreurs
                                </h3>
                                <div style="display: grid; gap: 16px;">
                                    ${wrongAnswers.map(wrong => `
                                        <div class="modern-card">
                                            <div style="padding: 24px; border-left: 4px solid var(--danger);">
                                                <h4 style="margin-bottom: 16px; color: var(--text-primary);">
                                                    ${this.escape(wrong.question)}
                                                </h4>
                                                <div style="display: grid; gap: 12px;">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <span style="color: var(--danger);">❌</span>
                                                        <span style="color: var(--text-muted);">Votre réponse :</span>
                                                        <strong style="color: var(--danger);">${this.escape(wrong.your_answer)}</strong>
                                                    </div>
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <span style="color: var(--success);">✅</span>
                                                        <span style="color: var(--text-muted);">Bonne réponse :</span>
                                                        <strong style="color: var(--success);">${this.escape(wrong.correct_answer)}</strong>
                                                    </div>
                                                    ${wrong.comment ? `
                                                        <div style="margin-top: 12px; padding: 12px; background: var(--gray-100); border-radius: var(--radius); font-style: italic; color: var(--text-muted);">
                                                            💡 ${this.escape(wrong.comment)}
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : `
                            <div class="modern-card" style="margin-top: 32px;">
                                <div style="padding: 24px; text-align: center;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
                                    <h3 style="color: var(--success); margin-bottom: 8px;">Parfait !</h3>
                                    <p class="text-muted">Vous avez répondu correctement à toutes les questions.</p>
                                </div>
                            </div>
                        `}

                        <div style="text-align: center; margin-top: 32px;">
                            <a href="${this.baseUrl}" class="btn btn-primary" style="margin-right: 16px;">
                                🏠 Retour à l'accueil
                            </a>
                            <button onclick="location.reload()" class="btn btn-secondary">
                                🔄 Recommencer
                            </button>
                        </div>
                    </div>
                </div>
            `;

        } catch (error) {
            this.showError('Erreur lors de l\'affichage des résultats: ' + error.message);
        }
    }

    startTimer() {
        const timerElement = document.getElementById('quiz-timer');
        if (!timerElement) return;

        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }

        const updateTimer = () => {
            const duration = this.getDuration(this.startTime, new Date());
            timerElement.textContent = `⏱️ ${duration}`;
        };

        updateTimer();
        this.timerInterval = setInterval(updateTimer, 1000);
    }

    getDuration(start, end) {
        const diff = Math.max(0, end - start);
        const minutes = Math.floor(diff / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }

    animateAnswers() {
        const answers = document.querySelectorAll('.answer-option');
        answers.forEach((answer, index) => {
            answer.style.opacity = '0';
            answer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                answer.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                answer.style.opacity = '1';
                answer.style.transform = 'translateY(0)';
            }, index * 100 + 200);
        });
    }

    showError(message) {
        const container = document.getElementById('quiz-container');
        container.innerHTML = `
            <div class="modern-card animate-fade-in">
                <div class="card-body text-center" style="padding: 48px 32px;">
                    <div style="font-size: 64px; margin-bottom: 24px;">😕</div>
                    <h2 style="color: var(--danger); margin-bottom: 16px;">Erreur</h2>
                    <p style="color: var(--text-muted); margin-bottom: 32px;">${this.escape(message)}</p>
                    <div class="flex justify-center gap-16">
                        <button onclick="location.reload()" class="btn btn-primary">
                            🔄 Réessayer
                        </button>
                        <a href="${this.baseUrl}" class="btn btn-secondary">
                            🏠 Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    escape(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
}

// Rendre la classe disponible globalement
window.QuizEngine = QuizEngine;