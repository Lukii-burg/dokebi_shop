<?php
// Shared catalog helpers for shop-related pages.
require_once __DIR__ . '/../db/functions.php';

function catalog_schema(PDO $conn): array {
    $tableExists = function(string $table) use ($conn): bool {
        try {
            $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    };
    $colExists = function(string $table, string $col) use ($conn): bool {
        try {
            $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
            $stmt->execute([$table, $col]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    };

    return [
        'has_main_table'  => $tableExists('main_categories'),
        'has_main_link'   => $colExists('categories', 'main_category_id'),
        'has_card_image'  => $colExists('categories', 'card_image'),
        'has_sort_order'  => $colExists('categories', 'sort_order'),
        'has_main_accent' => $colExists('main_categories', 'accent_color'),
        'has_cat_long'    => $colExists('categories', 'long_description'),
        'has_cat_faq'     => $colExists('categories', 'faq'),
        'has_cat_guide'   => $colExists('categories', 'guide'),
        'has_cat_video'   => $colExists('categories', 'video_url'),
    ];
}

function fetch_main_categories(PDO $conn, array $schema): array {
    if (empty($schema['has_main_table']) || empty($schema['has_main_link'])) {
        return [];
    }
    try {
        $stmt = $conn->query("
            SELECT mc.id, mc.name, mc.slug, mc.cover_image, mc.accent_color, mc.sort_order,
                   COUNT(DISTINCT c.id) AS sub_count,
                   COUNT(DISTINCT p.id) AS product_count
            FROM main_categories mc
            LEFT JOIN categories c ON c.main_category_id = mc.id AND c.is_active = 1
            LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
            WHERE mc.is_active = 1
            GROUP BY mc.id, mc.name, mc.slug, mc.cover_image, mc.accent_color, mc.sort_order
            ORDER BY mc.sort_order, mc.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function fetch_categories(PDO $conn, array $schema, ?int $selectedMainId = null): array {
    if (!empty($schema['has_main_link']) && !empty($schema['has_card_image'])) {
        $sql = "
            SELECT c.id, c.category_name, c.slug, c.card_image, c.main_category_id, c.sort_order,
                   mc.slug AS main_slug, mc.name AS main_name, mc.accent_color,
                   COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN main_categories mc ON mc.id = c.main_category_id
            LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
            WHERE c.is_active = 1
        ";
        $params = [];
        if ($selectedMainId !== null) {
            $sql .= " AND c.main_category_id = :main_id";
            $params[':main_id'] = $selectedMainId;
        }
        $sql .= "
            GROUP BY c.id, c.category_name, c.slug, c.card_image, c.main_category_id, c.sort_order, mc.slug, mc.name, mc.accent_color
            ORDER BY " . (!empty($schema['has_sort_order']) ? "COALESCE(c.sort_order,0)," : "") . " c.category_name
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fallback for legacy schema without main_categories/card_image
    return $conn->query("
        SELECT c.id, c.category_name, c.slug,
               '' AS card_image,
               NULL AS main_slug,
               NULL AS main_name,
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
        WHERE c.is_active = 1
        GROUP BY c.id, c.category_name, c.slug
        ORDER BY c.category_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function ensure_category_wishlist_table(PDO $conn): void {
    static $done = false;
    if ($done) return;
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS category_wishlists (
                user_id INT NOT NULL,
                category_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(user_id, category_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )
        ");
    } catch (Exception $e) {
        // ignore
    }
    $done = true;
}

/**
 * Fetch long-form content for a category with graceful fallbacks.
 */
function fetch_category_content(PDO $conn, array $category, array $schema): array {
    $content = [
        'long_description' => '',
        'faq'              => '',
        'guide'            => '',
        'video_url'        => ''
    ];
    if (!empty($category['id'])) {
        $fields = [];
        if (!empty($schema['has_cat_long']))  $fields[] = 'long_description';
        if (!empty($schema['has_cat_faq']))   $fields[] = 'faq';
        if (!empty($schema['has_cat_guide'])) $fields[] = 'guide';
        if (!empty($schema['has_cat_video'])) $fields[] = 'video_url';
        if ($fields) {
            $colList = implode(',', $fields);
            $stmt = $conn->prepare("SELECT $colList FROM categories WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$category['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $content = array_merge($content, array_filter($row, fn($v) => $v !== null));
            }
        }
    }
    // Fallback presets for known slugs to avoid empty content.
    $slug = $category['slug'] ?? '';
    if ($slug === 'genshin-impact') {
        if ($content['long_description'] === '') {
            $content['long_description'] = "Genshin Impact is a free-to-play action RPG across PS4, iOS, Android, and PC. Explore an open world with gacha mechanics, build your team, and conquer bosses and domains.\n\nGenesis Crystals are premium currency. Convert them to Primogems, then use Intertwined/Acquaint Fate to roll characters and weapons.";
        }
        if ($content['faq'] === '') {
            $content['faq'] = "- First top-up bonus applies only if you never recharged elsewhere.\n- Packs: 60 up to 12,960 Genesis Crystals.\n- Banners rotate heroes like Furina, Childe, Mona, Klee, Lumine, Aether.\n- Map is in-game with regions, landmarks, and points of interest.";
        }
        if ($content['guide'] === '') {
            $content['guide'] = "How to top-up:\n1) Select a Genesis Crystal pack.\n2) Enter UID and choose server.\n3) Pick payment method and checkout.\n4) Crystals are credited shortly.\n\nFind your UID: open your profile (top-left) and read UID at bottom-right.";
        }
    }
    return $content;
}
