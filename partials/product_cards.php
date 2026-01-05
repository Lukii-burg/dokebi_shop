<?php
if (empty($products)) {
    echo '<div class="notice">No products found. Try another search or category.</div>';
    return;
}
$welcomePromo = $welcomePromo ?? (is_logged_in() ? get_welcome_promo($conn, current_user_id()) : null);
?>
<div id="productsGrid" class="product-grid-seagm">
  <?php foreach ($products as $p): ?>
    <?php
      $img = $p['product_image'] ?? '';
      $imgPath = $img ? '../uploads/products/'.$img : '../logo/original.png';
      $inWishlist = in_array($p['id'], $wishlistIds, true);
      $stats = $reviewStats[(int)$p['id']] ?? ['avg'=>0,'count'=>0];
      $myRev = $userReviews[(int)$p['id']] ?? null;
    ?>
    <div class="product-card-seagm">
      <div class="product-image-seagm">
        <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($p['product_name']); ?>">
      </div>
      <div class="product-info-seagm">
        <div class="product-title-seagm"><?php echo htmlspecialchars($p['product_name']); ?></div>
        <div class="product-meta-seagm"><?php echo htmlspecialchars($p['category_name']); ?></div>
        <p class="muted" style="min-height:48px;"><?php echo htmlspecialchars($p['description'] ?? ''); ?></p>
        <div class="card-row" style="align-items:flex-end;gap:0.8rem;flex-wrap:wrap;">
          <div class="price">
            <?php if (!empty($p['old_price'])): ?>
              <span class="original-price"><?php echo number_format($p['old_price'],2); ?> MMK</span>
            <?php endif; ?>
            <?php echo number_format($p['price'],2); ?> MMK
          </div>
          <form method="post" action="../user/buy.php" class="buy-form" style="display:flex;align-items:flex-end;gap:0.35rem;flex-wrap:wrap;">
            <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentShopUrl); ?>">
            <label class="muted tiny" for="qty_<?php echo (int)$p['id']; ?>">Qty</label>
            <select id="qty_<?php echo (int)$p['id']; ?>" name="qty">
              <?php for($i=1;$i<=10;$i++): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
              <?php endfor; ?>
            </select>
            <label class="muted tiny" for="pay_<?php echo (int)$p['id']; ?>">Pay with</label>
            <select id="pay_<?php echo (int)$p['id']; ?>" name="payment_method" class="payment-select">
              <option value="KBZPay">KBZPay</option>
              <option value="Wave Pay">Wave Pay</option>
              <option value="Aya Pay">Aya Pay</option>
              <option value="Visa/Mastercard">Visa/Mastercard</option>
              <option value="MPU">MPU</option>
            </select>
            <?php if ($welcomePromo && !$welcomePromo['used']): ?>
              <label class="muted tiny" for="promo_<?php echo (int)$p['id']; ?>">Promo code</label>
              <input type="text" id="promo_<?php echo (int)$p['id']; ?>" name="promo_code" value="<?php echo htmlspecialchars($welcomePromo['code']); ?>" placeholder="Enter promo code">
            <?php endif; ?>
            <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
              <button class="btn primary" type="submit">Buy</button>
              <button class="btn" type="submit" formaction="<?php echo url_for('user/cart.php'); ?>" formmethod="post">Add to Cart</button>
            </div>
          </form>
          <?php if (is_logged_in()): ?>
            <form method="post" action="../user/wishlist_toggle.php" style="display:inline;">
              <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
              <input type="hidden" name="redirect" value="../main/shop.php?<?php echo http_build_query($_GET); ?>">
              <input type="hidden" name="action" value="<?php echo $inWishlist ? 'remove' : 'add'; ?>">
              <button class="btn" type="submit" style="background:var(--panel-2);">
                <?php echo $inWishlist ? 'Saved ' : 'Add to Wishlist ?'; ?>
              </button>
            </form>
          <?php else: ?>
            <a class="btn" href="../auth/login.php">Login to save</a>
          <?php endif; ?>
        </div>
        <div class="muted tiny">
          <?php if($stats['count']>0): ?>
            Rating: <?php echo number_format($stats['avg'],1); ?>? (<?php echo $stats['count']; ?> reviews)
          <?php else: ?>
            Not rated yet
          <?php endif; ?>
        </div>
        <?php if (!empty($approvedReviews[(int)$p['id']])): ?>
        <div class="muted tiny">
          <strong>Recent reviews:</strong>
          <ul style="padding-left:16px; margin:4px 0; display:flex; flex-direction:column; gap:2px;">
            <?php foreach($approvedReviews[(int)$p['id']] as $ar): ?>
              <li><?php echo (int)$ar['rating']; ?>? - <?php echo htmlspecialchars($ar['comment']); ?> <span class="muted tiny">(<?php echo htmlspecialchars($ar['user_name'] ?? 'User'); ?>)</span></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <details class="review-box">
          <summary><?php echo $myRev ? 'Edit your review' : 'Write a review'; ?></summary>
          <?php if (is_logged_in()): ?>
            <form method="post" action="<?php echo url_for('user/review_submit.php'); ?>" style="display:flex;flex-direction:column;gap:0.35rem;">
              <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
              <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentShopUrl); ?>">
              <div class="rating-input" style="display:flex;gap:0.35rem;align-items:center;flex-wrap:wrap;">
                <span class="muted tiny">Your rating:</span>
                <?php for($r=5;$r>=1;$r--): ?>
                  <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="radio" name="rating" value="<?php echo $r; ?>" <?php echo ($myRev && (int)$myRev['rating']===$r)?'checked':''; ?> required>
                    <?php echo $r; ?>?
                  </label>
                <?php endfor; ?>
              </div>
              <textarea name="comment" rows="2" maxlength="500" placeholder="Share your experience (optional)" style="width:100%;"><?php echo htmlspecialchars($myRev['comment'] ?? ''); ?></textarea>
              <button class="btn primary" type="submit">Submit review</button>
            </form>
          <?php else: ?>
            <a class="btn" href="../auth/login.php">Login to write a review</a>
          <?php endif; ?>
        </details>
        <div class="muted tiny">Stock: <?php echo (int)$p['stock']; ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
