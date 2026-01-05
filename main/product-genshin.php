<?php
// JSON feed for Genshin products, using database values instead of static JS.
header('Content-Type: application/json');

require_once __DIR__ . '/../db/functions.php';

try {
    // Look up the Genshin category.
    $catStmt = $conn->prepare("
        SELECT id
        FROM categories
        WHERE slug = :slug AND is_active = 1
        LIMIT 1
    ");
    $catStmt->execute([':slug' => 'genshin-impact']);
    $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
    if (!$cat) {
        http_response_code(404);
        echo json_encode(['error' => 'Category not found.']);
        exit;
    }

    // Optional flags: check if columns exist so we don't break on older schemas.
    $hasPopular = false;
    $hasBest = false;
    $colStmt = $conn->prepare("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name IN ('is_popular','is_best_value')
    ");
    $colStmt->execute();
    foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
        if ($col === 'is_popular') $hasPopular = true;
        if ($col === 'is_best_value') $hasBest = true;
    }

    $sql = "
        SELECT p.id, p.product_name, p.price, p.description,
               p.product_image
               " . ($hasPopular ? ", p.is_popular" : "") . "
               " . ($hasBest ? ", p.is_best_value" : "") . "
        FROM products p
        WHERE p.category_id = :cid AND p.status = 'active'
        ORDER BY p.price ASC, p.id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':cid' => (int)$cat['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $packages = [];
    foreach ($rows as $row) {
        $packages[] = [
            'id'          => (int)$row['id'],
            'amount'      => $row['product_name'],
            'price'       => (float)$row['price'],
            'description' => $row['description'] ?? '',
            'image'       => $row['product_image'] ? ('../uploads/products/' . $row['product_image']) : null,
            'popular'     => $hasPopular ? (bool)$row['is_popular'] : false,
            'bestValue'   => $hasBest ? (bool)$row['is_best_value'] : false,
        ];
    }

    echo json_encode(['packages' => $packages], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
