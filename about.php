<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'About Us';
$pageDescription = 'Learn about ' . SITE_NAME . '\'s mission to help teachers across Southeast Asia save preparation time with ready-to-use resources and practical classroom guidance.';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero py-5">
    <div class="container py-3 text-center">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="display-6 fw-bold mb-3">About <?= e(SITE_NAME) ?></h1>
                <p class="lead mb-0" style="opacity:0.95;">Our mission is simple: help teachers save preparation time by providing ready-to-use teaching materials and practical classroom guidance, built for real classrooms rather than generic, mass-produced content.</p>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <div class="row justify-content-center g-4">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <i class="fa-solid fa-magnifying-glass fa-lg text-primary mt-1"></i>
                        <h2 class="h4 fw-bold mb-0">The Problem We're Solving</h2>
                    </div>
                    <p class="text-secondary mb-0">Planning a lesson from scratch takes time most teachers don't have. Searching the internet for a worksheet or activity often turns up something generic, mistargeted at the wrong level, or missing the pieces a teacher actually needs &mdash; an answer key, clear instructions, or a sense of how long it will take in class. <?= e(SITE_NAME) ?> exists to shorten that search: resources organized by subject and grade, with enough context on each one to know whether it fits your lesson before you download it.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <i class="fa-solid fa-users fa-lg text-primary mt-1"></i>
                        <h2 class="h4 fw-bold mb-0">Who We're For</h2>
                    </div>
                    <p class="text-secondary mb-0"><?= e(SITE_NAME) ?> is built for teachers across Southeast Asia &mdash; ESL/EFL teachers, primary school teachers, international and bilingual school teachers, private tutors, and homeschool educators working in international, bilingual, and English Program classrooms in Thailand and neighboring countries, and anywhere else in the world. We cover English/ESL for Kindergarten through Grade 10, and Math and Science for Grades 1&ndash;6.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <i class="fa-solid fa-layer-group fa-lg text-primary mt-1"></i>
                        <h2 class="h4 fw-bold mb-0">What's on <?= e(SITE_NAME) ?></h2>
                    </div>
                    <ul class="text-secondary mb-0 ps-3">
                        <li class="mb-2">Downloadable resources &mdash; lesson plans, worksheets, PowerPoints, flashcards, games and assessments &mdash; organized by subject and grade level.</li>
                        <li class="mb-2">The <a href="<?= e(base_url('teacher-hub.php')) ?>">Teacher Hub</a>, where we write practical how-to-teach guides: classroom activities, common student difficulties, differentiation ideas, and assessment suggestions for specific topics.</li>
                        <li>A review system so teachers who've actually downloaded a resource can rate it and leave feedback for others.</li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <i class="fa-solid fa-clipboard-check fa-lg text-primary mt-1"></i>
                        <h2 class="h4 fw-bold mb-0">How Resources Are Created and Reviewed</h2>
                    </div>
                    <p class="text-secondary">Each resource is added and checked before it's published: that it's correctly labeled by subject, grade, and type, that the files open and work as described, and that it includes what a teacher would need to use it in class (such as an answer key, where relevant). Where we know specific details about a resource &mdash; its learning objectives, how to use it, or ways to differentiate it &mdash; we add that directly to the resource page rather than leaving teachers to guess. We don't add that guidance where we don't genuinely know it.</p>
                    <p class="text-secondary mb-0">Beyond that initial check, the review system is our ongoing quality signal: teachers who've downloaded a resource can rate it and describe how it worked for them, which helps us see what to improve.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm text-center" style="background-color: var(--brand-bg-soft);">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h4 fw-bold mb-2">Save Time. Teach Better.</h2>
                    <p class="text-secondary mb-4">That's our promise: less time searching for or creating materials from scratch, more time actually teaching &mdash; and enjoying it.</p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mb-3">
                        <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-primary px-4">Explore Resources</a>
                        <a href="<?= e(base_url('teacher-hub.php')) ?>" class="btn btn-outline-primary px-4">Visit the Teacher Hub</a>
                    </div>
                    <p class="small text-secondary mb-0">Questions or feedback? <a href="<?= e(base_url('contact.php')) ?>">Contact us</a> &mdash; we'd love to hear from you.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
