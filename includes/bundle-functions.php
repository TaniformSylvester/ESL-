<?php
/**
 * One-time-purchase resource bundles: admin CRUD, public listing/detail
 * data, and the access-grant check download-functions.php relies on.
 * Purchases themselves are recorded by stripe/webhook.php once Stripe
 * confirms payment — nothing here ever grants access without a real,
 * confirmed Stripe charge behind it.
 */

function bundle_slug_exists(string $slug, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = getDB()->prepare('SELECT id FROM bundles WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = getDB()->prepare('SELECT id FROM bundles WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }

    return (bool)$stmt->fetch();
}

function generate_unique_bundle_slug(string $title, ?int $excludeId = null): string
{
    $base = slugify($title);
    $base = $base !== '' ? $base : 'bundle';
    $slug = $base;
    $i = 2;

    while (bundle_slug_exists($slug, $excludeId)) {
        $slug = $base . '-' . $i;
        $i++;
    }

    return $slug;
}

/** Every bundle, newest first — for the admin list, published or not. */
function get_all_bundles_for_admin(): array
{
    return getDB()->query('SELECT * FROM bundles ORDER BY created_at DESC')->fetchAll();
}

/** Published bundles for the public listing page, with a live resource count. */
function get_published_bundles(): array
{
    return getDB()->query(
        "SELECT b.*, COUNT(br.resource_id) AS resource_count
         FROM bundles b
         LEFT JOIN bundle_resources br ON br.bundle_id = b.id
         WHERE b.is_published = 1
         GROUP BY b.id
         ORDER BY b.created_at DESC"
    )->fetchAll();
}

/**
 * The single bundle the homepage's "Featured Bundle" section shows, or null
 * if no published bundle is currently marked featured. Only ever one is
 * shown at a time (matching the homepage's one-hero-section design) — if an
 * admin marks more than one featured, the most recently updated wins.
 */
function get_featured_bundle(): ?array
{
    $stmt = getDB()->query(
        "SELECT b.*, COUNT(br.resource_id) AS resource_count
         FROM bundles b
         LEFT JOIN bundle_resources br ON br.bundle_id = b.id
         WHERE b.is_published = 1 AND b.is_featured = 1
         GROUP BY b.id
         ORDER BY b.updated_at DESC
         LIMIT 1"
    );
    $bundle = $stmt->fetch();

    return $bundle ?: null;
}

/** Public URL for a bundle's cover image (homepage feature, bundle cards), or null if none is set. */
function bundle_cover_image_url(array $bundle): ?string
{
    return !empty($bundle['cover_image']) ? UPLOAD_BUNDLE_URL . '/' . rawurlencode($bundle['cover_image']) : null;
}

/** A bundle's preview gallery images, in display order — the "browse before you buy" experience on the bundle page. */
function get_bundle_gallery_images(int $bundleId): array
{
    $stmt = getDB()->prepare('SELECT * FROM bundle_gallery_images WHERE bundle_id = ? ORDER BY sort_order, id');
    $stmt->execute([$bundleId]);

    return $stmt->fetchAll();
}

function get_bundle_gallery_image_by_id(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM bundle_gallery_images WHERE id = ?');
    $stmt->execute([$id]);
    $image = $stmt->fetch();

    return $image ?: null;
}

/** Removes one gallery image's file and DB row. Caller is responsible for authorization. */
function delete_bundle_gallery_image(int $id): void
{
    $image = get_bundle_gallery_image_by_id($id);
    if (!$image) {
        return;
    }

    getDB()->prepare('DELETE FROM bundle_gallery_images WHERE id = ?')->execute([$id]);
    @unlink(UPLOAD_BUNDLE_PATH . '/' . $image['image']);
}

/**
 * Uploads and attaches any gallery images present in $files['gallery_images']
 * (a standard PHP multi-file input array) to a bundle, continuing the sort
 * order after whatever images already exist. Skips (rather than fails on)
 * any individual file that isn't a valid image — one bad file shouldn't
 * block the rest of a bundle save. Returns an error message string, or null
 * if every provided file (if any) uploaded successfully.
 */
function add_bundle_gallery_images(int $bundleId, array $files): ?string
{
    if (empty($files['gallery_images']['name']) || !is_array($files['gallery_images']['name'])) {
        return null;
    }

    $count = count($files['gallery_images']['name']);
    $stmt = getDB()->prepare('INSERT INTO bundle_gallery_images (bundle_id, image, sort_order) VALUES (?, ?, ?)');

    $existing = getDB()->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM bundle_gallery_images WHERE bundle_id = ?');
    $existing->execute([$bundleId]);
    $nextOrder = (int)$existing->fetchColumn() + 1;

    $skipped = 0;
    for ($i = 0; $i < $count; $i++) {
        if (($files['gallery_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $file = [
            'name'     => $files['gallery_images']['name'][$i],
            'type'     => $files['gallery_images']['type'][$i],
            'tmp_name' => $files['gallery_images']['tmp_name'][$i],
            'error'    => $files['gallery_images']['error'][$i],
            'size'     => $files['gallery_images']['size'][$i],
        ];

        $upload = handle_upload($file, UPLOAD_BUNDLE_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);
        if (!$upload['success'] || $upload['filename'] === null) {
            $skipped++;
            continue;
        }

        $stmt->execute([$bundleId, $upload['filename'], $nextOrder]);
        $nextOrder++;
    }

    if ($skipped > 0) {
        return $skipped === 1
            ? 'One gallery image could not be uploaded (invalid type or too large) and was skipped.'
            : "{$skipped} gallery images could not be uploaded (invalid type or too large) and were skipped.";
    }

    return null;
}

function get_bundle_by_id(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM bundles WHERE id = ?');
    $stmt->execute([$id]);
    $bundle = $stmt->fetch();

    return $bundle ?: null;
}

/** Only ever used for the public bundle page, so only a published bundle is ever returned. */
function get_published_bundle_by_slug(string $slug): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM bundles WHERE slug = ? AND is_published = 1');
    $stmt->execute([$slug]);
    $bundle = $stmt->fetch();

    return $bundle ?: null;
}

/** The resources in a bundle, in sort order, joined with subject name for display. */
function get_bundle_resources(int $bundleId): array
{
    $stmt = getDB()->prepare(
        "SELECT r.*, s.name AS subject_name
         FROM bundle_resources br
         INNER JOIN resources r ON r.id = br.resource_id
         LEFT JOIN subjects s ON s.id = r.subject_id
         WHERE br.bundle_id = ?
         ORDER BY br.sort_order, r.title"
    );
    $stmt->execute([$bundleId]);

    return $stmt->fetchAll();
}

/** Bundle IDs only, for repopulating the admin form's multi-select on edit. */
function get_bundle_resource_ids(int $bundleId): array
{
    $stmt = getDB()->prepare('SELECT resource_id FROM bundle_resources WHERE bundle_id = ?');
    $stmt->execute([$bundleId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Published bundles that include this resource — used on resource.php to offer a bundle as an alternative to Pro. */
function get_published_bundles_containing_resource(int $resourceId): array
{
    $stmt = getDB()->prepare(
        "SELECT b.*
         FROM bundles b
         INNER JOIN bundle_resources br ON br.bundle_id = b.id
         WHERE br.resource_id = ? AND b.is_published = 1
         ORDER BY b.price ASC"
    );
    $stmt->execute([$resourceId]);

    return $stmt->fetchAll();
}

/**
 * Returns ['success' => bool, 'errors' => array<string,string>, 'id' => ?int, 'warning' => ?string]
 * $files is the raw $_FILES array — 'cover_image' (single) and
 * 'gallery_images' (multi) are read from it if present.
 */
function create_bundle(array $input, array $files = []): array
{
    $errors = validate_bundle_input($input);

    $uploadedCover = null;
    if (empty($errors) && !empty($files['cover_image']['name'])) {
        $upload = handle_upload($files['cover_image'], UPLOAD_BUNDLE_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);
        if (!$upload['success']) {
            $errors['cover_image'] = $upload['error'];
        } else {
            $uploadedCover = $upload;
        }
    }

    if (!empty($errors)) {
        if ($uploadedCover) {
            @unlink(UPLOAD_BUNDLE_PATH . '/' . $uploadedCover['filename']);
        }
        return ['success' => false, 'errors' => $errors, 'id' => null, 'warning' => null];
    }

    $title = clean_input($input['title']);
    $description = clean_input($input['description'] ?? '');
    $price = (float)$input['price'];
    $originalPrice = trim((string)($input['original_price'] ?? ''));
    $isPublished = !empty($input['is_published']) ? 1 : 0;
    $isFeatured = !empty($input['is_featured']) ? 1 : 0;

    $stmt = getDB()->prepare(
        'INSERT INTO bundles (title, slug, description, cover_image, price, original_price, is_published, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        generate_unique_bundle_slug($title),
        $description !== '' ? $description : null,
        $uploadedCover['filename'] ?? null,
        $price,
        $originalPrice !== '' ? (float)$originalPrice : null,
        $isPublished,
        $isFeatured,
    ]);

    $newId = (int)getDB()->lastInsertId();
    set_bundle_resources($newId, $input['resource_ids'] ?? []);
    $warning = add_bundle_gallery_images($newId, $files);

    return ['success' => true, 'errors' => [], 'id' => $newId, 'warning' => $warning];
}

/** Returns ['success' => bool, 'errors' => array<string,string>, 'warning' => ?string] */
function update_bundle(int $id, array $input, array $files = []): array
{
    $errors = validate_bundle_input($input);

    $uploadedCover = null;
    if (empty($errors) && !empty($files['cover_image']['name'])) {
        $upload = handle_upload($files['cover_image'], UPLOAD_BUNDLE_PATH, ALLOWED_IMAGE_MIME_TYPES, MAX_IMAGE_SIZE_BYTES);
        if (!$upload['success']) {
            $errors['cover_image'] = $upload['error'];
        } else {
            $uploadedCover = $upload;
        }
    }

    if (!empty($errors)) {
        if ($uploadedCover) {
            @unlink(UPLOAD_BUNDLE_PATH . '/' . $uploadedCover['filename']);
        }
        return ['success' => false, 'errors' => $errors, 'warning' => null];
    }

    $title = clean_input($input['title']);
    $description = clean_input($input['description'] ?? '');
    $price = (float)$input['price'];
    $originalPrice = trim((string)($input['original_price'] ?? ''));
    $isPublished = !empty($input['is_published']) ? 1 : 0;
    $isFeatured = !empty($input['is_featured']) ? 1 : 0;

    if ($uploadedCover) {
        $existing = get_bundle_by_id($id);
        if ($existing && !empty($existing['cover_image'])) {
            @unlink(UPLOAD_BUNDLE_PATH . '/' . $existing['cover_image']);
        }
    }

    $sql = 'UPDATE bundles SET title = ?, description = ?, price = ?, original_price = ?, is_published = ?, is_featured = ?'
         . ($uploadedCover ? ', cover_image = ?' : '')
         // slug is deliberately not regenerated on edit, same reasoning as
         // resources: a published bundle's URL may already be shared/paid for.
         . ' WHERE id = ?';

    $params = [
        $title,
        $description !== '' ? $description : null,
        $price,
        $originalPrice !== '' ? (float)$originalPrice : null,
        $isPublished,
        $isFeatured,
    ];
    if ($uploadedCover) {
        $params[] = $uploadedCover['filename'];
    }
    $params[] = $id;

    getDB()->prepare($sql)->execute($params);

    set_bundle_resources($id, $input['resource_ids'] ?? []);
    $warning = add_bundle_gallery_images($id, $files);

    return ['success' => true, 'errors' => [], 'warning' => $warning];
}

function validate_bundle_input(array $input): array
{
    $errors = [];

    $title = clean_input($input['title'] ?? '');
    if ($title === '' || mb_strlen($title) > 200) {
        $errors['title'] = 'Please enter a title.';
    }

    $price = $input['price'] ?? '';
    if ($price === '' || !is_numeric($price) || (float)$price <= 0 || (float)$price > 999999) {
        $errors['price'] = 'Please enter a valid price.';
    }

    $originalPrice = trim((string)($input['original_price'] ?? ''));
    if ($originalPrice !== '') {
        if (!is_numeric($originalPrice) || (float)$originalPrice > 999999) {
            $errors['original_price'] = 'Please enter a valid original price.';
        } elseif (is_numeric($price) && (float)$originalPrice <= (float)$price) {
            $errors['original_price'] = 'The original price must be higher than the current price for savings to make sense.';
        }
    }

    $resourceIds = array_filter(array_map('intval', $input['resource_ids'] ?? []));
    if (empty($resourceIds)) {
        $errors['resource_ids'] = 'Please select at least one resource for this bundle.';
    }

    return $errors;
}

function set_bundle_resources(int $bundleId, array $resourceIds): void
{
    $db = getDB();
    $db->prepare('DELETE FROM bundle_resources WHERE bundle_id = ?')->execute([$bundleId]);

    $stmt = $db->prepare('INSERT INTO bundle_resources (bundle_id, resource_id, sort_order) VALUES (?, ?, ?)');
    $order = 0;
    foreach ($resourceIds as $resourceId) {
        $resourceId = (int)$resourceId;
        if ($resourceId > 0) {
            $stmt->execute([$bundleId, $resourceId, $order]);
            $order++;
        }
    }
}

/**
 * Records a Stripe-confirmed bundle purchase — the automatic counterpart
 * called from stripe/webhook.php once Stripe confirms the charge actually
 * succeeded. Idempotent against Stripe's at-least-once webhook delivery,
 * same pattern as record_stripe_payment().
 */
function record_bundle_purchase(int $userId, int $bundleId, float $amount, string $sessionId): void
{
    $db = getDB();

    $existing = $db->prepare('SELECT id FROM bundle_purchases WHERE gateway_reference = ? LIMIT 1');
    $existing->execute([$sessionId]);
    if ($existing->fetchColumn()) {
        return;
    }

    $db->prepare(
        'INSERT INTO bundle_purchases (user_id, bundle_id, amount, currency, gateway_reference) VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $bundleId, $amount, CURRENCY, $sessionId]);
}

/** Whether this user has purchased any bundle that includes this resource — checked by can_download_resource(). */
function has_bundle_access(int $userId, int $resourceId): bool
{
    $stmt = getDB()->prepare(
        'SELECT bp.id
         FROM bundle_purchases bp
         INNER JOIN bundle_resources br ON br.bundle_id = bp.bundle_id
         WHERE bp.user_id = ? AND br.resource_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId, $resourceId]);

    return (bool)$stmt->fetch();
}

/** This user's purchased bundles, most recent first — for the My Bundles member page. */
function get_user_bundle_purchases(int $userId): array
{
    $stmt = getDB()->prepare(
        "SELECT bp.purchased_at, bp.amount, bp.currency, b.id AS bundle_id, b.title, b.slug
         FROM bundle_purchases bp
         INNER JOIN bundles b ON b.id = bp.bundle_id
         WHERE bp.user_id = ?
         ORDER BY bp.purchased_at DESC"
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/** Whether this user has already purchased this specific bundle — resource.php/bundle.php use this to avoid selling it twice. */
function has_purchased_bundle(int $userId, int $bundleId): bool
{
    $stmt = getDB()->prepare('SELECT id FROM bundle_purchases WHERE user_id = ? AND bundle_id = ? LIMIT 1');
    $stmt->execute([$userId, $bundleId]);

    return (bool)$stmt->fetch();
}
