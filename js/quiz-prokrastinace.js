/* ==========================================
   QUIZ JAVASCRIPT - PROKRASTINACE
   ========================================== */

// Quiz Configuration
const quizConfig = {
    questions: [
        {
            id: 1,
            text: "Co vás nejvíce brání začít s úkolem?",
            options: [
                { text: "Nic zvláštního, většinou začnu bez problémů", score: 0 },
                { text: "Strach, že to nebude dost dobré", score: 3 },
                { text: "Úkol se mi zdá příliš velký nebo složitý", score: 3 },
                { text: "Prostě se mi nechce, nemám energii", score: 3 }
            ]
        },
        {
            id: 2,
            text: "Jak často odkládáte i úkoly, které vás baví?",
            options: [
                { text: "Málokdy, úkoly které mě baví dělám rád/a", score: 0 },
                { text: "Občas, i s oblíbenými věcmi mám občas problém", score: 1 },
                { text: "Často, i když mě něco baví, odkládám to", score: 2 },
                { text: "Téměř vždy, odkládám všechno bez rozdílu", score: 3 }
            ]
        },
        {
            id: 3,
            text: "Jaký máte vztah k chybám a nedokonalosti?",
            options: [
                { text: "Chyby beru jako součást procesu učení", score: 0 },
                { text: "Trochu mě štve udělat chybu, ale zvládám to", score: 1 },
                { text: "Bojím se udělat chybu, snažím se jí vyhnout", score: 3 },
                { text: "Musí to být dokonalé, jinak raději nezačnu", score: 3 }
            ]
        },
        {
            id: 4,
            text: "Cítíte se často přetížení nebo unavení?",
            options: [
                { text: "Ne, mám dostatek energie", score: 0 },
                { text: "Občas, ale zvládám to", score: 1 },
                { text: "Ano, často se cítím vyčerpaný/-á", score: 3 },
                { text: "Téměř neustále, jsem na dně sil", score: 3 }
            ]
        },
        {
            id: 5,
            text: "Když přemýšlíte o úkolu, co cítíte?",
            options: [
                { text: "Klid nebo mírné nadšení", score: 0 },
                { text: "Lehkou nejistotu, ale zvládnutelnou", score: 1 },
                { text: "Úzkost, stres nebo odpor", score: 3 },
                { text: "Silný vnitřní tlak nebo vinu", score: 3 }
            ]
        },
        {
            id: 6,
            text: "Jak často čekáte na \"správnou chvíli\" nebo \"správnou náladu\"?",
            options: [
                { text: "Málokdy, začnu i když nemám ideální podmínky", score: 0 },
                { text: "Někdy, ale většinou se nenechám zastavit", score: 1 },
                { text: "Často, čekám až se budu cítit připravený/-á", score: 2 },
                { text: "Téměř vždy, bez správné nálady nemůžu začít", score: 3 }
            ]
        },
        {
            id: 7,
            text: "Jak se cítíte, když máte deadline blízko?",
            options: [
                { text: "V klidu, mám to pod kontrolou", score: 0 },
                { text: "Trochu ve stresu, ale funguje to", score: 1 },
                { text: "Ve velkém stresu, pracuji na poslední chvíli", score: 2 },
                { text: "V panice, je to můj jediný způsob, jak dokončit věci", score: 3 }
            ]
        },
        {
            id: 8,
            text: "Máte problém s dokončováním věcí?",
            options: [
                { text: "Ne, většinou dokončím, co začnu", score: 0 },
                { text: "Občas, ale většinu věcí dotáhnu", score: 1 },
                { text: "Ano, často přeskakuji mezi úkoly", score: 2 },
                { text: "Ano, mám mnoho rozpracovaných věcí", score: 3 }
            ]
        },
        {
            id: 9,
            text: "Jak velký vliv má na vás kritika nebo hodnocení druhých?",
            options: [
                { text: "Malý, spoléhám hlavně na sebe", score: 0 },
                { text: "Trochu mě ovlivňuje, ale nebrzdí mě to", score: 1 },
                { text: "Hodně, bojím se, co si o mně pomyslí", score: 3 },
                { text: "Velmi, strach z kritiky mě často zastavuje", score: 3 }
            ]
        },
        {
            id: 10,
            text: "Dělá vám větší problém začít nebo dokončit úkol?",
            options: [
                { text: "Ani jedno, zvládám obojí", score: 0 },
                { text: "Začátek je těžší, pak už to jde", score: 2 },
                { text: "Dokončení je problém, ztrácím zájem", score: 2 },
                { text: "Obojí je těžké, celý proces je boj", score: 3 }
            ]
        }
    ]
};

// Result Interpretations
const resultInterpretations = {
    minimal: {
        range: [0, 5],
        title: "Minimální prokrastinace",
        icon: "✓",
        color: "#4CAF50",
        description: "Gratuluji! Prokrastinace vás téměř neovlivňuje. Máte zdravý vztah k úkolům a většinou je zvládáte včas a bez zbytečného odkládání. Vaše strategie fungují dobře.",
        recommendations: [
            "Pokračujte v tom, co děláte – vaše přístupy fungují",
            "Můžete se stát inspirací pro ostatní – sdílejte své metody",
            "Buďte k sobě laskavý/-á i v případech, kdy něco přece jen odložíte",
            "Věnujte pozornost preventivní péči o duševní zdraví"
        ],
        cta: {
            heading: "Pokračujte ve svých dobrých návycích",
            description: "I když vám prokrastinace nedělá problémy, terapie může podpořit váš osobní růst a pomoci vám v dalších oblastech života.",
            button: "Zjistit více o terapii"
        },
        showCrisis: false
    },
    mild: {
        range: [6, 12],
        title: "Mírná prokrastinace",
        icon: "○",
        color: "#8BC34A",
        description: "Občas odkládáte úkoly, ale v celku si s tím poradíte. Prokrastinace vás ovlivňuje jen minimálně a většinou máte nástroje, jak ji překonat.",
        recommendations: [
            "Zmenšete začátek na nejmenší možný krok",
            "Pracujte v krátkých časových blocích (např. 10-2-10)",
            "Vytvořte si příjemné prostředí pro práci",
            "Oceňujte se za dokončení, ne za dokonalost"
        ],
        cta: {
            heading: "Posílejte své dovednosti pro zvládání úkolů",
            description: "Terapie vám může pomoci najít ještě lepší strategie a předejít tomu, aby se mírná prokrastinace prohlubovala.",
            button: "Promluvit si o tom"
        },
        showCrisis: false
    },
    moderate: {
        range: [13, 20],
        title: "Střední prokrastinace",
        icon: "!",
        color: "#FFC107",
        description: "Prokrastinace vás ovlivňuje častěji a více. Odkládání úkolů vám přináší stres, vinu nebo tlak. Je čas začít aktivně pracovat s tímto vzorcem.",
        recommendations: [
            "Identifikujte, jakou emoci úkol spouští (strach, stud, odpor?)",
            "Nastavte si \"dost dobré\" místo \"dokonalé\"",
            "Zkuste techniku micro-tasking – rozdělte úkol na nejmenší kroky",
            "Vytvořte si rutinu pro začínání úkolů",
            "Zvažte práci s terapeutem pro hlubší pochopení vzorců"
        ],
        cta: {
            heading: "Je čas aktivně pracovat s prokrastinací",
            description: "Terapie vám poskytne nástroje a podporu pro pochopení kořenů prokrastinace a změnu vzorců.",
            button: "Objednat konzultaci"
        },
        showCrisis: false
    },
    high: {
        range: [21, 30],
        title: "Výrazná prokrastinace",
        icon: "⚠",
        color: "#F44336",
        description: "Prokrastinace vás výrazně ovlivňuje v každodenním životě. Odkládání úkolů vám přináší značný stres, vinu a pravděpodobně ovlivňuje vaše vztahy, práci i osobní cíle.",
        recommendations: [
            "Vyhledejte odbornou pomoc – terapeut vám pomůže najít kořeny prokrastinace",
            "Začněte s nejmenšími možnými kroky (2 minuty)",
            "Buďte k sobě laskavý/-á – prokrastinace není lenost",
            "Zjistěte, zda nejste vyčerpaní – možná potřebujete odpočinek, ne disciplínu",
            "Vytvořte si podporu – mluvte o tom s důvěryhodnými lidmi",
            "Omezte multitasking a zaměřte se na jednu věc"
        ],
        cta: {
            heading: "Pomohu vám najít cestu ven z prokrastinace",
            description: "Prokrastinace často není o lennosti, ale o hlubších emočních vzorcích. Terapie vám poskytne bezpečný prostor a konkrétní nástroje.",
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
        localStorage.setItem('quiz-prokrastinace-progress', JSON.stringify(progress));
    }

    loadProgress() {
        try {
            const saved = localStorage.getItem('quiz-prokrastinace-progress');
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
        localStorage.removeItem('quiz-prokrastinace-progress');
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
        this.crisisResources = document.getElementById('crisisResources');
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
        const saved = localStorage.getItem('quiz-prokrastinace-progress');
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

        // Show/hide crisis resources
        if (result.showCrisis) {
            this.crisisResources.style.display = 'block';
        } else {
            this.crisisResources.style.display = 'none';
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
    const text = encodeURIComponent('Test prokrastinace - Může vám pomoci zjistit, co vás brzdí v plnění úkolů');

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
        localStorage.removeItem('quiz-prokrastinace-progress');

        // Reload page to start fresh
        window.location.reload();
    }
}

// Initialize Quiz when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const quiz = new QuizController();
});
