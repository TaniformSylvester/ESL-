<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/subject-functions.php';
require_once __DIR__ . '/../includes/request-functions.php';

require_admin();
$admin = current_user();

$filters = [
    'status'        => trim((string)($_GET['status'] ?? '')),
    'subject_id'    => (int)($_GET['subject_id'] ?? 0),
    'grade'         => trim((string)($_GET['grade'] ?? '')),
    'resource_type' => trim((string)($_GET['resource_type'] ?? '')),
    'difficulty'    => trim((string)($_GET['difficulty'] ?? '')),
    'date_from'     => trim((string)($_GET['date_from'] ?? '')),
    'date_to'       => trim((string)($_GET['date_to'] ?? '')),
    'search'        => trim((string)($_GET['search'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_all_requests_for_admin($filters, $page, ADMIN_ROWS_PER_PAGE);
$stats = get_request_stats();
$insights = get_request_insights();
$subjects = get_all_subjects();

$pageTitle = 'Resource Requests';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Total Requests</p>
            <p class="stat-value mb-0"><?= (int)$stats['total'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">New</p>
            <p class="stat-value mb-0 text-secondary"><?= (int)$stats['by_status']['new'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Planned</p>
            <p class="stat-value mb-0 text-primary"><?= (int)$stats['by_status']['planned'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">In Progress</p>
            <p class="stat-value mb-0 text-warning"><?= (int)$stats['by_status']['in_progress'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Published</p>
            <p class="stat-value mb-0 text-success"><?= (int)$stats['by_status']['published'] ?></p>
        </div></div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 stat-card h-100"><div class="card-body">
            <p class="text-secondary small text-uppercase mb-1">Reviewing</p>
            <p class="stat-value mb-0"><?= (int)$stats['by_status']['reviewing'] ?></p>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 fw-bold text-secondary text-uppercase mb-3">Resource Demand</h2>
        <div class="row g-3 small">
            <div class="col-6 col-md-2"><strong>This week:</strong> <?= (int)$insights['this_week'] ?></div>
            <div class="col-6 col-md-2"><strong>This month:</strong> <?= (int)$insights['this_month'] ?></div>
            <div class="col-6 col-md-2"><strong>Top subject:</strong> <?= $insights['top_subject'] ? e($insights['top_subject']) : e('Not enough data yet') ?></div>
            <div class="col-6 col-md-3"><strong>Top grade:</strong> <?= $insights['top_grade'] ? e($insights['top_grade']) : e('Not enough data yet') ?></div>
            <div class="col-6 col-md-3"><strong>Top type:</strong> <?= $insights['top_type'] ? e($insights['top_type']) : e('Not enough data yet') ?></div>
        </div>
    </div>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach (['' => 'All'] + REQUEST_STATUSES as $value => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filters['status'] === $value ? 'active' : '' ?>"
               href="<?= e(base_url('admin/requests.php?status=' . $value)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="<?= e(base_url('admin/requests.php')) ?>" class="row g-2 mb-3">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="Search topic, description, email, ID&hellip;" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-2">
        <select name="subject_id" class="form-select">
            <option value="">All Subjects</option>
            <?php foreach ($subjects as $subjectOption): ?>
                <option value="<?= (int)$subjectOption['id'] ?>" <?= $filters['subject_id'] === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="grade" class="form-select">
            <option value="">All Grades</option>
            <?php foreach (GRADE_LEVELS as $grade): ?>
                <option value="<?= e($grade) ?>" <?= $filters['grade'] === $grade ? 'selected' : '' ?>><?= e($grade) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="resource_type" class="form-select">
            <option value="">All Types</option>
            <?php foreach (RESOURCE_TYPES as $type): ?>
                <option value="<?= e($type) ?>" <?= $filters['resource_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
            <?php endforeach; ?>
            <option value="Other" <?= $filters['resource_type'] === 'Other' ? 'selected' : '' ?>>Other</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="difficulty" class="form-select">
            <option value="">All Difficulty</option>
            <?php foreach (REQUEST_DIFFICULTIES as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['difficulty'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-outline-secondary">Filter</button>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('admin/requests-export.php?' . http_build_query($filters))) ?>">
            <i class="fa-solid fa-file-csv me-1"></i>Export CSV
        </a>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Subject</th>
                    <th>Grade</th>
                    <th>Type</th>
                    <th class="text-end">Requests</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">No requests found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['items'] as $request): ?>
                    <?php
                        $statusBadge = match ($request['status']) {
                            'published' => 'bg-success',
                            'declined' => 'bg-danger',
                            'in_progress' => 'bg-warning text-dark',
                            'planned' => 'bg-primary',
                            'reviewing' => 'bg-info text-dark',
                            'duplicate' => 'bg-light text-dark border',
                            default => 'bg-secondary',
                        };
                    ?>
                    <tr>
                        <td class="small">
                            <a href="<?= e(base_url('admin/request-detail.php?id=' . (int)$request['id'])) ?>"><?= e($request['topic']) ?></a>
                        </td>
                        <td class="small"><?= e($request['subject_name'] ?? '') ?></td>
                        <td class="small"><?= e($request['grade_level'] ?? '') ?></td>
                        <td class="small"><?= e($request['resource_type'] === 'Other' ? ($request['resource_type_other'] ?: 'Other') : $request['resource_type']) ?></td>
                        <td class="text-end small"><?= (int)$request['demand_count'] ?></td>
                        <td><span class="badge <?= $statusBadge ?>"><?= e(REQUEST_STATUSES[$request['status']] ?? $request['status']) ?></span></td>
                        <td class="small text-secondary"><?= e(format_date($request['created_at'])) ?></td>
                        <td class="text-nowrap">
                            <a href="<?= e(base_url('admin/request-detail.php?id=' . (int)$request['id'])) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
