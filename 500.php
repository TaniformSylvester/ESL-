<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Something Went Wrong';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container text-center py-5 my-5">
    <h1 class="display-3 fw-bold text-danger">500</h1>
    <p class="lead">Something went wrong on our end. Please try again shortly.</p>
    <a href="<?= e(base_url()) ?>" class="btn btn-primary mt-3">Go to Homepage</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
