<?php
$categoryTileData = $categoriesForTiles ?? $categories ?? [];
if (empty($categoryTileData)) {
    return;
}
?>
<div class="category-tiles">
  <?php foreach ($categoryTileData as $cat):
    $accent = $cat['accent_color'] ?? '#0ea5e9';
    $imgPath = ($cat['card_image'] ?? '') ? '../uploads/categories/'.$cat['card_image'] : '';
    $bgLayers = $imgPath
      ? "url('".htmlspecialchars($imgPath, ENT_QUOTES)."')"
      : "linear-gradient(135deg, #f3f4f6, #e5e7eb)";
    $coverParts = [
      "--accent:$accent",
      "background-image:$bgLayers",
      "background-color:#f3f4f6",
      "background-size:cover",
      "background-position:center"
    ];
    $coverStyle = implode(';', $coverParts) . ';';
    $slug = $cat['slug'] ?? '';
    $pageMap = [
      'mlbb-diamonds'     => 'mlbb.php',
      'pubg-uc'           => 'pubg.php',
      'freefire-diamonds' => 'freefire.php',
      'premium-accounts'  => 'premium.php',
      'genshin-impact'    => 'genshin.php',
      'valorant-points'   => 'valorant.php',
      'mm-topup'          => 'mmtopup.php',
      'gift-cards'        => 'giftcards.php',
      'steam-wallet'             => 'giftcards.php',
      'google-play-gift-cards'   => 'giftcards.php',
      'app-store-gift-cards'     => 'giftcards.php',
      'mpt-topup'                => 'topup.php',
      'u9-topup'                 => 'topup.php',
      'atom-topup'               => 'topup.php',
      'mytel-topup'              => 'topup.php',
      'spotify-premium'          => 'topup.php',
      'youtube-premium'          => 'topup.php',
      'netflix-premium'          => 'topup.php',
      'telegram-premium'         => 'topup.php',
      'chatgpt-premium'          => 'topup.php',
    ];
    $page = $pageMap[$slug] ?? 'topup.php';
    $catLink = $page . '?slug=' . urlencode($slug);
    $isActiveCat = ($cat['slug'] ?? '') === ($categorySlug ?? '');
  ?>
    <a class="category-tile <?php echo $isActiveCat ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($catLink); ?>">
      <div class="category-tile__cover" style="<?php echo $coverStyle; ?>"></div>
      <div class="category-tile__body">
        <div class="category-tile__meta">
          <span class="pill-dot" style="background: <?php echo htmlspecialchars($accent); ?>;"></span>
          <span class="muted tiny"><?php echo htmlspecialchars($cat['main_name'] ?? ''); ?></span>
        </div>
        <div class="category-tile__title"><?php echo htmlspecialchars($cat['category_name'] ?? ''); ?></div>
        <div class="category-tile__count"><?php echo (int)($cat['product_count'] ?? 0); ?> items</div>
      </div>
    </a>
  <?php endforeach; ?>
</div>
