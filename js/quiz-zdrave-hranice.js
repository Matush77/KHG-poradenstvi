/* ==========================================
   KVÍZ: ZDRAVÉ OSOBNÍ HRANICE
   ========================================== */

const STORAGE_KEY = 'quiz-zdrave-hranice-progress';
const ANSWER_LABELS = ['Téměř nikdy', 'Spíše ne', 'Někdy', 'Spíše ano', 'Téměř vždy'];

const quizConfig = {
    questions: [
        { text: 'Když mě někdo požádá o něco, na co nemám čas nebo energii, dokážu odmítnout.', reversed: false },
        { text: 'Když řeknu „ne“, ještě dlouho přemýšlím, jestli jsem nebyl/a sobecký/á.', reversed: true },
        { text: 'Často souhlasím s něčím jen proto, abych druhého nezklamal/a.', reversed: true },
        { text: 'Dokážu říct, když je mi chování druhého nepříjemné.', reversed: false },
        { text: 'Když je někdo kvůli mému rozhodnutí zklamaný, mám potřebu své rozhodnutí změnit.', reversed: true },
        { text: 'Potřeby druhých často poznám dříve než svoje vlastní.', reversed: true },
        { text: 'Dokážu si vzít čas pro sebe, aniž bych měl/a pocit, že se za to musím omlouvat.', reversed: false },
        { text: 'Když někdo mou hranici nerespektuje, dokážu ji zopakovat a stát si za ní.', reversed: false },
        { text: 'Někdy něco dlouho toleruji a potom reaguji mnohem intenzivněji, než jsem původně chtěl/a.', reversed: true },
        { text: 'Mám pocit, že své „ne“ musím důkladně vysvětlit, aby bylo pro druhého přijatelné.', reversed: true },
        { text: 'Ve vztazích se dokážu rozhodovat také podle toho, co potřebuji já.', reversed: false },
        { text: 'Dokážu přijmout, že se na mě někdo může zlobit, aniž bych automaticky cítil/a, že jsem udělal/a něco špatně.', reversed: false }
    ]
};

const resultInterpretations = [
    {
        min: 38,
        max: 48,
        title: 'Vaše hranice jsou většinou pevné a pružné',
        icon: '✓',
        color: '#3f7652',
        paragraphs: [
            'Zdá se, že dokážete vnímat své potřeby a zároveň zůstávat v kontaktu s potřebami druhých. Umíte říct „ne“, vyjádřit, co Vám není příjemné, a poměrně dobře nesete i situace, kdy s Vaším rozhodnutím někdo nesouhlasí.',
            'To neznamená, že nikdy neustoupíte nebo nepochybujete. Zdravé hranice nejsou neprůstřelná zeď. Mohou se měnit podle situace, vztahu i toho, co právě potřebujete. Důležité je, že máte možnost volby – Vaše „ano“ i Vaše „ne“ mohou být skutečně Vaše.'
        ],
        recommendations: [
            'Všímejte si, ve kterých vztazích se Vám hranice drží snadno a kde je to obtížnější.',
            'Rozlišujte mezi vědomým kompromisem a automatickým ustoupením.',
            'Dovolte svým hranicím měnit se podle situace a Vaší aktuální kapacity.'
        ]
    },
    {
        min: 27,
        max: 37,
        title: 'Své hranice většinou vnímáte, ale někdy je těžké je udržet',
        icon: '○',
        color: '#2d5a7b',
        paragraphs: [
            'Pravděpodobně poměrně dobře poznáte, kdy Vám něco nevyhovuje nebo kdy už je toho na Vás příliš. Obtížnější může být hranici vyslovit nebo u ní zůstat ve chvíli, kdy druhý reaguje zklamáním, nesouhlasem nebo tlakem.',
            'Možná řeknete „ne“, ale vzápětí začnete pochybovat, vysvětlovat nebo hledat způsob, jak nepříjemný pocit rychle napravit. Nespokojenost druhého však automaticky neznamená, že Vaše hranice byla špatná.'
        ],
        recommendations: [
            'Předem si připravte jednu krátkou větu, kterou můžete v náročné situaci použít.',
            'Než odpovíte, dopřejte si čas slovy: „Potřebuji si to promyslet.“',
            'Když přijdou výčitky, oddělte nepříjemný pocit od hodnocení svého rozhodnutí.'
        ]
    },
    {
        min: 16,
        max: 26,
        title: 'Často dáváte přednost potřebám druhých',
        icon: '↔',
        color: '#9a712b',
        paragraphs: [
            'Ve vztazích můžete mít tendenci přizpůsobovat se, předcházet konfliktům nebo se starat o to, aby byli ostatní spokojení. Vaše vlastní potřeby se pak snadno dostávají na druhé místo.',
            'Říct „ne“ může vyvolávat výčitky, obavu z konfliktu nebo strach, že druhého zklamete. Možná proto raději souhlasíte – a až později si uvědomíte, že jste vlastně souhlasit nechtěli.'
        ],
        recommendations: [
            'Položte si otázku: „Říkám ano, protože opravdu chci, nebo protože se bojím říct ne?“',
            'Začněte jednou malou hranicí v situaci, která pro Vás není příliš ohrožující.',
            'Zkuste odmítnutí vyslovit bez dlouhého obhajování a všimněte si, co ve Vás následuje.'
        ]
    },
    {
        min: 0,
        max: 15,
        title: 'Vaše vlastní potřeby často zůstávají stranou',
        icon: '•',
        color: '#a65f55',
        paragraphs: [
            'Možná jste si zvykli být tím, kdo vydrží, pomůže, přizpůsobí se nebo ustoupí. Může být pro Vás obtížné poznat, co vlastně potřebujete Vy, natož to vyslovit před druhými. Představa, že někoho odmítnete nebo zklamete, může přinášet výrazné napětí či pocit viny.',
            'Dlouhodobě může takové fungování vést k vyčerpání, frustraci nebo pocitu, že ve vztazích není dost prostoru také pro Vás. Nemusíte začínat velkým „ne“. Prvním krokem může být všimnout si okamžiků, kdy automaticky souhlasíte, přestože uvnitř cítíte něco jiného.'
        ],
        recommendations: [
            'Jednou denně se zastavte a pojmenujte, co právě potřebujete Vy.',
            'Místo okamžitého souhlasu používejte větu: „Dám Vám vědět později.“',
            'Pokud je pro Vás nastavování hranic dlouhodobě velmi náročné, zvažte podporu terapeuta nebo jiné důvěryhodné osoby.'
        ]
    }
];

class BoundariesQuiz {
    constructor() {
        this.currentQuestion = 0;
        this.answers = new Array(quizConfig.questions.length).fill(null);
        this.loadProgress();
        this.getElements();
        this.bindEvents();
        this.offerResume();
        this.totalQuestions.textContent = quizConfig.questions.length;
    }

    getElements() {
        this.intro = document.querySelector('.quiz-intro');
        this.quizSection = document.getElementById('quizSection');
        this.resultsSection = document.getElementById('resultsSection');
        this.startButton = document.getElementById('startQuizBtn');
        this.questionText = document.getElementById('questionText');
        this.optionsContainer = document.getElementById('optionsContainer');
        this.previousButton = document.getElementById('prevBtn');
        this.nextButton = document.getElementById('nextBtn');
        this.currentQuestionText = document.getElementById('currentQuestion');
        this.totalQuestions = document.getElementById('totalQuestions');
        this.progressPercent = document.getElementById('progressPercent');
        this.progressFill = document.getElementById('progressFill');
        this.liveRegion = document.getElementById('liveRegion');
    }

    bindEvents() {
        this.startButton.addEventListener('click', () => this.start());
        this.previousButton.addEventListener('click', () => this.previous());
        this.nextButton.addEventListener('click', () => this.next());
    }

    offerResume() {
        if (this.currentQuestion > 0 && this.answers.some(answer => answer !== null)) {
            const resume = window.confirm('Chcete pokračovat v nedokončeném kvízu?');
            if (!resume) {
                this.clearProgress();
                this.currentQuestion = 0;
                this.answers.fill(null);
            }
        }
    }

    start() {
        this.track('quiz_started');
        this.intro.style.display = 'none';
        this.quizSection.style.display = 'block';
        this.renderQuestion(false);
        this.questionText.setAttribute('tabindex', '-1');
        this.questionText.focus();
    }

    scoreFor(question, optionIndex) {
        return question.reversed ? 4 - optionIndex : optionIndex;
    }

    getScore() {
        return this.answers.reduce((total, optionIndex, questionIndex) => {
            if (optionIndex === null) return total;
            return total + this.scoreFor(quizConfig.questions[questionIndex], optionIndex);
        }, 0);
    }

    renderQuestion(shouldScroll = true) {
        const question = quizConfig.questions[this.currentQuestion];
        this.questionText.textContent = question.text;
        this.optionsContainer.replaceChildren();

        ANSWER_LABELS.forEach((answerText, optionIndex) => {
            const label = document.createElement('label');
            label.className = 'quiz-option';
            if (this.answers[this.currentQuestion] === optionIndex) label.classList.add('selected');

            const input = document.createElement('input');
            input.type = 'radio';
            input.name = `question-${this.currentQuestion + 1}`;
            input.value = String(optionIndex);
            input.checked = this.answers[this.currentQuestion] === optionIndex;

            const text = document.createElement('span');
            text.className = 'quiz-option-label';
            text.textContent = answerText;

            input.addEventListener('change', () => this.selectAnswer(optionIndex));
            label.append(input, text);
            this.optionsContainer.appendChild(label);
        });

        this.updateProgress();
        this.previousButton.style.display = this.currentQuestion > 0 ? 'inline-block' : 'none';
        this.nextButton.textContent = this.currentQuestion === quizConfig.questions.length - 1 ? 'Zobrazit výsledek →' : 'Další →';
        this.nextButton.disabled = this.answers[this.currentQuestion] === null;
        this.liveRegion.textContent = `Otázka ${this.currentQuestion + 1} z ${quizConfig.questions.length}: ${question.text}`;

        if (shouldScroll) {
            this.quizSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            this.questionText.focus({ preventScroll: true });
        }
    }

    selectAnswer(optionIndex) {
        this.answers[this.currentQuestion] = optionIndex;
        this.saveProgress();
        this.optionsContainer.querySelectorAll('.quiz-option').forEach((option, index) => {
            option.classList.toggle('selected', index === optionIndex);
        });
        this.nextButton.disabled = false;
    }

    updateProgress() {
        const number = this.currentQuestion + 1;
        const percentage = Math.round((number / quizConfig.questions.length) * 100);
        this.currentQuestionText.textContent = number;
        this.progressPercent.textContent = percentage;
        this.progressFill.style.width = `${percentage}%`;
        this.progressFill.parentElement.setAttribute('aria-valuenow', percentage);
    }

    previous() {
        if (this.currentQuestion === 0) return;
        this.currentQuestion -= 1;
        this.saveProgress();
        this.renderQuestion();
    }

    next() {
        if (this.answers[this.currentQuestion] === null) return;
        if (this.currentQuestion < quizConfig.questions.length - 1) {
            this.currentQuestion += 1;
            this.saveProgress();
            this.renderQuestion();
            return;
        }
        this.showResult();
    }

    showResult() {
        const score = this.getScore();
        const result = resultInterpretations.find(item => score >= item.min && score <= item.max);
        this.track('quiz_completed');
        this.clearProgress();
        this.quizSection.style.display = 'none';
        this.resultsSection.style.display = 'block';

        const icon = document.getElementById('resultsIcon');
        const title = document.getElementById('resultsTitle');
        const description = document.getElementById('resultsDescription');
        const recommendations = document.getElementById('recommendationsList');

        icon.textContent = result.icon;
        icon.style.color = result.color;
        title.textContent = result.title;
        title.style.color = result.color;
        document.getElementById('resultsScore').textContent = score;

        description.replaceChildren();
        result.paragraphs.forEach(paragraph => {
            const element = document.createElement('p');
            element.textContent = paragraph;
            description.appendChild(element);
        });

        recommendations.replaceChildren();
        result.recommendations.forEach(recommendation => {
            const item = document.createElement('li');
            item.textContent = recommendation;
            recommendations.appendChild(item);
        });

        title.setAttribute('tabindex', '-1');
        title.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    saveProgress() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                currentQuestion: this.currentQuestion,
                answers: this.answers,
                timestamp: Date.now()
            }));
        } catch (error) {
            // Kvíz funguje i v prohlížeči, který lokální úložiště nepovoluje.
        }
    }

    loadProgress() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
            const isFresh = saved && Date.now() - saved.timestamp < 86400000;
            const hasValidAnswers = isFresh && Array.isArray(saved.answers) && saved.answers.length === quizConfig.questions.length;
            if (hasValidAnswers) {
                this.currentQuestion = Math.min(Math.max(Number(saved.currentQuestion) || 0, 0), quizConfig.questions.length - 1);
                this.answers = saved.answers.map(answer => Number.isInteger(answer) && answer >= 0 && answer <= 4 ? answer : null);
            }
        } catch (error) {
            this.clearProgress();
        }
    }

    clearProgress() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (error) {
            // Bez lokálního úložiště není co mazat.
        }
    }

    track(action) {
        if (typeof gtag === 'function') {
            gtag('event', action, { event_category: 'Quiz' });
        }
    }
}

function shareQuiz(platform) {
    const pageUrl = window.location.href;
    if (platform === 'facebook') {
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pageUrl)}`, '_blank', 'width=600,height=600,noopener');
    }
}

function copyQuizLink() {
    const pageUrl = window.location.href;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(pageUrl).then(
            () => showShareMessage('Odkaz byl zkopírován.'),
            () => fallbackCopyLink(pageUrl)
        );
    } else {
        fallbackCopyLink(pageUrl);
    }
}

function fallbackCopyLink(value) {
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showShareMessage('Odkaz byl zkopírován.');
    } catch (error) {
        showShareMessage('Odkaz se nepodařilo zkopírovat.');
    }
    textarea.remove();
}

function showShareMessage(message) {
    const element = document.getElementById('shareMessage');
    element.textContent = message;
    element.style.display = 'block';
    window.setTimeout(() => { element.style.display = 'none'; }, 3000);
}

function restartQuiz() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (error) {
        // Kvíz lze restartovat i bez přístupu k úložišti.
    }
    window.location.reload();
}

document.addEventListener('DOMContentLoaded', () => {
    new BoundariesQuiz();
});
