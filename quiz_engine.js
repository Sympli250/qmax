/**
 * Quiz App v0.3 - Moteur de quiz (front)
 * - Récupère les questions via API
 * - Enregistre le participant
 * - Sauvegarde chaque réponse
 * - Affiche le score final
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
        this.init();
    }

    async init() {
        try {
            this.loading();
            await this.loadQuestions();
            await this.register();
            this.startTime = new Date();
            this.render();
        } catch (e) {
            this.error('Impossible de charger le quiz: ' + (e?.message || e));
        }
    }

    loading() {
        const c = document.getElementById('quiz-container');
        c.innerHTML = '<div class="panel center"><div class="spinner"></div><p>Chargement...</p></div>';
    }

    async loadQuestions() {
        const r = await fetch(`${this.baseUrl}?api=quiz-questions&code=${encodeURIComponent(this.quizCode)}`);
        if (!r.ok) throw new Error(await r.text());
        const data = await r.json();
        if (!Array.isArray(data) || data.length === 0) throw new Error('Aucune question');
        this.questions = data;
    }

    async register() {
        const r = await fetch(`${this.baseUrl}?api=participant-register`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ quiz_code: this.quizCode, nickname: this.participantName })
        });
        if (!r.ok) throw new Error(await r.text());
        const j = await r.json();
        this.participantId = j.participant_id;
        if (!this.participantId) throw new Error('Identifiant participant invalide');
    }

    render() {
        if (this.index >= this.questions.length) { this.showResults(); return; }
        const q = this.questions[this.index];
        const c = document.getElementById('quiz-container');
        const progress = Math.round((this.index) * 100 / this.questions.length);

        c.innerHTML = `
            <div class="panel">
                <div class="question-header">
                    <div class="badge">Question ${this.index+1}/${this.questions.length}</div>
                    <div class="badge info" id="quiz-timer">0:00</div>
                    <div class="badge green">${this.escape(this.participantName)}</div>
                </div>
                <h2 class="question-text">${this.escape(q.text)}</h2>
                <div class="answers-grid">
                    ${q.answers.map((a,i)=>`
                        <button class="answer-btn" data-id="${a.id}">
                            ${String.fromCharCode(65+i)}. ${this.escape(a.text)}
                        </button>
                    `).join('')}
                </div>
                <div class="progress"><div class="fill" style="width:${progress}%"></div></div>
            </div>
        `;

        this.startTimer();

        c.querySelectorAll('.answer-btn').forEach(btn=>{
            btn.addEventListener('click', async () => {
                c.querySelectorAll('.answer-btn').forEach(b=>b.disabled=true);
                btn.classList.add('selected');
                await this.submit(parseInt(btn.dataset.id,10));
            });
        });
    }

    async submit(answerId) {
        const q = this.questions[this.index];
        try {
            const r = await fetch(`${this.baseUrl}?api=participant-answer`, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    participant_id: this.participantId,
                    question_id: q.id,
                    answer_id: answerId
                })
            });
            if (!r.ok) throw new Error(await r.text());
            this.index++;
            setTimeout(()=>this.render(), 500);
        } catch (e) {
            this.error('Erreur lors de la sauvegarde de la réponse');
        }
    }

    async showResults() {
        try {
            const r = await fetch(`${this.baseUrl}?api=participant-score&participant_id=${encodeURIComponent(this.participantId)}`);
            if (!r.ok) throw new Error(await r.text());
            const s = await r.json();

            const wr = await fetch(`${this.baseUrl}?api=participant-wrong-answers&participant_id=${encodeURIComponent(this.participantId)}`);
            const wrong = wr.ok ? await wr.json() : [];

            const pct = s && s.total_questions > 0 ? Math.round( (s.correct_answers*100) / s.total_questions ) : 0;
            const c = document.getElementById('quiz-container');
            const dur = this.duration(this.startTime, new Date());
            clearInterval(this.timerInterval);

            const reviewHtml = wrong.length ? `
                <h3>Révision des erreurs</h3>
                ${wrong.map(w => `
                    <div class="review-item">
                        <p class="question-text">${this.escape(w.question)}</p>
                        <p class="your-answer">Votre réponse : ${this.escape(w.your_answer)}</p>
                        <p class="correct-answer">Bonne réponse : ${this.escape(w.correct_answer)}</p>
                        ${w.comment ? `<p class="question-comment">${this.escape(w.comment)}</p>` : ''}
                    </div>
                `).join('')}
            ` : '';

            c.innerHTML = `
                <div class="panel">
                    <div class="center">
                        <h2>Quiz terminé</h2>
                        <p><strong>${this.escape(this.participantName)}</strong></p>
                        <div class="score-box">
                            <div class="big">${s.correct_answers}/${s.total_questions}</div>
                            <div class="big green">${pct}%</div>
                            <div>Temps: ${dur}</div>
                        </div>
                        <p class="success-score">Score de réussite : ${pct}%</p>
                    </div>
                    ${reviewHtml}
                    <p class="center"><a class="btn-primary" href="${this.baseUrl}">Retour à l'accueil</a></p>
                </div>
            `;
        } catch (e) {
            this.error('Erreur lors du calcul du score');
        }
    }

    startTimer(){
        const t = document.getElementById('quiz-timer');
        if(!t) return;
        if (this.timerInterval) clearInterval(this.timerInterval);
        const update = () => { t.textContent = this.duration(this.startTime, new Date()); };
        update();
        this.timerInterval = setInterval(update, 1000);
    }

    duration(a,b){
        const ms = Math.max(0, b - a);
        const m = Math.floor(ms/60000);
        const s = Math.floor((ms%60000)/1000);
        return `${m}:${String(s).padStart(2,'0')}`;
    }

    error(msg){
        const c = document.getElementById('quiz-container');
        c.innerHTML = `
            <div class="panel center">
                <h2>Erreur</h2>
                <p>${this.escape(msg)}</p>
                <p><button class="btn-secondary" onclick="location.reload()">Réessayer</button></p>
            </div>
        `;
    }

    escape(t){ const d=document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; }
}
window.QuizEngine = QuizEngine;
