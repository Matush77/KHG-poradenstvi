// Quiz Configuration
const quizConfig = {
    questions: [
        {
            id: 1,
            text: "Co vás nejvíce brání začít s úkolem?",
            options: [
                { text: "Nic zvláštního, většinou začnu bez problémů", points: 0, type: "none" },
                { text: "Strach, že to nebude dost dobré", points: 3, type: "fear" },
                { text: "Úkol se mi zdá příliš velký nebo složitý", points: 3, type: "overwhelm" },
                { text: "Prostě se mi nechce, nemám energii", points: 3, type: "exhaustion" }
            ]
        },
        {
            id: 2,
            text: "Jak často odkládáte i úkoly, které vás baví?",
            options: [
                { text: "Málokdy, úkoly které mě baví dělám ráda", points: 0, type: "none" },
                { text: "Občas, i s oblíbenými věcmi mám občas problém", points: 1, type: "mild" },
                { text: "Často, i když mě něco baví, odkládám to", points: 2, type: "moderate" },
                { text: "Téměř vždy, odkládám všechno bez rozdílu", points: 3, type: "high" }
            ]
        },
        {
            id: 3,
            text: "Jaký máte vztah k chybám a nedokonalosti?",
            options: [
                { text: "Chyby beru jako součást procesu učení", points: 0, type: "none" },
                { text: "Trochu mě štve udělat chybu, ale zvládám to", points: 1, type: "mild" },
                { text: "Bojím se udělat chybu, snažím se jí vyhnout", points: 3, type: "fear" },
                { text: "Musí to být dokonalé, jinak raději nezačnu", points: 3, type: "perfectionism" }
            ]
        },
        {
            id: 4,
            text: "Cítíte se často přetížení nebo unavení?",
            options: [
                { text: "Ne, mám dostatek energie", points: 0, type: "none" },
                { text: "Občas, ale zvládám to", points: 1, type: "mild" },
                { text: "Ano, často se cítím vyčerpaný/-á", points: 3, type: "exhaustion" },
                { text: "Téměř neustále, jsem na dně sil", points: 3, type: "burnout" }
            ]
        },
        {
            id: 5,
            text: "Když přemýšlíte o úkolu, co cítíte?",
            options: [
                { text: "Klid nebo mírné nadšení", points: 0, type: "none" },
                { text: "Lehkou nejistotu, ale zvládnutelnou", points: 1, type: "mild" },
                { text: "Úzkost, stres nebo odpor", points: 3, type: "anxiety" },
                { text: "Silný vnitřní tlak nebo vinu", points: 3, type: "pressure" }
            ]
        },
        {
            id: 6,
            text: "Jak často čekáte na \"správnou chvíli\" nebo \"správnou náladu\"?",
            options: [
                { text: "Málokdy, začnu i když nemám ideální podmínky", points: 0, type: "none" },
                { text: "Někdy, ale většinou se nenechám zastavit", points: 1, type: "mild" },
                { text: "Často, čekám až se budu cítit připravený/-á", points: 2, type: "moderate" },
                { text: "Téměř vždy, bez správné nálady nemůžu začít", points: 3, type: "avoidance" }
            ]
        },
        {
            id: 7,
            text: "Jak se cítíte, když máte deadline blízko?",
            options: [
                { text: "V klidu, mám to pod kontrolou", points: 0, type: "none" },
                { text: "Trochu ve stresu, ale funguje to", points: 1, type: "mild" },
                { text: "Ve velkém stresu, pracuji na poslední chvíli", points: 2, type: "moderate" },
                { text: "V panice, je to můj jediný způsob, jak dokončit věci", points: 3, type: "adrenaline" }
            ]
        },
        {
            id: 8,
            text: "Máte problém s dokončováním věcí?",
            options: [
                { text: "Ne, většinou dokončím, co začnu", points: 0, type: "none" },
                { text: "Občas, ale většinu věcí dotáhnu", points: 1, type: "mild" },
                { text: "Ano, často přeskakuji mezi úkoly", points: 2, type: "moderate" },
                { text: "Ano, mám mnoho rozpracovaných věcí", points: 3, type: "chronic" }
            ]
        },
        {
            id: 9,
            text: "Jak velký vliv má na vás kritika nebo hodnocení druhých?",
            options: [
                { text: "Malý, spoléhám hlavně na sebe", points: 0, type: "none" },
                { text: "Trochu mě ovlivňuje, ale nebrzdí mě to", points: 1, type: "mild" },
                { text: "Hodně, bojím se, co si o mně pomyslí", points: 3, type: "approval" },
                { text: "Velmi, strach z kritiky mě často zastavuje", points: 3, type: "rejection" }
            ]
        },
        {
            id: 10,
            text: "Dělá vám větší problém začít nebo dokončit úkol?",
            options: [
                { text: "Ani jedno, zvládám obojí", points: 0, type: "none" },
                { text: "Začátek je těžší, pak už to jde", points: 2, type: "activation" },
                { text: "Dokončení je problém, ztrácím zájem", points: 2, type: "followthrough" },
                { text: "Obojí je těžké, celý proces je boj", points: 3, type: "comprehensive" }
            ]
        }
    ],
    results: [
        {
            range: [0, 5],
            title: "Minimální prokrastinace",
            description: "Gratuluji! Prokrastinace vás téměř neovlivňuje. Máte zdravý vztah k úkolům a většinou je zvládáte včas a bez zbytečného odkládání. Vaše strategie fungují dobře.",
            recommendations: [
                "Pokračujte v tom, co děláte – vaše přístupy fungují",
                "Můžete se stát inspirací pro ostatní – sdílejte své metody",
                "Buďte k sobě laskavý/-á i v případech, kdy něco přece jen odložíte",
                "Věnujte pozornost preventivní péči o duševní zdraví"
            ],
            primaryCause: "none",
            color: "#4CAF50"
        },
        {
            range: [6, 12],
            title: "Mírná prokrastinace",
            description: "Občas odkládáte úkoly, ale v celku si s tím poradíte. Prokrastinace vás ovlivňuje jen minimálně a většinou máte nástroje, jak ji překonat.",
            recommendations: [
                "Zmenšete začátek na nejmenší možný krok",
                "Pracujte v krátkých časových blocích (např. 10-2-10)",
                "Vytvořte si příjemné prostředí pro práci",
                "Oceňujte se za dokončení, ne za dokonalost"
            ],
            primaryCause: "mild",
            color: "#8BC34A"
        },
        {
            range: [13, 20],
            title: "Střední prokrastinace",
            description: "Prokrastinace vás ovlivňuje častěji a více. Odkládání úkolů vám přináší stres, vinu nebo tlak. Je čas začít aktivně pracovat s tímto vzorcem.",
            recommendations: [
                "Identifikujte, jakou emoci úkol spouští (strach, stud, odpor?)",
                "Nastavte si \"dost dobré\" místo \"dokonalé\"",
                "Zkuste techniku micro-tasking – rozdělte úkol na nejmenší kroky",
                "Vytvořte si rutinu pro začínání úkolů",
                "Zvažte práci s terapeutem pro hlubší pochopení vzorců"
            ],
            primaryCause: "moderate",
            color: "#FFC107"
        },
        {
            range: [21, 30],
            title: "Výrazná prokrastinace",
            description: "Prokrastinace vás výrazně ovlivňuje v každodenním životě. Odkládání úkolů vám přináší značný stres, vinu a pravděpodobně ovlivňuje vaše vztahy, práci i osobní cíle.",
            recommendations: [
                "Vyhledejte odbornou pomoc – terapeut vám pomůže najít kořeny prokrastinace",
                "Začněte s nejmenšími možnými kroky (2 minuty)",
                "Buďte k sobě laskavý/-á – prokrastinace není lenost",
                "Zjistěte, zda nejste vyčerpaní – možná potřebujete odpočinek, ne disciplínu",
                "Vytvořte si podporu – mluvte o tom s důvěryhodnými lidmi",
                "Omezte multitasking a zaměřte se na jednu věc"
            ],
            primaryCause: "high",
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
        this.answerTypes = new Array(quizConfig.questions.length).fill(null);
        this.score = 0;
        this.loadProgress();
    }

    saveProgress() {
        const progressData = {
            currentQuestion: this.currentQuestion,
            answers: this.answers,
            answerTypes: this.answerTypes,
            score: this.score,
            timestamp: Date.now()
        };
        localStorage.setItem('quiz-prokrastinace-progress', JSON.stringify(progressData));
    }

    loadProgress() {
        const saved = localStorage.getItem('quiz-prokrastinace-progress');
        if (saved) {
            const data = JSON.parse(saved);
            // Check if progress is less than 24 hours old
            if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                this.currentQuestion = data.currentQuestion;
                this.answers = data.answers;
                this.answerTypes = data.answerTypes || new Array(quizConfig.questions.length).fill(null);
                this.score = data.score;
            } else {
                this.clearProgress();
            }
        }
    }

    clearProgress() {
        localStorage.removeItem('quiz-prokrastinace-progress');
    }

    calculateScore() {
        this.score = this.answers.reduce((sum, points) => sum + (points || 0), 0);
        return this.score;
    }

    analyzePrimaryCause() {
        const typeCounts = {};
        this.answerTypes.forEach(type => {
            if (type && type !== 'none' && type !== 'mild') {
                typeCounts[type] = (typeCounts[type] || 0) + 1;
            }
        });

        let primaryCause = 'general';
        let maxCount = 0;
        Object.entries(typeCounts).forEach(([type, count]) => {
            if (count > maxCount) {
                maxCount = count;
                primaryCause = type;
            }
        });

        return primaryCause;
    }

    getResult() {
        const score = this.calculateScore();
        const result = quizConfig.results.find(result =>
            score >= result.range[0] && score <= result.range[1]
        );

        // Add cause-specific insights
        if (score > 12) {
            const primaryCause = this.analyzePrimaryCause();
            result.insight = this.getCauseInsight(primaryCause);
        }

        return result;
    }

    getCauseInsight(cause) {
        const insights = {
            'fear': 'Váš hlavní problém je strach z neúspěchu. Pamatujte: nedokonalý začátek je lepší než dokonalé nic.',
            'perfectionism': 'Perfekcionismus je váš hlavní protivník. Zkuste si nastavit \"dost dobré\" místo \"dokonalé\".',
            'overwhelm': 'Úkoly se vám zdají příliš velké. Rozdělte je na mikroskopické kroky – tak malé, aby se jim mozek nebránil.',
            'exhaustion': 'Může to být vyčerpání, ne prokrastinace. Možná potřebujete odpočinek víc než disciplínu.',
            'anxiety': 'Úzkost je vaším hlavním spouštěčem. Pracujte s emocí kolem úkolu, ne s úkolem samotným.',
            'approval': 'Strach z hodnocení vás brzdí. Zkuste si pamatovat: nemusíte být dokonalý/-á, abyste byl/a hodnotný/-á.',
            'avoidance': 'Vyhýbání se nepohodlí je vaším vzorcem. Začněte s 2 minutami – jen otevřít dokument, jen jeden krok.'
        };
        return insights[cause] || '';
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
                'quiz_name': 'prokrastinace'
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
                                data-type="${option.type}"
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
        const type = button.dataset.type;

        // Remove selected class from all options
        this.questionContainer.querySelectorAll('.quiz-option').forEach(btn => {
            btn.classList.remove('selected');
        });

        // Add selected class to clicked option
        button.classList.add('selected');

        // Save answer
        this.state.answers[this.state.currentQuestion] = points;
        this.state.answerTypes[this.state.currentQuestion] = type;
        this.state.saveProgress();

        // Track answer with Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'quiz_answer', {
                'quiz_name': 'prokrastinace',
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
                'quiz_name': 'prokrastinace',
                'score': this.state.score,
                'result_level': result.primaryCause
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

                ${result.insight ? `<p class="quiz-result-insight"><strong>Váš hlavní vzorec:</strong> ${result.insight}</p>` : ''}

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
        const shareText = `Dokončil/a jsem test prokrastinace na KHG Poradenství. Můj výsledek: ${result.title}`;
        const shareUrl = window.location.href;

        if (navigator.share) {
            navigator.share({
                title: 'Test prokrastinace - KHG Poradenství',
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
                'item_id': 'prokrastinace'
            });
        }
    }
}

// Initialize quiz when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new QuizController();
});
