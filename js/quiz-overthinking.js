// Quiz Configuration
const quizConfig = {
    questions: [
        {
            id: 1,
            text: "Jak často se vracíte k situacím, které už dávno skončily?",
            options: [
                { text: "Málokdy, většinou se soustředím na přítomnost", points: 0 },
                { text: "Občas, ale rychle se vrátím do současnosti", points: 1 },
                { text: "Často, myslím na minulé situace několikrát týdně", points: 2 },
                { text: "Neustále, stále dokola analyzuji, co se stalo", points: 3 }
            ]
        },
        {
            id: 2,
            text: "Dokážete vypnout myšlenky před spaním?",
            options: [
                { text: "Ano, usínám snadno a klidně", points: 0 },
                { text: "Většinou ano, jen občas mám problém", points: 1 },
                { text: "Ne, často mi trvá dlouho usnout", points: 2 },
                { text: "Vůbec ne, hlava mi jede i v noci", points: 3 }
            ]
        },
        {
            id: 3,
            text: "Po rozhovoru s někým analyzujete, co jste řekli?",
            options: [
                { text: "Ne, nevracím se k tomu", points: 0 },
                { text: "Jen pokud to bylo důležité", points: 1 },
                { text: "Ano, často si přehrávám důležité konverzace", points: 2 },
                { text: "Vždy, detailně analyzuję každé slovo", points: 3 }
            ]
        },
        {
            id: 4,
            text: "Jak často vytváříte scénáře \"co kdyby\" o budoucnosti?",
            options: [
                { text: "Málokdy, žiju v přítomnosti", points: 0 },
                { text: "Někdy, při důležitých rozhodnutích", points: 1 },
                { text: "Často, plánuji různé varianty", points: 2 },
                { text: "Neustále, připravuję se na každou možnost", points: 3 }
            ]
        },
        {
            id: 5,
            text: "Máte pocit, že potřebujete mít vše pod kontrolou?",
            options: [
                { text: "Ne, jsem v pohodě s nejistotou", points: 0 },
                { text: "Občas, ale dokážu to zvládnout", points: 1 },
                { text: "Ano, potřebuji vědět, co bude", points: 2 },
                { text: "Určitě, nejistota mě velmi stresuje", points: 3 }
            ]
        },
        {
            id: 6,
            text: "Jak dlouho vám trvá rozhodnutí v běžných věcech?",
            options: [
                { text: "Rozhoduji se rychle a bez problémů", points: 0 },
                { text: "Normálně, občas potřebuji chvíli přemýšlet", points: 1 },
                { text: "Dlouho, zvažuji všechny možnosti", points: 2 },
                { text: "Velmi dlouho, často se nedokážu rozhodnout", points: 3 }
            ]
        },
        {
            id: 7,
            text: "Bojíte se udělat chybu?",
            options: [
                { text: "Ne, chyby beru jako součást života", points: 0 },
                { text: "Trochu, ale zvládám to", points: 1 },
                { text: "Ano, snažím se jim vyhnout", points: 2 },
                { text: "Velmi, strach z chyby mě často paralyzuje", points: 3 }
            ]
        },
        {
            id: 8,
            text: "Cítíte se unavení z vlastních myšlenek?",
            options: [
                { text: "Ne, mé myšlenky mě nevyčerpávají", points: 0 },
                { text: "Občas, ale většinou je zvládám", points: 1 },
                { text: "Často, přemýšlení mě unavuje", points: 2 },
                { text: "Téměř neustále, jsem vyčerpaný/-á", points: 3 }
            ]
        },
        {
            id: 9,
            text: "Dokážete se soustředit na jednu věc najednou?",
            options: [
                { text: "Ano, soustředím se dobře", points: 0 },
                { text: "Většinou ano, jen občas se rozptýlím", points: 1 },
                { text: "Ne, myšlenky mi často skáčou", points: 2 },
                { text: "Vůbec ne, hlava mi jede na mnoho věcí najednou", points: 3 }
            ]
        },
        {
            id: 10,
            text: "Jak moc vás zajímá, co si o vás myslí ostatní?",
            options: [
                { text: "Málo, důležitý je můj vlastní názor", points: 0 },
                { text: "Trochu, ale nepřemýšlím o tom moc", points: 1 },
                { text: "Hodně, často to řeším", points: 2 },
                { text: "Velmi, neustále o tom přemýšlím", points: 3 }
            ]
        }
    ],
    results: [
        {
            range: [0, 7],
            level: "healthy",
            title: "Zdravé přemýšlení",
            description: "Gratuluji! Vypadá to, že máte zdravý vztah ke svým myšlenkám. Dokážete přemýšlet, aniž byste se v tom ztráceli. Umíte se soustředit na přítomnost a nepřetěžujete se zbytečnými scénáři.",
            recommendations: [
                "Pokračujte ve své praxi mindfulness nebo jakékoliv aktivitě, která vám pomáhá zůstat v přítomnosti",
                "Sdílejte své strategie s ostatními, kteří s overthinkingem bojují",
                "Buďte k sobě i nadále laskavý/á a udržujte si zdravé hranice"
            ],
            color: "#4CAF50"
        },
        {
            range: [8, 15],
            level: "mild",
            title: "Mírný overthinking",
            description: "Občas se vám stává, že přemýšlíte víc, než by bylo potřeba, ale v celku to zvládáte. Jsou situace, kdy vás myšlenky zatěžují, ale máte nástroje, jak se z toho dostat.",
            recommendations: [
                "Praktikujte techniku časového rámce – dejte si 10 minut na přemýšlení a pak to nechte být",
                "Zkuste si myšlenky zapsat, aby přestaly kolovat v hlavě",
                "Vraťte se k tělu – dýchání, procházka nebo protažení pomohou přerušit kolotoč myšlenek",
                "Přečtěte si náš článek o overthinkingu pro další tipy"
            ],
            color: "#FFC107"
        },
        {
            range: [16, 23],
            level: "moderate",
            title: "Výraznější overthinking",
            description: "Overthinking vás ovlivňuje častěji a více. Myšlenky vás zatěžují, unavují a často vám brání v klidu a soustředění. Je čas na to začít aktivně pracovat s tímto vzorcem.",
            recommendations: [
                "Pojmenujte si, kdy se overthinking spouští – co jsou vaše spouštěče?",
                "Cvičte toleranci k nejistotě – ne vše musí být pod kontrolou",
                "Omezte rozhodování na \"dost dobré\" místo \"dokonalé\"",
                "Vytvořte si rutinu pro uklidnění mysli před spaním",
                "Zvažte práci s terapeutem, který vám pomůže najít vaše vlastní strategie"
            ],
            color: "#FF9800"
        },
        {
            range: [24, 30],
            level: "high",
            title: "Intenzivní overthinking",
            description: "Overthinking vás výrazně ovlivňuje v každodenním životě. Myšlenky vás vyčerpávají, brání vám v odpočinku a pravděpodobně ovlivňují váš spánek, rozhodování i vztahy. Je důležité s tím začít pracovat.",
            recommendations: [
                "Vyhledejte odbornou pomoc – terapeut vám pomůže najít cestu ven z tohoto vzorce",
                "Začněte malými kroky – i 5 minut denní meditace nebo psaní deníku může pomoci",
                "Buďte k sobě laskavý/á – overthinking není vaše chyba, je to naučený vzorec",
                "Omezte příjem informací a podnětů, které mysl ještě více zatěžují",
                "Vytvořte si podporu – mluvte o tom s důvěryhodnými lidmi"
            ],
            color: "#F44336",
            showCTA: true
        }
    ]
};

// Quiz State Management
class QuizState {
    constructor() {
        this.currentQuestion = 0;
        this.answers = new Array(quizConfig.questions.length).fill(null);
        this.score = 0;
        this.loadProgress();
    }

    saveProgress() {
        const progressData = {
            currentQuestion: this.currentQuestion,
            answers: this.answers,
            score: this.score,
            timestamp: Date.now()
        };
        localStorage.setItem('quiz-overthinking-progress', JSON.stringify(progressData));
    }

    loadProgress() {
        const saved = localStorage.getItem('quiz-overthinking-progress');
        if (saved) {
            const data = JSON.parse(saved);
            // Check if progress is less than 24 hours old
            if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                this.currentQuestion = data.currentQuestion;
                this.answers = data.answers;
                this.score = data.score;
            } else {
                this.clearProgress();
            }
        }
    }

    clearProgress() {
        localStorage.removeItem('quiz-overthinking-progress');
    }

    calculateScore() {
        this.score = this.answers.reduce((sum, points) => sum + (points || 0), 0);
        return this.score;
    }

    getResult() {
        const score = this.calculateScore();
        return quizConfig.results.find(result =>
            score >= result.range[0] && score <= result.range[1]
        );
    }
}

// Quiz Controller
class QuizController {
    constructor() {
        this.state = new QuizState();
        this.initializeElements();
        this.attachEventListeners();

        // Resume quiz if in progress
        if (this.state.currentQuestion > 0 && this.state.currentQuestion < quizConfig.questions.length) {
            this.showQuestion();
        }
    }

    initializeElements() {
        this.startBtn = document.getElementById('startQuiz');
        this.quizContainer = document.getElementById('quizContainer');
        this.questionContainer = document.getElementById('questionContainer');
        this.progressBar = document.getElementById('progressBar');
        this.progressText = document.getElementById('progressText');
        this.resultsSection = document.getElementById('resultsSection');
        this.resultsContent = document.getElementById('resultsContent');
        this.retakeBtn = document.getElementById('retakeQuiz');
        this.shareBtn = document.getElementById('shareQuiz');
    }

    attachEventListeners() {
        this.startBtn.addEventListener('click', () => this.startQuiz());
        this.retakeBtn.addEventListener('click', () => this.retakeQuiz());
        this.shareBtn.addEventListener('click', () => this.shareResults());
    }

    startQuiz() {
        // Track quiz start with Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'quiz_start', {
                'quiz_name': 'overthinking'
            });
        }

        document.querySelector('.quiz-intro').style.display = 'none';
        this.quizContainer.style.display = 'block';
        this.showQuestion();
    }

    showQuestion() {
        const question = quizConfig.questions[this.state.currentQuestion];
        const progress = ((this.state.currentQuestion + 1) / quizConfig.questions.length) * 100;

        this.progressBar.style.width = `${progress}%`;
        this.progressText.textContent = `Otázka ${this.state.currentQuestion + 1} z ${quizConfig.questions.length}`;

        this.questionContainer.innerHTML = `
            <div class="quiz-question" data-question="${question.id}">
                <h2 class="quiz-question-text">${question.text}</h2>
                <div class="quiz-options">
                    ${question.options.map((option, index) => `
                        <button class="quiz-option ${this.state.answers[this.state.currentQuestion] === option.points ? 'selected' : ''}"
                                data-points="${option.points}"
                                data-index="${index}">
                            <span class="quiz-option-text">${option.text}</span>
                        </button>
                    `).join('')}
                </div>
                ${this.state.currentQuestion > 0 ? '<button class="btn btn-secondary quiz-back-btn">Zpět</button>' : ''}
            </div>
        `;

        // Attach option click handlers
        const optionButtons = this.questionContainer.querySelectorAll('.quiz-option');
        optionButtons.forEach(btn => {
            btn.addEventListener('click', (e) => this.selectOption(e));
        });

        // Attach back button handler
        const backBtn = this.questionContainer.querySelector('.quiz-back-btn');
        if (backBtn) {
            backBtn.addEventListener('click', () => this.previousQuestion());
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    selectOption(event) {
        const button = event.currentTarget;
        const points = parseInt(button.dataset.points);

        // Remove selected class from all options
        this.questionContainer.querySelectorAll('.quiz-option').forEach(btn => {
            btn.classList.remove('selected');
        });

        // Add selected class to clicked option
        button.classList.add('selected');

        // Save answer
        this.state.answers[this.state.currentQuestion] = points;
        this.state.saveProgress();

        // Track answer with Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'quiz_answer', {
                'quiz_name': 'overthinking',
                'question_number': this.state.currentQuestion + 1,
                'answer_value': points
            });
        }

        // Move to next question after brief delay
        setTimeout(() => {
            this.nextQuestion();
        }, 300);
    }

    nextQuestion() {
        if (this.state.currentQuestion < quizConfig.questions.length - 1) {
            this.state.currentQuestion++;
            this.state.saveProgress();
            this.showQuestion();
        } else {
            this.showResults();
        }
    }

    previousQuestion() {
        if (this.state.currentQuestion > 0) {
            this.state.currentQuestion--;
            this.state.saveProgress();
            this.showQuestion();
        }
    }

    showResults() {
        const result = this.state.getResult();

        // Track quiz completion with Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'quiz_complete', {
                'quiz_name': 'overthinking',
                'score': this.state.score,
                'result_level': result.level
            });
        }

        this.quizContainer.style.display = 'none';
        this.resultsSection.style.display = 'block';

        this.resultsContent.innerHTML = `
            <div class="quiz-result-card" style="border-left: 4px solid ${result.color}">
                <div class="quiz-result-score">
                    <div class="quiz-result-score-circle" style="background: linear-gradient(135deg, ${result.color}22, ${result.color}44)">
                        <span class="quiz-result-score-number">${this.state.score}</span>
                        <span class="quiz-result-score-max">/ 30</span>
                    </div>
                </div>

                <h3 class="quiz-result-title" style="color: ${result.color}">${result.title}</h3>
                <p class="quiz-result-description">${result.description}</p>

                <div class="quiz-result-recommendations">
                    <h4>Co vám může pomoci:</h4>
                    <ul>
                        ${result.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;

        // Clear saved progress
        this.state.clearProgress();

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    retakeQuiz() {
        this.state = new QuizState();
        this.resultsSection.style.display = 'none';
        this.quizContainer.style.display = 'block';
        this.showQuestion();
    }

    shareResults() {
        const result = this.state.getResult();
        const shareText = `Dokončil/a jsem test overthinkingu na KHG Poradenství. Můj výsledek: ${result.title}`;
        const shareUrl = window.location.href;

        if (navigator.share) {
            navigator.share({
                title: 'Test overthinkingu - KHG Poradenství',
                text: shareText,
                url: shareUrl
            }).catch(err => console.log('Sdílení zrušeno'));
        } else {
            // Fallback - copy to clipboard
            navigator.clipboard.writeText(`${shareText}\n${shareUrl}`)
                .then(() => alert('Odkaz zkopírován do schránky!'))
                .catch(err => console.error('Chyba při kopírování:', err));
        }

        // Track sharing with Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'share', {
                'method': 'quiz_share',
                'content_type': 'quiz',
                'item_id': 'overthinking'
            });
        }
    }
}

// Initialize quiz when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new QuizController();
});
