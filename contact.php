<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php'; // for validate_email_format()

$errors = [];
$submitted = false;
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Honeypot: a hidden field real users never fill in. Bots that
    // auto-fill every field will trip this and get a silent "success".
    $honeypot = trim((string)($_POST['website'] ?? ''));

    $old['name'] = clean_input($_POST['name'] ?? '');
    $old['email'] = clean_input($_POST['email'] ?? '');
    $old['subject'] = clean_input($_POST['subject'] ?? '');
    $old['message'] = clean_input($_POST['message'] ?? '');

    if ($honeypot === '') {
        $rateKey = 'contact_form:' . ($_SERVER['REMOTE_ADDR'] ?? '');

        if (too_many_attempts($rateKey, 5, 600)) {
            $errors['general'] = 'You\'ve sent several messages recently. Please wait a few minutes and try again.';
        } else {
            if ($old['name'] === '' || mb_strlen($old['name']) > 150) {
                $errors['name'] = 'Please enter your name.';
            }
            if ($old['email'] === '' || !validate_email_format($old['email'])) {
                $errors['email'] = 'Please enter a valid email address.';
            }
            if ($old['message'] === '') {
                $errors['message'] = 'Please enter a message.';
            }

            if (empty($errors)) {
                record_attempt($rateKey);

                getDB()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)')
                    ->execute([$old['name'], $old['email'], $old['subject'] !== '' ? $old['subject'] : null, $old['message']]);

                $submitted = true;
                $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
            }
        }
    } else {
        // Looked like a bot: pretend it worked, but don't store or send anything.
        $submitted = true;
    }
}

$pageTitle = 'Contact Us';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1 class="fw-bold mb-1">Contact Us</h1>
            <p class="text-secondary mb-4">Questions, feedback, or resource requests &mdash; we'd love to hear from you.</p>

            <?php if ($submitted): ?>
                <div class="alert alert-success">Thanks for reaching out! We'll get back to you soon.</div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(base_url('contact.php')) ?>" novalidate>
                <?php csrf_field(); ?>

                <div class="d-none" aria-hidden="true">
                    <label for="website">Leave this field blank</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                           id="name" name="name" value="<?= e($old['name']) ?>" required maxlength="150">
                    <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190">
                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="subject">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?= e($old['subject']) ?>" maxlength="200">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="message">Message</label>
                    <textarea class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                              id="message" name="message" rows="5" required><?= e($old['message']) ?></textarea>
                    <?php if (isset($errors['message'])): ?><div class="invalid-feedback"><?= e($errors['message']) ?></div><?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary px-4">Send Message</button>
            </form>

            <p class="text-secondary small mt-4 mb-0">
                Or email us directly at <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.
            </p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
