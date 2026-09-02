// "Number Challenge" — a small, self-contained addition/subtraction quiz
// for the homepage Play & Learn section. No account, no server calls, no
// external libraries: every question and the final score are generated
// and computed entirely in the browser, so nothing here is ever fabricated
// data — it's a real, live result of what was actually answered.

document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('numberChallenge');
    if (!root) {
        return; // Not on this page — nothing to do.
    }

    var TOTAL_QUESTIONS = 10;

    var gameArea = document.getElementById('ncGameArea');
    var resultArea = document.getElementById('ncResult');
    var questionEl = document.getElementById('ncQuestion');
    var feedbackEl = document.getElementById('ncFeedback');
    var counterEl = document.getElementById('ncQuestionCounter');
    var scoreEl = document.getElementById('ncScore');
    var resultTextEl = document.getElementById('ncResultText');
    var answerButtons = Array.prototype.slice.call(root.querySelectorAll('.nc-answer-btn'));
    var playAgainBtn = document.getElementById('ncPlayAgain');

    var state = { index: 0, score: 0, answers: [], locked: false };

    function randomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    // Builds one addition or subtraction question with a correct answer
    // and two distinct, plausible wrong answers (never negative, never
    // duplicated), keeping the difficulty appropriate for young learners.
    function buildQuestion() {
        var isAddition = Math.random() < 0.5;
        var a, b, correct, text;

        if (isAddition) {
            a = randomInt(1, 20);
            b = randomInt(1, 20);
            correct = a + b;
            text = a + ' + ' + b + ' = ?';
        } else {
            a = randomInt(5, 20);
            b = randomInt(1, a);
            correct = a - b;
            text = a + ' − ' + b + ' = ?';
        }

        var choices = [correct];
        while (choices.length < 3) {
            var offset = randomInt(-5, 5);
            var candidate = correct + offset;
            if (candidate >= 0 && choices.indexOf(candidate) === -1) {
                choices.push(candidate);
            }
        }
        for (var i = choices.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = choices[i];
            choices[i] = choices[j];
            choices[j] = tmp;
        }

        return { text: text, correct: correct, choices: choices };
    }

    var questions = [];

    function renderQuestion() {
        var q = questions[state.index];
        questionEl.textContent = q.text;
        counterEl.textContent = 'Question ' + (state.index + 1) + '/' + TOTAL_QUESTIONS;
        feedbackEl.textContent = ' ';
        feedbackEl.className = 'text-center fw-bold mt-3 mb-0';
        state.locked = false;

        answerButtons.forEach(function (btn, i) {
            btn.textContent = q.choices[i];
            btn.disabled = false;
            btn.classList.remove('btn-success', 'btn-danger');
            btn.classList.add('btn-outline-primary');
        });
    }

    function startGame() {
        questions = [];
        for (var i = 0; i < TOTAL_QUESTIONS; i++) {
            questions.push(buildQuestion());
        }
        state.index = 0;
        state.score = 0;
        state.answers = [];
        scoreEl.textContent = '0';
        gameArea.classList.remove('d-none');
        resultArea.classList.add('d-none');
        renderQuestion();
    }

    function handleAnswer(btn) {
        if (state.locked) {
            return;
        }
        state.locked = true;

        var chosen = parseInt(btn.textContent, 10);
        var q = questions[state.index];
        var correctBtn = null;

        answerButtons.forEach(function (b) {
            b.disabled = true;
            if (parseInt(b.textContent, 10) === q.correct) {
                correctBtn = b;
            }
        });

        if (chosen === q.correct) {
            state.score++;
            scoreEl.textContent = String(state.score);
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');
            feedbackEl.textContent = 'Correct!';
            feedbackEl.classList.add('text-success');
        } else {
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-danger');
            if (correctBtn) {
                correctBtn.classList.remove('btn-outline-primary');
                correctBtn.classList.add('btn-success');
            }
            feedbackEl.textContent = 'Try again!';
            feedbackEl.classList.add('text-danger');
        }

        setTimeout(function () {
            state.index++;
            if (state.index < TOTAL_QUESTIONS) {
                renderQuestion();
            } else {
                finishGame();
            }
        }, 900);
    }

    function finishGame() {
        gameArea.classList.add('d-none');
        resultArea.classList.remove('d-none');
        resultTextEl.textContent = 'Great job! You scored ' + state.score + '/' + TOTAL_QUESTIONS + '.';
    }

    answerButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            handleAnswer(btn);
        });
    });

    if (playAgainBtn) {
        playAgainBtn.addEventListener('click', startGame);
    }

    startGame();
});
