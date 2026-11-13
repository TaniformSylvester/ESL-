<?php
/**
 * One-time first-admin setup. This intentionally refuses to run at all
 * once any admin account already exists, so it can never act as a
 * permanent backdoor — and it never ships with a default password of
 * any kind, since you choose your own here.
 *
 * >>> DELETE THIS /install/ FOLDER FROM YOUR SERVER as soon as you've
 *     created your admin account. <<<
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

$stmt = getDB()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminExists = (int)$stmt->fetchColumn() > 0;

$errors = [];
$success = false;
$old = ['first_name' => '', 'last_name' => '', 'email' => ''];

if (!$adminExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $firstName = clean_input($_POST['first_name'] ?? '');
    $lastName = clean_input($_POST['last_name'] ?? '');
    $email = strtolower(clean_input($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($firstName === '' || mb_strlen($firstName) > 100) {
        $errors['first_name'] = 'Please enter a first name.';
    }
    if ($lastName === '' || mb_strlen($lastName) > 100) {
        $errors['last_name'] = 'Please enter a last name.';
    }
    if (!validate_email_format($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (email_exists($email)) {
        $errors['email'] = 'An account with this email already exists.';
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters, with a letter and a number.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    $old = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];

    if (empty($errors)) {
        $db = getDB();
        $db->beginTransaction();

        try {
            $db->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
                ->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);

            $userId = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO memberships (user_id, status) VALUES (?, 'active')")
                ->execute([$userId]);

            $db->commit();
            $success = true;
            $adminExists = true;
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('create-admin.php failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Create Admin Account';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-user-shield me-1"></i> Create Admin Account</h1>

                    <?php if ($success): ?>
                        <div class="alert alert-success mt-3">
                            Admin account created successfully!
                        </div>
                        <p class="fw-bold text-danger">For security, please delete the /install/ folder from your server now.</p>
                        <a href="<?= e(base_url('admin/login.php')) ?>" class="btn btn-primary">Go to Admin Login</a>
                    <?php elseif ($adminExists): ?>
                        <div class="alert alert-warning mt-3">
                            An admin account already exists. This one-time setup page can't be used again.
                        </div>
                        <p class="fw-bold text-danger">For security, please delete the /install/ folder from your server.</p>
                        <a href="<?= e(base_url('admin/login.php')) ?>" class="btn btn-primary">Go to Admin Login</a>
                    <?php else: ?>
                        <p class="text-secondary mb-4">This runs once. Choose your own admin email and password below — nothing here is pre-filled or guessable.</p>

                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(base_url('install/create-admin.php')) ?>" novalidate>
                            <?php csrf_field(); ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="first_name">First Name</label>
                                    <input type="text" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                                           id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required maxlength="100">
                                    <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="last_name">Last Name</label>
                                    <input type="text" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                                           id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required maxlength="100">
                                    <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="email">Email</label>
                                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                           id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190">
                                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Password</label>
                                    <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                           id="password" name="password" required minlength="<?= (int)PASSWORD_MIN_LENGTH ?>">
                                    <div class="form-text">At least <?= (int)PASSWORD_MIN_LENGTH ?> characters, with a letter and a number.</div>
                                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="confirm_password">Confirm Password</label>
                                    <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                           id="confirm_password" name="confirm_password" required>
                                    <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div><?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-4 py-2">Create Admin Account</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
