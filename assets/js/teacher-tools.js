// Teacher Tools: Random Name Picker + Classroom Timer. Both run entirely
// client-side — no server round trip, no account, nothing saved except a
// teacher's own class list in this browser's localStorage.
(function () {
    'use strict';

    /* ---------------------------------------------------------------
       Random Name Picker
       ------------------------------------------------------------- */
    var npSetup = document.getElementById('npSetup');
    var npPicker = document.getElementById('npPicker');
    if (npSetup && npPicker) {
        var npNamesInput = document.getElementById('npNames');
        var npNoRepeat = document.getElementById('npNoRepeat');
        var npStartBtn = document.getElementById('npStart');
        var npPickBtn = document.getElementById('npPick');
        var npResetRoundBtn = document.getElementById('npResetRound');
        var npEditListBtn = document.getElementById('npEditList');
        var npResult = document.getElementById('npResult');
        var npRemainingCount = document.getElementById('npRemainingCount');
        var npRemainingPlural = document.getElementById('npRemainingPlural');

        var STORAGE_KEY = 'teachluma_name_picker_list';
        var fullList = [];
        var remaining = [];
        var isPicking = false;

        function parseNames(text) {
            return text.split('\n').map(function (n) { return n.trim(); }).filter(function (n) { return n !== ''; });
        }

        function saveList(names) {
            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(names));
            } catch (e) { /* localStorage unavailable — list just won't persist */ }
        }

        function loadSavedList() {
            try {
                var raw = window.localStorage.getItem(STORAGE_KEY);
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        }

        function updateRemainingLabel() {
            npRemainingCount.textContent = String(remaining.length);
            npRemainingPlural.textContent = remaining.length === 1 ? '' : 's';
        }

        function showPicker(names) {
            fullList = names.slice();
            remaining = names.slice();
            npResult.textContent = ' ';
            updateRemainingLabel();
            npSetup.classList.add('d-none');
            npPicker.classList.remove('d-none');
        }

        npStartBtn.addEventListener('click', function () {
            var names = parseNames(npNamesInput.value);
            if (names.length < 1) {
                npNamesInput.classList.add('is-invalid');
                return;
            }
            npNamesInput.classList.remove('is-invalid');
            saveList(names);
            showPicker(names);
        });

        npEditListBtn.addEventListener('click', function () {
            npNamesInput.value = fullList.join('\n');
            npPicker.classList.add('d-none');
            npSetup.classList.remove('d-none');
        });

        npResetRoundBtn.addEventListener('click', function () {
            remaining = fullList.slice();
            npResult.textContent = ' ';
            updateRemainingLabel();
        });

        npPickBtn.addEventListener('click', function () {
            if (isPicking) { return; }

            var pool = npNoRepeat.checked ? remaining : fullList;
            if (pool.length === 0) {
                // Everyone's been picked this round — start a fresh round automatically.
                remaining = fullList.slice();
                pool = remaining;
            }
            if (pool.length === 0) { return; }

            isPicking = true;
            npPickBtn.disabled = true;

            var ticks = 12;
            var tick = 0;
            var shuffleInterval = window.setInterval(function () {
                var randomName = pool[Math.floor(Math.random() * pool.length)];
                npResult.textContent = randomName;
                tick++;
                if (tick >= ticks) {
                    window.clearInterval(shuffleInterval);
                    var finalIndex = Math.floor(Math.random() * pool.length);
                    var finalName = pool[finalIndex];
                    npResult.textContent = finalName;

                    if (npNoRepeat.checked) {
                        var idx = remaining.indexOf(finalName);
                        if (idx !== -1) { remaining.splice(idx, 1); }
                        updateRemainingLabel();
                    }

                    isPicking = false;
                    npPickBtn.disabled = false;
                }
            }, 70);
        });

        var saved = loadSavedList();
        if (saved && saved.length > 0) {
            npNamesInput.value = saved.join('\n');
            showPicker(saved);
        }
    }

    /* ---------------------------------------------------------------
       Classroom Timer
       ------------------------------------------------------------- */
    var ctDisplay = document.getElementById('ctDisplay');
    if (ctDisplay) {
        var ctProgress = document.getElementById('ctProgress');
        var ctStartPauseBtn = document.getElementById('ctStartPause');
        var ctResetBtn = document.getElementById('ctReset');
        var ctStatus = document.getElementById('ctStatus');
        var ctCustomMinutes = document.getElementById('ctCustomMinutes');
        var ctCustomSetBtn = document.getElementById('ctCustomSet');
        var ctPresetButtons = document.querySelectorAll('.ct-preset');

        var DEFAULT_SECONDS = 300;
        var totalSeconds = DEFAULT_SECONDS;
        var remainingSeconds = DEFAULT_SECONDS;
        var intervalId = null;
        var isRunning = false;

        function formatTime(seconds) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        function render() {
            ctDisplay.textContent = formatTime(remainingSeconds);
            var pct = totalSeconds > 0 ? (remainingSeconds / totalSeconds) * 100 : 0;
            ctProgress.style.width = pct + '%';
        }

        function playBeep() {
            try {
                var AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) { return; }
                var ctx = new AudioCtx();
                var oscillator = ctx.createOscillator();
                var gain = ctx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 880;
                gain.gain.value = 0.15;
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.start();
                oscillator.stop(ctx.currentTime + 0.6);
                oscillator.onended = function () { ctx.close(); };
            } catch (e) { /* Web Audio unavailable — visual alert still shows */ }
        }

        function stopInterval() {
            if (intervalId !== null) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
            isRunning = false;
            ctStartPauseBtn.textContent = 'Start';
        }

        function setDuration(seconds) {
            stopInterval();
            totalSeconds = seconds;
            remainingSeconds = seconds;
            ctDisplay.classList.remove('teacher-tool-display-alert');
            ctStatus.textContent = ' ';
            render();
        }

        ctPresetButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setDuration(parseInt(btn.getAttribute('data-seconds'), 10));
            });
        });

        ctCustomSetBtn.addEventListener('click', function () {
            var minutes = parseInt(ctCustomMinutes.value, 10);
            if (isNaN(minutes) || minutes <= 0) { return; }
            setDuration(Math.min(minutes, 120) * 60);
        });

        ctStartPauseBtn.addEventListener('click', function () {
            if (isRunning) {
                stopInterval();
                return;
            }
            if (remainingSeconds <= 0) { return; }

            isRunning = true;
            ctStartPauseBtn.textContent = 'Pause';
            ctDisplay.classList.remove('teacher-tool-display-alert');
            ctStatus.textContent = ' ';

            intervalId = window.setInterval(function () {
                remainingSeconds--;
                render();
                if (remainingSeconds <= 0) {
                    stopInterval();
                    ctDisplay.classList.add('teacher-tool-display-alert');
                    ctStatus.textContent = "Time's up!";
                    playBeep();
                }
            }, 1000);
        });

        ctResetBtn.addEventListener('click', function () {
            setDuration(totalSeconds);
        });

        render();
    }
})();
