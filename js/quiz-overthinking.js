/* ==========================================
   QUIZ JAVASCRIPT - OVERTHINKING
   ========================================== */

// Quiz Configuration
const quizConfig = {
    questions: [
        {
            id: 1,
            text: "Jak často se vracíte k situacím, které už skončily?",
            options: [
                { text: "Zřídka", score: 0 },
                { text: "Občas", score: 1 },
                { text: "Často", score: 2 },
                { text: "Neustále", score: 3 }
            ]
        },
        {
            id: 2,
            text: "Máte problém vypnout myšlenky před spaním?",
            options: [
                { text: "Ne, usínám snadno", score: 0 },
                { text: "Občas to trvá déle", score: 1 },
                { text: "Často mi to trvá dlouho", score: 2 },
                { text: "Téměř vždy se nemůžu uklidnit", score: 3 }
            ]
        },
        {
            id: 3,
            text: "Analyzujete detaily konverzací i po jejich skončení?",
            options: [
                { text: "Málokdy", score: 0 },
                { text: "Někdy, když byla důležitá", score: 1 },
                { text: "Často, zejména když bylo něco nejasné", score: 2 },
                { text: "Vždy, znovu a znovu si přehrávám každé slovo", score: 3 }
            ]
        },
        {
            id: 4,
            text: "Vytváříte si v hlavě nejhorší možné scénáře?",
            options: [
                { text: "Ne, obvykle jsem optimista", score: 0 },
                { text: "Občas mě napadnou", score: 1 },
                { text: "Často si představuji, co všechno může jít špatně", score: 2 },
                { text: "Neustále, nemůžu se od toho odpoutat", score: 3 }
            ]
        },
        {
            id: 5,
            text: "Máte pocit, že Vám myšlenky běží i ve chvíli, kdy chcete odpočívat?",
            options: [
                { text: "Ne, dokážu se dobře uvolnit", score: 0 },
                { text: "Občas mi myšlenky bloudí", score: 1 },
                { text: "Často se nemůžu odpojit", score: 2 },
                { text: "Téměř nikdy nejsem v klidu", score: 3 }
            ]
        },
        {
            id: 6,
            text: "Jak moc vás paralyzuje rozhodování?",
            options: [
                { text: "Rozhoduji se poměrně snadno", score: 0 },
                { text: "Občas zvažuji dlouho", score: 1 },
                { text: "Často se nemůžu rozhodnout", score: 2 },
                { text: "Skoro vždy mě to blokuje", score: 3 }
            ]
        },
        {
            id: 7,
            text: "Bojíte se, co si o vás druzí pomyslí?",
            options: [
                { text: "Málokdy mě to trápí", score: 0 },
                { text: "Občas si tím dělám hlavu", score: 1 },
                { text: "Často o tom přemýšlím", score: 2 },
                { text: "Neustále analyzuji reakce druhých", score: 3 }
            ]
        },
        {
            id: 8,
            text: "Máte pocit, že musíte mít všechno pod kontrolou?",
            options: [
                { text: "Ne, jsem v pohodě s nejistotou", score: 0 },
                { text: "Občas se cítím nekomfortně", score: 1 },
                { text: "Často potřebuji vědět, co bude", score: 2 },
                { text: "Neustále, nejistota mě velmi stresuje", score: 3 }
            ]
        },
        {
            id: 9,
            text: "Jak často se cítíte duševně vyčerpaní?",
            options: [
                { text: "Zřídka", score: 0 },
                { text: "Občas", score: 1 },
                { text: "Často", score: 2 },
                { text: "Téměř pořád", score: 3 }
            ]
        },
        {
            id: 10,
            text: "Dokážete být přítomní v momentě?",
            options: [
                { text: "Ano, snadno se soustředím na přítomnost", score: 0 },
                { text: "Občas mi myšlenky utíkají jinam", score: 1 },
                { text: "Často jsem myšlenkami jinde", score: 2 },
                { text: "Téměř nikdy nejsem plně v přítomném okamžiku", score: 3 }
            ]
        }
    ]
};

// Result Interpretations
const resultInterpretations = {
    minimal: {
        range: [0, 7],
        title: "Málo známek nadměrného přemýšlení",
        icon: "✓",
        color: "#4CAF50",
        description: "Ve Vašich odpovědích se objevilo jen málo situací spojených s nadměrným přemýšlením. Výsledek ale nepředstavuje celkové hodnocení Vašeho duševního zdraví.",
        recommendations: [
            "Pokračujte ve svých osvědčených strategiích",
            "Všímejte si, které postupy Vám pomáhají vracet pozornost k přítomnosti",
            "Nezapomínejte na preventivní péči o duševní zdraví",
            "Buďte k sobě laskaví i v případech, kdy se přece jen přistíhnete při přemýšlení"
        ],
        cta: {
            heading: "",
            description: "",
            button: ""
        },
        showCta: false,
        showCrisis: false
    },
    mild: {
        range: [8, 15],
        title: "Občasné nadměrné přemýšlení",
        icon: "○",
        color: "#8BC34A",
        description: "Ve Vašich odpovědích se objevují některé situace, ve kterých přemýšlíte déle nebo intenzivněji, než byste chtěli. Můžete vyzkoušet několik postupů a sledovat, co Vám pomáhá.",
        recommendations: [
            "Zjistěte, zda nejste vyčerpaní – můžete potřebovat odpočinek, ne disciplínu",
            "Vytvořte si podporu – mluvte o tom s důvěryhodnými lidmi",
            "Omezte multitasking a zaměřte se na jednu věc",
            "Zkuste techniku \"5-4-3-2-1\" pro návrat do přítomnosti",
            "Zapište si myšlenky na papír místo jejich opakování v hlavě",
            "Nastavte si \"čas na přemýšlení\" – 15 minut denně"
        ],
        cta: {
            heading: "Chcete tématu lépe porozumět?",
            description: "Pokud Vás opakované přemýšlení zatěžuje, můžete v terapii prozkoumat situace, ve kterých se objevuje, a hledat vhodné způsoby práce s ním.",
            button: "Promluvit si o tom"
        },
        showCrisis: false
    },
    moderate: {
        range: [16, 23],
        title: "Častější nadměrné přemýšlení",
        icon: "!",
        color: "#FFC107",
        description: "Vaše odpovědi naznačují častější výskyt nadměrného přemýšlení. Pokud Vám zasahuje do odpočinku, rozhodování nebo každodenního fungování, může být užitečné začít s ním cíleně pracovat.",
        recommendations: [
            "Uvědomte si, že myšlenky nejsou fakta",
            "Pojmenujte si, že se znovu vracíte ke stejné obavě, a zaměřte pozornost na konkrétní činnost nebo na své okolí",
            "Praktikujte mindfulness nebo meditaci",
            "Omezte kofein, který může zhoršovat úzkostné myšlenky",
            "Pohybujte se – fyzická aktivita pomáhá snížit přemýšlení",
            "Zvažte práci s terapeutem pro hlubší pochopení vzorců"
        ],
        cta: {
            heading: "Je čas aktivně pracovat s overthinkingem",
            description: "V terapii můžete prozkoumat souvislosti nadměrného přemýšlení a hledat způsoby, které budou odpovídat Vaší situaci.",
            button: "Objednat konzultaci"
        },
        showCrisis: false
    },
    high: {
        range: [24, 30],
        title: "Výrazné nadměrné přemýšlení",
        icon: "⚠",
        color: "#F44336",
        description: "Ve Vašich odpovědích se objevují výrazné projevy nadměrného přemýšlení. Pokud Vám narušují spánek, vztahy nebo každodenní fungování, může být užitečné probrat obtíže s psychologem nebo psychoterapeutem.",
        recommendations: [
            "Zvažte odbornou konzultaci, při které můžete prozkoumat souvislosti obtíží ve Vaší konkrétní situaci",
            "Začněte s malými kroky – zlepšení je proces",
            "Buďte k sobě laskaví – overthinking není vaše chyba",
            "Omezte stresory tam, kde to je možné",
            "Vytvořte si večerní rutinu pro uklidnění mysli",
            "Zvažte mindfulness nebo jógu jako doplňkovou podporu"
        ],
        cta: {
            heading: "Nadměrné přemýšlení nemusíte řešit sami",
            description: "V terapii můžete hledat souvislosti svých obtíží a postupy, které Vám pomohou lépe zvládat zatěžující myšlenky.",
            button: "Vyhledat pomoc"
        },
        showCrisis: false
    }
};

// Quiz State
class QuizState {
    constructor() {
        this.currentQuestion = 0;
        this.answers = new Array(quizConfig.questions.length).fill(null);
        this.score = 0;
        this.loadProgress();
    }

    selectAnswer(questionIndex, optionIndex, score) {
        // If there was a previous answer, subtract its score
        const question = quizConfig.questions[questionIndex];
        if (this.answers[questionIndex] !== null) {
            const previousScore = question.options[this.answers[questionIndex]].score;
            this.score -= previousScore;
        }

        // Add new answer and score
        this.answers[questionIndex] = optionIndex;
        this.score += score;
        this.saveProgress();
    }

    nextQuestion() {
        if (this.currentQuestion < quizConfig.questions.length - 1) {
            this.currentQuestion++;
            this.saveProgress();
            return true;
        }
        return false;
    }

    previousQuestion() {
        if (this.currentQuestion > 0) {
            this.currentQuestion--;
            this.saveProgress();
            return true;
        }
        return false;
    }

    getProgress() {
        return Math.round(((this.currentQuestion + 1) / quizConfig.questions.length) * 100);
    }

    saveProgress() {
        const progress = {
            currentQuestion: this.currentQuestion,
            answers: this.answers,
            score: this.score,
            timestamp: Date.now()
        };
        localStorage.setItem('quiz-overthinking-progress', JSON.stringify(progress));
    }

    loadProgress() {
        try {
            const saved = localStorage.getItem('quiz-overthinking-progress');
            if (saved) {
                const data = JSON.parse(saved);
                // Only load if less than 24 hours old
                if (Date.now() - data.timestamp < 86400000) {
                    this.currentQuestion = data.currentQuestion || 0;
                    this.answers = data.answers || new Array(quizConfig.questions.length).fill(null);
                    this.score = data.score || 0;
                }
            }
        } catch (e) {
            console.error('Error loading progress:', e);
        }
    }

    clearProgress() {
        localStorage.removeItem('quiz-overthinking-progress');
    }

    getResult() {
        for (const key in resultInterpretations) {
            const result = resultInterpretations[key];
            if (this.score >= result.range[0] && this.score <= result.range[1]) {
                return { type: key, ...result };
            }
        }
        return resultInterpretations.minimal;
    }
}

// Quiz Controller
class QuizController {
    constructor() {
        this.state = new QuizState();
        this.initializeElements();
        this.attachEventListeners();
        this.checkResumeQuiz();
    }

    initializeElements() {
        // Intro elements
        this.introSection = document.querySelector('.quiz-intro');
        this.startBtn = document.getElementById('startQuizBtn');

        // Quiz elements
        this.quizSection = document.getElementById('quizSection');
        this.questionCard = document.getElementById('questionCard');
        this.questionText = document.getElementById('questionText');
        this.optionsContainer = document.getElementById('optionsContainer');
        this.prevBtn = document.getElementById('prevBtn');
        this.nextBtn = document.getElementById('nextBtn');

        // Progress elements
        this.currentQuestionEl = document.getElementById('currentQuestion');
        this.totalQuestionsEl = document.getElementById('totalQuestions');
        this.progressPercentEl = document.getElementById('progressPercent');
        this.progressFill = document.getElementById('progressFill');
        this.liveRegion = document.getElementById('liveRegion');

        // Results elements
        this.resultsSection = document.getElementById('resultsSection');
        this.resultsIcon = document.getElementById('resultsIcon');
        this.resultsTitle = document.getElementById('resultsTitle');
        this.resultsScore = document.getElementById('resultsScore');
        this.resultsDescription = document.getElementById('resultsDescription');
        this.recommendationsList = document.getElementById('recommendationsList');
        this.resultsCta = document.getElementById('resultsCta');
        this.ctaHeading = document.getElementById('ctaHeading');
        this.ctaDescription = document.getElementById('ctaDescription');
        this.ctaButton = document.getElementById('ctaButton');

        // Set total questions
        if (this.totalQuestionsEl) {
            this.totalQuestionsEl.textContent = quizConfig.questions.length;
        }
    }

    attachEventListeners() {
        if (this.startBtn) {
            this.startBtn.addEventListener('click', () => this.startQuiz());
        }

        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.handlePrevious());
        }

        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.handleNext());
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (this.quizSection.style.display !== 'none') {
                if (e.key === 'Enter' && !this.nextBtn.disabled) {
                    this.handleNext();
                }
            }
        });
    }

    checkResumeQuiz() {
        const saved = localStorage.getItem('quiz-overthinking-progress');
        if (saved) {
            try {
                const data = JSON.parse(saved);
                if (Date.now() - data.timestamp < 86400000 && data.currentQuestion > 0) {
                    const resume = confirm('Chcete pokračovat v nedokončeném testu?');
                    if (!resume) {
                        this.state.clearProgress();
                        this.state = new QuizState();
                    }
                }
            } catch (e) {
                console.error('Error checking resume:', e);
            }
        }
    }

    startQuiz() {
        this.trackEvent('quiz_started');
        this.introSection.style.display = 'none';
        this.quizSection.style.display = 'block';
        this.renderQuestion();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    renderQuestion(shouldScroll = true) {
        const questionIndex = this.state.currentQuestion;
        const question = quizConfig.questions[questionIndex];

        // Update progress
        this.updateProgress();

        // Update question text
        this.questionText.textContent = question.text;

        // Render options
        this.optionsContainer.innerHTML = '';
        question.options.forEach((option, index) => {
            const optionDiv = document.createElement('div');
            optionDiv.className = 'quiz-option';
            if (this.state.answers[questionIndex] === index) {
                optionDiv.classList.add('selected');
            }

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = `question-${question.id}`;
            radio.id = `q${question.id}-opt${index}`;
            radio.value = index;
            if (this.state.answers[questionIndex] === index) {
                radio.checked = true;
            }

            const label = document.createElement('label');
            label.htmlFor = radio.id;
            label.className = 'quiz-option-label';
            label.textContent = option.text;

            optionDiv.appendChild(radio);
            optionDiv.appendChild(label);

            // Click handler for entire option div
            optionDiv.addEventListener('click', (e) => {
                e.preventDefault();
                this.selectOption(questionIndex, index, option.score);
            });

            this.optionsContainer.appendChild(optionDiv);
        });

        // Update navigation buttons
        this.updateNavigationButtons();

        // Announce to screen readers
        this.announceToScreenReader(`Otázka ${questionIndex + 1} z ${quizConfig.questions.length}: ${question.text}`);

        // Only scroll when moving between questions
        if (shouldScroll) {
            const isMobile = window.innerWidth <= 768;
            if (!isMobile) {
                window.scrollTo({ top: this.quizSection.offsetTop - 100, behavior: 'smooth' });
            } else {
                // On mobile, scroll to keep question in view
                this.quizSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    selectOption(questionIndex, optionIndex, score) {
        this.state.selectAnswer(questionIndex, optionIndex, score);

        // Store current scroll position
        const scrollPos = window.scrollY || window.pageYOffset;

        // Update UI
        const options = this.optionsContainer.querySelectorAll('.quiz-option');
        options.forEach((opt, idx) => {
            opt.classList.toggle('selected', idx === optionIndex);
            const radio = opt.querySelector('input[type="radio"]');
            radio.checked = idx === optionIndex;
        });

        // Restore scroll position to prevent unwanted scrolling
        window.scrollTo(0, scrollPos);

        // Enable next button
        this.nextBtn.disabled = false;

        // Track event
        this.trackEvent('quiz_question_answered', `Question ${questionIndex + 1}`);
    }

    handlePrevious() {
        if (this.state.previousQuestion()) {
            this.renderQuestion();
        }
    }

    handleNext() {
        const hasNext = this.state.nextQuestion();
        if (hasNext) {
            this.renderQuestion();
        } else {
            this.showResults();
        }
    }

    updateProgress() {
        const questionNum = this.state.currentQuestion + 1;
        const progress = this.state.getProgress();

        this.currentQuestionEl.textContent = questionNum;
        this.progressPercentEl.textContent = progress;
        this.progressFill.style.width = `${progress}%`;
        this.progressFill.parentElement.setAttribute('aria-valuenow', progress);
    }

    updateNavigationButtons() {
        // Show/hide previous button
        if (this.state.currentQuestion > 0) {
            this.prevBtn.style.display = 'inline-block';
        } else {
            this.prevBtn.style.display = 'none';
        }

        // Update next button text
        if (this.state.currentQuestion === quizConfig.questions.length - 1) {
            this.nextBtn.textContent = 'Zobrazit výsledky →';
        } else {
            this.nextBtn.textContent = 'Další →';
        }

        // Enable/disable next button based on answer
        this.nextBtn.disabled = this.state.answers[this.state.currentQuestion] === null;
    }

    showResults() {
        const result = this.state.getResult();

        // Track completion
        this.trackEvent('quiz_completed', result.type);

        // Hide quiz section
        this.quizSection.style.display = 'none';

        // Show results section
        this.resultsSection.style.display = 'block';

        // Populate results
        this.resultsIcon.textContent = result.icon;
        this.resultsIcon.style.color = result.color;
        this.resultsTitle.textContent = result.title;
        this.resultsTitle.style.color = result.color;
        this.resultsScore.textContent = this.state.score;
        this.resultsDescription.textContent = result.description;

        // Populate recommendations
        this.recommendationsList.innerHTML = '';
        result.recommendations.forEach(rec => {
            const li = document.createElement('li');
            li.textContent = rec;
            this.recommendationsList.appendChild(li);
        });

        if (this.resultsCta) {
            this.resultsCta.style.display = result.showCta === false ? 'none' : 'block';
        }

        // Populate CTA
        if (this.ctaHeading) {
            this.ctaHeading.textContent = result.cta.heading;
        }
        if (this.ctaDescription) {
            this.ctaDescription.textContent = result.cta.description;
        }
        if (this.ctaButton) {
            this.ctaButton.textContent = result.cta.button;
        }

        // Clear saved progress
        this.state.clearProgress();

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    announceToScreenReader(message) {
        if (this.liveRegion) {
            this.liveRegion.textContent = message;
        }
    }

    trackEvent(action, label = '') {
        if (typeof gtag !== 'undefined') {
            gtag('event', action, {
                'event_category': 'Quiz',
                'event_label': label
            });
        }
    }
}

// Social Sharing Functions
function shareQuiz(platform) {
    const url = window.location.href;
    const text = encodeURIComponent('Test overthinkingu - Může vám pomoci zjistit, jak moc vás ovlivňuje přemýšlení');

    console.log('Sharing quiz:', platform, 'URL:', url);

    let shareUrl = '';

    if (platform === 'facebook') {
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
    } else if (platform === 'twitter') {
        shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${text}`;
    }

    console.log('Final share URL:', shareUrl);

    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=600');

        // Track share event
        if (typeof gtag !== 'undefined') {
            gtag('event', 'share', {
                'event_category': 'Quiz',
                'event_label': platform
            });
        }
    }
}

function copyQuizLink() {
    const url = window.location.href;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            showShareMessage('Odkaz byl zkopírován!');
        }).catch(() => {
            fallbackCopyLink(url);
        });
    } else {
        fallbackCopyLink(url);
    }

    // Track copy event
    if (typeof gtag !== 'undefined') {
        gtag('event', 'share', {
            'event_category': 'Quiz',
            'event_label': 'copy_link'
        });
    }
}

function fallbackCopyLink(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        document.execCommand('copy');
        showShareMessage('Odkaz byl zkopírován!');
    } catch (err) {
        showShareMessage('Chyba při kopírování odkazu');
    }

    document.body.removeChild(textarea);
}

function showShareMessage(message) {
    const messageEl = document.getElementById('shareMessage');
    if (messageEl) {
        messageEl.textContent = message;
        messageEl.style.display = 'block';

        setTimeout(() => {
            messageEl.style.display = 'none';
        }, 3000);
    }
}

function restartQuiz() {
    if (confirm('Opravdu chcete začít test znovu? Vaše současné výsledky budou ztraceny.')) {
        // Clear state
        localStorage.removeItem('quiz-overthinking-progress');

        // Reload page to start fresh
        window.location.reload();
    }
}

// Initialize Quiz when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const quiz = new QuizController();
});
