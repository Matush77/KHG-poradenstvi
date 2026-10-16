/* ==========================================
   QUIZ JAVASCRIPT - TOXICKÝ VZTAH
   ========================================== */

// Quiz Configuration
const quizConfig = {
    questions: [
        {
            id: 1,
            text: "Jak často váš partner respektuje vaše osobní hranice?",
            options: [
                { text: "Vždy", score: 0 },
                { text: "Většinou", score: 2 },
                { text: "Občas", score: 4 },
                { text: "Zřídka", score: 6 },
                { text: "Nikdy", score: 8 }
            ]
        },
        {
            id: 2,
            text: "Cítíte se svobodně vyjádřit svůj názor, i když se neshodujete?",
            options: [
                { text: "Určitě ano", score: 0 },
                { text: "Spíše ano", score: 2 },
                { text: "Někdy ano, někdy ne", score: 4 },
                { text: "Spíše ne", score: 6 },
                { text: "Určitě ne", score: 8 }
            ]
        },
        {
            id: 3,
            text: "Jak reaguje váš partner, když řeknete \"ne\"?",
            options: [
                { text: "Respektuje to bez problémů", score: 0 },
                { text: "Většinou to akceptuje", score: 2 },
                { text: "Někdy se zlobí, ale přijme to", score: 4 },
                { text: "Často se naštve nebo mě přesvědčuje", score: 6 },
                { text: "Nikdy to nepřijme, trvá na svém", score: 8 }
            ]
        },
        {
            id: 4,
            text: "Jak často vás partner kritizuje nebo snižuje?",
            options: [
                { text: "Nikdy", score: 0 },
                { text: "Zřídka", score: 2 },
                { text: "Občas", score: 4 },
                { text: "Často", score: 6 },
                { text: "Neustále", score: 8 }
            ]
        },
        {
            id: 5,
            text: "Cítíte se ve vztahu bezpečně a respektovaně?",
            options: [
                { text: "Vždy", score: 0 },
                { text: "Většinou", score: 2 },
                { text: "Někdy ano, někdy ne", score: 5 },
                { text: "Spíše ne", score: 7, redFlag: true },
                { text: "Určitě ne", score: 9, redFlag: true }
            ]
        },
        {
            id: 6,
            text: "Máte pocit, že chodíte \"po špičkách\", abyste partnera nenaštvali?",
            options: [
                { text: "Nikdy", score: 0 },
                { text: "Zřídka", score: 3 },
                { text: "Občas", score: 5 },
                { text: "Často", score: 7 },
                { text: "Neustále", score: 9, redFlag: true }
            ]
        },
        {
            id: 7,
            text: "Snaží se váš partner kontrolovat, kam chodíte nebo s kým trávíte čas?",
            options: [
                { text: "Vůbec ne", score: 0 },
                { text: "Pouze se zajímá", score: 1 },
                { text: "Někdy je přehnaně zvědavý/á", score: 4 },
                { text: "Často chce vědět všechno", score: 7 },
                { text: "Neustále mě kontroluje", score: 9, redFlag: true }
            ]
        },
        {
            id: 8,
            text: "Jak se váš partner staví k vašim přátelům a rodině?",
            options: [
                { text: "Podporuje mé vztahy", score: 0 },
                { text: "Je neutrální", score: 2 },
                { text: "Někdy je kritický/á", score: 4 },
                { text: "Často kritizuje nebo odrazuje od setkání", score: 7 },
                { text: "Izoluje mě od blízkých", score: 9, redFlag: true }
            ]
        },
        {
            id: 9,
            text: "Jak váš partner řeší konflikty?",
            options: [
                { text: "Klidně a konstruktivně diskutujeme", score: 0 },
                { text: "Většinou dobře, občas se pohádáme", score: 2 },
                { text: "Často se vyhýbá nebo přehání", score: 5 },
                { text: "Křičí, obviňuje nebo manipuluje", score: 7 },
                { text: "Používá agresivní chování", score: 9, redFlag: true }
            ]
        },
        {
            id: 10,
            text: "Přijímá váš partner zodpovědnost za své chování?",
            options: [
                { text: "Vždy", score: 0 },
                { text: "Většinou", score: 2 },
                { text: "Někdy", score: 4 },
                { text: "Zřídka, často viní mě", score: 7 },
                { text: "Nikdy, vždy jsem já ten špatný/á", score: 9 }
            ]
        }
    ]
};

// Result Interpretations
const resultInterpretations = {
    healthy: {
        range: [0, 20],
        title: "Převažují znaky respektu a bezpečí",
        icon: "✓",
        color: "#4A9D7B",
        description: "Ve Vašich odpovědích převažují znaky respektu, důvěry a bezpečí. Test však nemůže posoudit celý vztah ani vyloučit konkrétní problematické chování.",
        recommendations: [
            "Pokračujte v otevřené komunikaci a vzájemném respektu",
            "Věnujte čas společným aktivitám i individuálním zájmům",
            "Pravidelně si říkejte, co v vztahu funguje dobře",
            "Buďte pozorní k potřebám partnera i svým vlastním"
        ],
        cta: {
            heading: "",
            description: "",
            button: ""
        },
        showCta: false,
        showCrisis: false
    },
    warning: {
        range: [21, 45],
        title: "Některé varovné signály",
        icon: "⚠",
        color: "#E8A87C",
        description: "Některé odpovědi poukazují na chování, kterému je vhodné věnovat pozornost. Závažnost situace nelze určit pouze podle celkového skóre – důležitý je konkrétní kontext, zejména přítomnost strachu, nátlaku, kontroly, vyhrožování nebo násilí.",
        recommendations: [
            "O tom, co Vás trápí, mluvte s partnerem pouze tehdy, pokud je to pro Vás bezpečné",
            "Pokud se obáváte partnerovy reakce, svěřte se nejprve důvěryhodné osobě nebo odborné službě",
            "Zvažte individuální odbornou konzultaci pro ujasnění situace",
            "Nevzdávejte se svých přátel a osobních zájmů",
            "Sledujte, zda se situace zlepšuje nebo zhoršuje"
        ],
        cta: {
            heading: "Chcete si situaci bezpečně ujasnit?",
            description: "Při individuální konzultaci můžete popsat konkrétní situaci a promyslet další kroky s ohledem na své potřeby a bezpečí.",
            button: "Promluvit si o tom"
        },
        showCrisis: false
    },
    concerning: {
        range: [46, 65],
        title: "Výrazné znaky nezdravého vztahu",
        icon: "!",
        color: "#D97B7B",
        description: "Některé Vaše odpovědi mohou ukazovat na opakované nerespektování hranic, kontrolu nebo manipulaci. Test nemůže určit povahu celého vztahu, ale tyto konkrétní projevy je vhodné brát vážně.",
        recommendations: [
            "Vaše pocity jsou platné a důležité - věřte jim",
            "Promluvte si s důvěryhodnou osobou o vaší situaci",
            "Zvažte individuální odbornou konzultaci pro podporu a ujasnění situace",
            "Vytvořte si bezpečnostní plán, pokud se cítíte ohroženi",
            "Pamatujte: máte právo na bezpečný a respektující vztah",
            "Informujte se o možnostech pomoci a podpory"
        ],
        cta: {
            heading: "Nejste na to sami",
            description: "Individuální konzultace může nabídnout prostor pro zmapování situace a hledání bezpečných dalších kroků.",
            button: "Objednat konzultaci"
        },
        showCrisis: false
    },
    toxic: {
        range: [66, 86],
        title: "Vážné znaky toxického vztahu",
        icon: "⚠",
        color: "#D97B7B",
        description: "Některé z Vašich odpovědí popisují závažné varovné signály. Test nemůže určit povahu celého vztahu, ale pokud zažíváte strach, nátlak, kontrolu, vyhrožování nebo násilí, je důležité vyhledat bezpečnou podporu.",
        recommendations: [
            "Vaše bezpečnost je absolutní prioritou",
            "Obraťte se na odbornou pomoc - psychologa nebo centrum pomoci",
            "Svěřte se někomu, komu důvěřujete",
            "Vytvořte si bezpečnostní plán pro případ eskalace",
            "Nejste zodpovědní za chování druhého člověka",
            "Odchod z toxického vztahu je proces - buďte trpěliví a laskavý/á k sobě",
            "Zdokumentujte případné incidenty (pro vlastní potřebu nebo právní ochranu)"
        ],
        cta: {
            heading: "Podpora je dostupná",
            description: "Individuální konzultace může nabídnout prostor pro zmapování situace a hledání bezpečných dalších kroků.",
            button: "Vyhledat pomoc"
        },
        showCrisis: true
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
        localStorage.setItem('quiz-toxicky-vztah-progress', JSON.stringify(progress));
    }

    loadProgress() {
        try {
            const saved = localStorage.getItem('quiz-toxicky-vztah-progress');
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
        localStorage.removeItem('quiz-toxicky-vztah-progress');
    }

    hasRedFlag() {
        return this.answers.some((optionIndex, questionIndex) => {
            if (optionIndex === null) return false;
            const option = quizConfig.questions[questionIndex]?.options[optionIndex];
            return option?.redFlag === true;
        });
    }

    getResult() {
        let result = { type: 'healthy', ...resultInterpretations.healthy };
        for (const key in resultInterpretations) {
            const band = resultInterpretations[key];
            if (this.score >= band.range[0] && this.score <= band.range[1]) {
                result = { type: key, ...band };
                break;
            }
        }

        // A single answer describing fear, aggression, control or isolation
        // must never get the reassuring result, whatever the total score.
        if (this.hasRedFlag()) {
            if (result.type === 'healthy') {
                result = { type: 'warning', ...resultInterpretations.warning };
            }
            result.showCrisis = true;
        }

        return result;
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
        this.quizForm = document.getElementById('quizForm');
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
        this.crisisHelp = document.getElementById('crisisHelp');
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

        if (this.quizForm) {
            this.quizForm.addEventListener('submit', (event) => event.preventDefault());
        }
    }

    checkResumeQuiz() {
        const saved = localStorage.getItem('quiz-toxicky-vztah-progress');
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

    renderQuestion() {
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

        // Scroll to top of quiz section (only on desktop or when changing questions)
        // Avoid scrolling on mobile after selecting an option
        const isMobile = window.innerWidth <= 768;
        if (!isMobile) {
            window.scrollTo({ top: this.quizSection.offsetTop - 100, behavior: 'smooth' });
        } else {
            // On mobile, scroll to keep question in view
            this.quizSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

        if (this.crisisHelp) {
            this.crisisHelp.hidden = result.showCrisis !== true;
        }

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
        // Odpovědi ani výsledek kvízu se neposílají do reklamních systémů.
    }
}

// Social Sharing Functions
function shareQuiz(platform) {
    const url = window.location.href;
    const text = encodeURIComponent('Test toxického vztahu - Může vám pomoci získat jasno ve vašem vztahu');

    let shareUrl = '';

    if (platform === 'facebook') {
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
    } else if (platform === 'twitter') {
        shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${text}`;
    }

    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=600');
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
        localStorage.removeItem('quiz-toxicky-vztah-progress');

        // Reload page to start fresh
        window.location.reload();
    }
}

// Initialize Quiz when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const quiz = new QuizController();
});
