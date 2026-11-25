<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$slugify = fn(string $s) => strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s), '-'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $action = (string) ($_POST['form_action'] ?? '');
    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 100);
    if ($action === 'add' && $name !== '') {
        $slug = $slugify($name) ?: 'categorie';
        $exists = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug=?'); $exists->execute([$slug]);
        if ($exists->fetchColumn()) $slug .= '-' . bin2hex(random_bytes(2));
        $order = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM categories')->fetchColumn();
        $pdo->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (?,?,?)')->execute([$name, $slug, $order]);
        flash('Catégorie « ' . $name . ' » créée.');
    }
    if ($action === 'save') {
        $st = $pdo->prepare("UPDATE categories SET name=?, sort_order=? WHERE id=? AND slug<>'tous'");
        foreach ((array) ($_POST['cat'] ?? []) as $id => $row) {
            $n = mb_substr(trim((string) ($row['name'] ?? '')), 0, 100);
            if ($n !== '') $st->execute([$n, (int) ($row['order'] ?? 0), (int) $id]);
        }
        flash('Catégories enregistrées.');
    }
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM categories WHERE id=? AND slug<>'tous'")->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Catégorie supprimée. Ses produits sont désormais « sans catégorie ».');
    }
    redirect('categories.php');
}
$cats = $pdo->query("SELECT c.*, COUNT(p.id) total, SUM(p.active) online,
    (SELECT image FROM products x WHERE x.category_id=c.id AND x.image IS NOT NULL AND x.image<>'' ORDER BY x.created_at DESC LIMIT 1) image
    FROM categories c LEFT JOIN products p ON p.category_id=c.id WHERE c.slug<>'tous' GROUP BY c.id ORDER BY c.sort_order, c.name")->fetchAll();
$pageTitle = 'Catégories';
$pageSubtitle = 'Organisez la boutique et le menu des filtres';
require __DIR__ . '/layout.php';
?>
<div class="grid xl:grid-cols-[1fr_340px] gap-5 items-start">
  <?= card_open('Catégories', 'list') ?>
    <form method="post" id="cats"><?= csrf_field() ?><input type="hidden" name="form_action" value="save"></form>
    <div class="overflow-x-auto -mx-5 sm:-mx-6">
      <table class="tbl w-full min-w-[620px]">
        <thead><tr><th class="!pl-5 sm:!pl-6">Ordre</th><th>Nom</th><th>Produits</th><th>Lien</th><th class="!pr-5 sm:!pr-6"></th></tr></thead>
        <tbody>
          <?php foreach ($cats as $c): ?>
            <tr>
              <td class="!pl-5 sm:!pl-6 w-24"><input form="cats" type="number" name="cat[<?= (int) $c['id'] ?>][order]" value="<?= (int) $c['sort_order'] ?>" class="field !h-9 !w-16 text-center"></td>
              <td><div class="flex items-center gap-3"><?= product_thumb($c['image'], 'w-10 h-10') ?><input form="cats" name="cat[<?= (int) $c['id'] ?>][name]" value="<?= e($c['name']) ?>" class="field !h-9 max-w-[240px] font-medium"></div></td>
              <td class="text-muted whitespace-nowrap"><?= (int) $c['total'] ?> <span class="text-xs">(<?= (int) $c['online'] ?> en ligne)</span></td>
              <td><a href="<?= e(url('boutique.php?cat[]=' . rawurlencode($c['slug']))) ?>" target="_blank" class="text-xs text-forest underline underline-offset-2">/<?= e($c['slug']) ?></a></td>
              <td class="!pr-5 sm:!pr-6 text-right"><form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="w-9 h-9 rounded-lg text-muted hover:text-terra hover:bg-terra/5 inline-flex items-center justify-center" data-confirm="Supprimer la catégorie « <?= e($c['name']) ?> » ? Ses <?= (int) $c['total'] ?> produits resteront en ligne, sans catégorie."><?= icon('trash', 'w-4 h-4') ?></button></form></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mt-5 flex justify-end"><button form="cats" class="btn btn-primary"><?= icon('check', 'w-4 h-4') ?> Enregistrer</button></div>
  </section>
  <?= card_open('Nouvelle catégorie', 'plus') ?>
    <form method="post" class="space-y-4"><?= csrf_field() ?><input type="hidden" name="form_action" value="add">
      <label class="block"><span class="label">Nom</span><input name="name" required maxlength="100" class="field" placeholder="Ex. T-shirts & Polos"></label>
      <button class="btn btn-primary w-full"><?= icon('plus', 'w-4 h-4') ?> Créer</button>
      <p class="text-xs text-muted">L'ordre détermine l'affichage sur l'accueil et dans les filtres. Les catégories sans produit sont masquées sur l'accueil.</p>
    </form>
  </section>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
