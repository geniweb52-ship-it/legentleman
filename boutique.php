<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';

/** Lit un paramètre GET sous forme de liste de chaînes non vides. */
function get_list(string $key): array
{
    $value = $_GET[$key] ?? [];
    $value = is_array($value) ? $value : [$value];
    return array_values(array_unique(array_filter(array_map(fn($v) => trim((string) $v), $value), fn($v) => $v !== '')));
}

$search = trim((string) ($_GET['q'] ?? ''));
$cats = get_list('cat');
if (!empty($_GET['category']) && is_string($_GET['category'])) $cats[] = trim($_GET['category']); // ancien format d'URL
$cats = array_values(array_diff(array_unique($cats), ['tous']));
$sizesSel = get_list('size');
$colorsSel = get_list('color');
$sort = (string) ($_GET['sort'] ?? 'new');
$sort = in_array($sort, ['new', 'price_asc', 'price_desc', 'name'], true) ? $sort : 'new';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$bounds = $pdo->query('SELECT COALESCE(MIN(price),0) lo, COALESCE(MAX(price),0) hi FROM products WHERE active=1')->fetch();
$priceFloor = (int) (floor((int) $bounds['lo'] / 1000) * 1000);
$priceCeil = max($priceFloor + 1000, (int) (ceil((int) $bounds['hi'] / 1000) * 1000));
$min = (int) ($_GET['min'] ?? 0);
$max = (int) ($_GET['max'] ?? 0);
$min = $min > $priceFloor ? $min : 0;
$max = ($max > 0 && $max < $priceCeil) ? $max : 0;

$where = ['p.active=1'];
$params = [];
if ($cats) { $where[] = 'c.slug IN (' . implode(',', array_fill(0, count($cats), '?')) . ')'; array_push($params, ...$cats); }
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ? OR c.name LIKE ?)'; $like = '%' . $search . '%'; array_push($params, $like, $like, $like, $like); }
if ($sizesSel) {
    $placeholders = implode(',', array_fill(0, count($sizesSel), '?'));
    $giftOptions = implode(' OR ', array_fill(0, count($sizesSel), "FIND_IN_SET(?, REPLACE(gbc.sizes, ', ', ',')) > 0"));
    $where[] = "((p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets') AND EXISTS (SELECT 1 FROM variants fv WHERE fv.product_id=p.id AND fv.stock>0 AND fv.size IN ($placeholders))) OR ((p.is_gift_box=1 OR c.slug='coffrets') AND EXISTS (SELECT 1 FROM gift_box_text_components gbc WHERE gbc.gift_box_id=p.id AND ($giftOptions))))";
    array_push($params, ...$sizesSel, ...$sizesSel);
}
if ($colorsSel) {
    $placeholders = implode(',', array_fill(0, count($colorsSel), '?'));
    $giftOptions = implode(' OR ', array_fill(0, count($colorsSel), "FIND_IN_SET(?, REPLACE(gbc.colors, ', ', ',')) > 0"));
    $where[] = "((p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets') AND EXISTS (SELECT 1 FROM variants fv WHERE fv.product_id=p.id AND fv.stock>0 AND fv.color IN ($placeholders))) OR ((p.is_gift_box=1 OR c.slug='coffrets') AND EXISTS (SELECT 1 FROM gift_box_text_components gbc WHERE gbc.gift_box_id=p.id AND ($giftOptions))))";
    array_push($params, ...$colorsSel, ...$colorsSel);
}
if ($min > 0) { $where[] = 'p.price>=?'; $params[] = $min; }
if ($max > 0) { $where[] = 'p.price<=?'; $params[] = $max; }
$order = match ($sort) { 'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'name' => 'p.name ASC', default => 'p.created_at DESC, p.id DESC' };
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT p.*, c.name category_name, " . PRODUCT_STOCK_SQL . " stock_total FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT c.name, c.slug, COUNT(p.id) total FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.active=1 WHERE c.slug<>'tous' GROUP BY c.id, c.name, c.slug, c.sort_order ORDER BY c.sort_order")->fetchAll();
$catNames = array_column($categories, 'name', 'slug');
// Les déclinaisons techniques des coffrets (taille « Unique », couleur « Coffret ») ne sont pas des filtres pertinents :
// leurs vraies options viennent de leurs composants, ajoutés juste après.
$regularVariants = "FROM variants v JOIN products p ON p.id=v.product_id LEFT JOIN categories c ON c.id=p.category_id
  WHERE p.active=1 AND p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets')";
$sizes = $pdo->query("SELECT DISTINCT v.size $regularVariants")->fetchAll(PDO::FETCH_COLUMN);
$colors = $pdo->query("SELECT DISTINCT v.color $regularVariants")->fetchAll(PDO::FETCH_COLUMN);
foreach ($pdo->query("SELECT gbc.sizes,gbc.colors FROM gift_box_text_components gbc JOIN products p ON p.id=gbc.gift_box_id WHERE p.active=1")->fetchAll() as $componentOptions) {
    $sizes = array_merge($sizes, array_filter(array_map('trim', explode(',', (string) $componentOptions['sizes']))));
    $colors = array_merge($colors, array_filter(array_map('trim', explode(',', (string) $componentOptions['colors']))));
}
$sizes = sort_sizes(array_values(array_unique($sizes)));
$colors = array_values(array_unique($colors));
sort($colors);

$state = array_filter(['q' => $search, 'cat' => $cats, 'size' => $sizesSel, 'color' => $colorsSel, 'min' => $min ?: null, 'max' => $max ?: null, 'sort' => $sort !== 'new' ? $sort : null], fn($v) => $v !== null && $v !== '' && $v !== []);
function shop_url(array $state, array $changes = []): string
{
    $query = array_filter(array_merge($state, $changes), fn($v) => $v !== null && $v !== '' && $v !== []);
    return url('boutique.php' . ($query ? '?' . http_build_query($query) : ''));
}
$chips = [];
if ($search !== '') $chips[] = ['« ' . $search . ' »', shop_url($state, ['q' => null, 'page' => null])];
foreach ($cats as $slug) $chips[] = [$catNames[$slug] ?? $slug, shop_url($state, ['cat' => array_values(array_diff($cats, [$slug]))])];
foreach ($sizesSel as $s) $chips[] = ['Taille ' . $s, shop_url($state, ['size' => array_values(array_diff($sizesSel, [$s]))])];
foreach ($colorsSel as $c) $chips[] = [$c, shop_url($state, ['color' => array_values(array_diff($colorsSel, [$c]))])];
if ($min || $max) $chips[] = [money($min ?: $priceFloor) . ' – ' . money($max ?: $priceCeil), shop_url($state, ['min' => null, 'max' => null])];
$activeFilters = count($chips);

$isNewArrivals = $sort === 'new' && ($_GET['sort'] ?? '') === 'new' && !$cats && $search === '';
$title = match (true) {
    $search !== '' => 'Résultats pour « ' . $search . ' »',
    count($cats) === 1 => $catNames[$cats[0]] ?? 'Boutique',
    $isNewArrivals => 'Nouveautés',
    default => 'Boutique',
};
$pageTitle = $title;
require __DIR__ . '/includes/header.php';

$filterSection = function (string $title, string $content, bool $open = true): string {
    return '<details class="border-b border-line py-5" ' . ($open ? 'open' : '') . '><summary class="flex items-center justify-between font-semibold text-[15px]">' . e($title)
        . '<span class="chev transition-transform">' . icon('chevron-down', 'w-4 h-4') . '</span></summary><div class="pt-4">' . $content . '</div></details>';
};
?>
<main class="max-w-site mx-auto px-4 sm:px-6 pt-6">
  <?= breadcrumb($title === 'Boutique' ? [['Boutique', null]] : [['Boutique', url('boutique.php')], [$title, null]]) ?>
  <header class="mt-6 max-w-2xl">
    <h1 class="font-display text-4xl sm:text-5xl"><?= e($title) ?></h1>
    <p class="mt-3 text-muted"><?= $isNewArrivals ? 'Les dernières pièces arrivées en boutique.' : 'Découvrez notre sélection de vêtements, chaussures et accessoires.' ?></p>
  </header>

  <div class="mt-8 flex items-center justify-between gap-3">
    <button type="button" class="btn btn-primary !min-h-11 !px-5 lg:!hidden" data-open="filter-drawer"><?= icon('filter', 'w-4 h-4') ?> Filtrer<?= $activeFilters ? ' (' . $activeFilters . ')' : '' ?></button>
    <p class="hidden lg:block text-sm text-muted"><span class="font-semibold text-ink tabular-nums"><?= $total ?></span> produit<?= $total > 1 ? 's' : '' ?></p>
    <label class="relative inline-flex items-center gap-2 h-11 pl-4 pr-10 rounded-lg border border-line bg-white text-sm">
      <span class="text-muted whitespace-nowrap">Trier par :</span>
      <select name="sort" form="filters" class="appearance-none bg-transparent font-semibold outline-none cursor-pointer" data-autosubmit>
        <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Nouveautés</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nom (A–Z)</option>
      </select>
      <span class="absolute right-3 pointer-events-none"><?= icon('chevron-down', 'w-4 h-4') ?></span>
    </label>
  </div>

  <div class="mt-6 grid lg:grid-cols-[250px_1fr] gap-8 xl:gap-12 items-start">
    <!-- Filtres -->
    <div id="filter-drawer" class="filter-drawer lg:sticky lg:top-24">
      <div class="overlay absolute inset-0 bg-ink/40 lg:hidden" data-close></div>
      <form id="filters" method="get" action="<?= e(url('boutique.php')) ?>" class="drawer lg:!transform-none">
        <div class="drawer-head flex items-center justify-between px-5 h-16 border-b border-line sticky top-0 bg-cream z-10">
          <p class="font-display text-xl">Filtres</p>
          <button type="button" class="icon-btn -mr-2" data-close aria-label="Fermer les filtres"><?= icon('x', 'w-6 h-6') ?></button>
        </div>
        <div class="px-5 lg:px-0">
          <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
          <?php
          ob_start(); ?>
            <ul class="space-y-3">
              <?php foreach ($categories as $cat): ?>
                <li><label class="flex items-center gap-3 text-sm cursor-pointer hover:text-forest"><input type="checkbox" class="check" name="cat[]" value="<?= e($cat['slug']) ?>" <?= in_array($cat['slug'], $cats, true) ? 'checked' : '' ?>><span class="flex-1"><?= e($cat['name']) ?></span><span class="text-muted text-xs tabular-nums">(<?= (int) $cat['total'] ?>)</span></label></li>
              <?php endforeach; ?>
            </ul>
          <?php echo $filterSection('Catégories', (string) ob_get_clean());

          if ($sizes) {
              ob_start(); ?>
              <div class="flex flex-wrap gap-2">
                <?php foreach ($sizes as $s): ?>
                  <label><input type="checkbox" class="sr-only" name="size[]" value="<?= e($s) ?>" <?= in_array($s, $sizesSel, true) ? 'checked' : '' ?>><span class="size-chip !min-w-[2.9rem] !h-10 !text-[13px]"><?= e($s) ?></span></label>
                <?php endforeach; ?>
              </div>
              <?php echo $filterSection('Taille', (string) ob_get_clean());
          }

          if ($colors) {
              ob_start(); ?>
              <div class="flex flex-wrap gap-3">
                <?php foreach ($colors as $c): ?>
                  <label title="<?= e($c) ?>" class="cursor-pointer"><input type="checkbox" class="sr-only" name="color[]" value="<?= e($c) ?>" <?= in_array($c, $colorsSel, true) ? 'checked' : '' ?>><span class="swatch block" style="background:<?= e(color_background($c)) ?>"></span><span class="sr-only"><?= e($c) ?></span></label>
                <?php endforeach; ?>
              </div>
              <?php echo $filterSection('Couleur', (string) ob_get_clean());
          }

          ob_start(); ?>
            <div data-price-range data-floor="<?= $priceFloor ?>" data-ceil="<?= $priceCeil ?>">
              <div class="range-wrap">
                <div class="range-track"><div class="range-fill" data-fill></div></div>
                <input type="range" min="<?= $priceFloor ?>" max="<?= $priceCeil ?>" step="500" value="<?= $min ?: $priceFloor ?>" data-lo aria-label="Prix minimum">
                <input type="range" min="<?= $priceFloor ?>" max="<?= $priceCeil ?>" step="500" value="<?= $max ?: $priceCeil ?>" data-hi aria-label="Prix maximum">
              </div>
              <p class="mt-3 text-sm text-muted tabular-nums"><span data-lo-label><?= money($min ?: $priceFloor) ?></span> – <span data-hi-label><?= money($max ?: $priceCeil) ?></span></p>
              <input type="hidden" name="min" value="<?= $min ?: '' ?>" data-lo-input>
              <input type="hidden" name="max" value="<?= $max ?: '' ?>" data-hi-input>
            </div>
          <?php echo $filterSection('Prix', (string) ob_get_clean()); ?>

          <div class="sticky lg:static bottom-0 -mx-5 lg:mx-0 p-4 lg:p-0 lg:pt-6 bg-cream border-t border-line lg:border-0 flex gap-3">
            <?php if ($activeFilters): ?><a href="<?= e(url('boutique.php' . ($isNewArrivals ? '?sort=new' : ''))) ?>" class="btn btn-outline !min-h-11 !px-4 flex-1 lg:flex-none">Effacer</a><?php endif; ?>
            <button class="btn btn-primary !min-h-11 flex-1">Appliquer</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Résultats -->
    <section aria-label="Produits">
      <?php if ($chips): ?>
        <div class="flex flex-wrap items-center gap-2 mb-6">
          <?php foreach ($chips as [$label, $href]): ?>
            <a href="<?= e($href) ?>" class="inline-flex items-center gap-1.5 h-8 pl-3 pr-2 rounded-full bg-white border border-line text-xs font-medium hover:border-ink transition-colors"><?= e($label) ?><?= icon('x', 'w-3.5 h-3.5') ?></a>
          <?php endforeach; ?>
          <a href="<?= e(url('boutique.php')) ?>" class="text-xs underline underline-offset-2 text-muted hover:text-ink ml-1">Tout effacer</a>
        </div>
      <?php endif; ?>

      <?php if (!$products): ?>
        <div class="rounded-2xl border border-dashed border-line bg-white/60 py-16 px-6 text-center">
          <span class="mx-auto w-14 h-14 rounded-full bg-cream flex items-center justify-center text-muted"><?= icon('search', 'w-6 h-6') ?></span>
          <p class="mt-4 font-display text-2xl">Aucun produit trouvé</p>
          <p class="mt-2 text-muted text-sm">Essayez d'élargir vos filtres, ou demandez-nous directement sur WhatsApp.</p>
          <div class="mt-6 flex flex-wrap justify-center gap-3"><a href="<?= e(url('boutique.php')) ?>" class="btn btn-primary !min-h-11">Voir tous les produits</a><a href="<?= e(whatsapp_url('Bonjour, je cherche : ' . ($search ?: 'un article'))) ?>" target="_blank" rel="noopener" class="btn btn-outline !min-h-11"><?= icon('whatsapp', 'w-4 h-4') ?> Demander</a></div>
        </div>
      <?php else: ?>
        <p class="lg:hidden text-sm text-muted mb-4"><span class="font-semibold text-ink"><?= $total ?></span> produit<?= $total > 1 ? 's' : '' ?></p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-8 sm:gap-x-6">
          <?php foreach ($products as $product) echo product_card($product); ?>
        </div>

        <?php if ($pages > 1): ?>
          <nav class="mt-12 flex items-center justify-center gap-1.5" aria-label="Pagination">
            <?php if ($page > 1): ?><a href="<?= e(shop_url($state, ['page' => $page - 1])) ?>" class="icon-btn border border-line bg-white" aria-label="Page précédente"><?= icon('chevron-left', 'w-4 h-4') ?></a><?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): if ($i !== 1 && $i !== $pages && abs($i - $page) > 1) { if (abs($i - $page) === 2) echo '<span class="px-1 text-muted">…</span>'; continue; } ?>
              <a href="<?= e(shop_url($state, ['page' => $i > 1 ? $i : null])) ?>" class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-semibold tabular-nums transition-colors <?= $i === $page ? 'bg-forest text-white' : 'hover:bg-white border border-transparent hover:border-line' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="<?= e(shop_url($state, ['page' => $page + 1])) ?>" class="icon-btn border border-line bg-white" aria-label="Page suivante"><?= icon('chevron-right', 'w-4 h-4') ?></a><?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
