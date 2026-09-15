<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Free Classroom Tools — Random Name Picker & Timer';
$pageDescription = 'Free, no-login classroom tools for teachers: a random student name picker and a simple countdown timer, ready to use on any lesson.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold mb-2">Teacher Tools</h1>
        <p class="text-secondary mx-auto" style="max-width:600px;">Quick, free classroom tools &mdash; no account, no download, just open and use.</p>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100 teacher-tool-card">
                <div class="card-body p-4">
                    <h2 class="h4 fw-bold mb-1">Random Name Picker</h2>
                    <p class="text-secondary small mb-3">Paste your class list, then pick a student at random &mdash; great for cold-calling, turn-taking, or choosing who goes first.</p>

                    <div id="npSetup">
                        <label class="form-label small fw-bold" for="npNames">Class list (one name per line)</label>
                        <textarea id="npNames" class="form-control mb-2" rows="6" placeholder="Somchai&#10;Mai&#10;Nok&#10;Ploy&#10;..."></textarea>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="npNoRepeat" checked>
                            <label class="form-check-label small" for="npNoRepeat">Don't repeat a name until everyone's been picked</label>
                        </div>
                        <button type="button" class="btn btn-primary w-100" id="npStart">Save List &amp; Start Picking</button>
                    </div>

                    <div id="npPicker" class="d-none text-center">
                        <p class="small text-secondary mb-2"><span id="npRemainingCount">0</span> student<span id="npRemainingPlural">s</span> left in this round</p>
                        <div class="teacher-tool-display mb-3" id="npResult">&nbsp;</div>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-primary px-4" id="npPick">Pick a Student</button>
                            <button type="button" class="btn btn-outline-secondary" id="npResetRound">Reset Round</button>
                            <button type="button" class="btn btn-outline-secondary" id="npEditList">Edit List</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100 teacher-tool-card">
                <div class="card-body p-4">
                    <h2 class="h4 fw-bold mb-1">Classroom Timer</h2>
                    <p class="text-secondary small mb-3">A simple countdown for warm-ups, group work, or a quiet-reading block.</p>

                    <div class="teacher-tool-display mb-3" id="ctDisplay">05:00</div>
                    <div class="progress mb-3" style="height:8px;">
                        <div class="progress-bar" id="ctProgress" role="progressbar" style="width:100%"></div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary ct-preset" data-seconds="60">1 min</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ct-preset" data-seconds="180">3 min</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ct-preset" data-seconds="300">5 min</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ct-preset" data-seconds="600">10 min</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ct-preset" data-seconds="900">15 min</button>
                    </div>

                    <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                        <label class="small text-secondary mb-0" for="ctCustomMinutes">Custom:</label>
                        <input type="number" min="0" max="120" class="form-control form-control-sm" id="ctCustomMinutes" style="width:70px;" placeholder="min">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ctCustomSet">Set</button>
                    </div>

                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <button type="button" class="btn btn-primary px-4" id="ctStartPause">Start</button>
                        <button type="button" class="btn btn-outline-secondary" id="ctReset">Reset</button>
                    </div>
                    <p class="small text-secondary text-center mt-3 mb-0" id="ctStatus" aria-live="polite">&nbsp;</p>
                </div>
            </div>
        </div>
    </div>

    <p class="text-secondary text-center small mt-4 mb-0">Both tools run entirely in your browser &mdash; your class list is saved only on this device, never sent to our servers.</p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
