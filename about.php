<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'About Us';
$pageDescription = 'Learn about ' . SITE_NAME . '\'s mission to help teachers across Southeast Asia save preparation time with ready-to-use resources and practical classroom guidance.';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="fw-bold mb-4">About <?= e(SITE_NAME) ?></h1>

            <p class="lead">Our mission is simple: help teachers save preparation time by providing ready-to-use teaching materials and practical classroom guidance, built for real classrooms rather than generic, mass-produced content.</p>

            <h2 class="h4 fw-bold mt-5 mb-3">The Problem We're Solving</h2>
            <p>Planning a lesson from scratch takes time most teachers don't have. Searching the internet for a worksheet or activity often turns up something generic, mistargeted at the wrong level, or missing the pieces a teacher actually needs &mdash; an answer key, clear instructions, or a sense of how long it will take in class. <?= e(SITE_NAME) ?> exists to shorten that search: resources organized by subject and grade, with enough context on each one to know whether it fits your lesson before you download it.</p>

            <h2 class="h4 fw-bold mt-5 mb-3">Who We're For</h2>
            <p><?= e(SITE_NAME) ?> is built for teachers across Southeast Asia &mdash; ESL/EFL teachers, primary school teachers, international and bilingual school teachers, private tutors, and homeschool educators working in international, bilingual, and English Program classrooms in Thailand and neighboring countries, and anywhere else in the world. We cover English/ESL for Kindergarten through Grade 10, and Math and Science for Grades 1&ndash;6.</p>

            <h2 class="h4 fw-bold mt-5 mb-3">What's on <?= e(SITE_NAME) ?></h2>
            <ul>
                <li>Downloadable resources &mdash; lesson plans, worksheets, PowerPoints, flashcards, games and assessments &mdash; organized by subject and grade level.</li>
                <li>The <a href="<?= e(base_url('teacher-hub.php')) ?>">Teacher Hub</a>, where we write practical how-to-teach guides: classroom activities, common student difficulties, differentiation ideas, and assessment suggestions for specific topics.</li>
                <li>A review system so teachers who've actually downloaded a resource can rate it and leave feedback for others.</li>
            </ul>

            <h2 class="h4 fw-bold mt-5 mb-3">How Resources Are Created and Reviewed</h2>
            <p>Each resource is added and checked before it's published: that it's correctly labeled by subject, grade, and type, that the files open and work as described, and that it includes what a teacher would need to use it in class (such as an answer key, where relevant). Where we know specific details about a resource &mdash; its learning objectives, how to use it, or ways to differentiate it &mdash; we add that directly to the resource page rather than leaving teachers to guess. We don't add that guidance where we don't genuinely know it.</p>
            <p>Beyond that initial check, the review system is our ongoing quality signal: teachers who've downloaded a resource can rate it and describe how it worked for them, which helps us see what to improve.</p>

            <h2 class="h4 fw-bold mt-5 mb-3">Save Time. Teach Better.</h2>
            <p>That's our promise: less time searching for or creating materials from scratch, more time actually teaching &mdash; and enjoying it.</p>

            <div class="text-center mt-5 d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-primary px-4">Explore Resources</a>
                <a href="<?= e(base_url('teacher-hub.php')) ?>" class="btn btn-outline-primary px-4">Visit the Teacher Hub</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
