<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $action = (string) ($_POST['form_action'] ?? '');
    // Deux zones ne peuvent pas porter le même nom (sinon le client voit des doublons au moment de commander).
    $isDuplicate = fn(PDOException $e) => ($e->errorInfo[1] ?? 0) === 1062;
    if ($action === 'add') {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        if ($name !== '') {
            try {
                $pdo->prepare('INSERT INTO delivery_zones (name, fee, active) VALUES (?,?,1)')->execute([$name, max(0, (int) ($_POST['fee'] ?? 0))]);
                flash('Zone « ' . $name . ' » ajoutée.');
            } catch (PDOException $exception) {
                if (!$isDuplicate($exception)) throw $exception;
                flash('Une zone « ' . $name . ' » existe déjà : modifiez son tarif dans la liste.', 'error');
            }
        }
    }
    if ($action === 'save') {
        $st = $pdo->prepare('UPDATE delivery_zones SET name=?, fee=?, active=? WHERE id=?');
        $duplicates = [];
        foreach ((array) ($_POST['zone'] ?? []) as $id => $z) {
            $n = mb_substr(trim((string) ($z['name'] ?? '')), 0, 120);
            if ($n === '') continue;
            try {
                $st->execute([$n, max(0, (int) ($z['fee'] ?? 0)), (int) !empty($z['active']), (int) $id]);
            } catch (PDOException $exception) {
                if (!$isDuplicate($exception)) throw $exception;
                $duplicates[] = $n;
            }
        }
        if ($duplicates) flash('Nom déjà utilisé par une autre zone : ' . implode(', ', $duplicates) . '. Les autres modifications sont enregistrées.', 'error');
        else flash('Zones de livraison enregistrées.');
    }
    redirect('livraison.php');
}
$zones = $pdo->query("SELECT z.*, COUNT(o.id) orders_count FROM delivery_zones z LEFT JOIN orders o ON o.delivery_zone_id=z.id AND o.status<>'annulee' GROUP BY z.id ORDER BY z.fee, z.name")->fetchAll();
$shipping = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='expediee'")->fetchColumn();
$toShip = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('payee','en_preparation') OR (status='nouvelle' AND payment_method='delivery')")->fetchColumn();
$pageTitle = 'Livraisons';
$pageSubtitle = 'Zones, tarifs et livraison offerte dès ' . fcfa(FREE_DELIVERY_THRESHOLD);
require __DIR__ . '/layout.php';
?>
<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
  <a href="commandes.php?statut=a_traiter" class="bg-white rounded-2xl border border-line p-4 flex items-center gap-3 hover:border-forest"><span class="w-11 h-11 rounded-full bg-[#EFE9DC] text-forest flex items-center justify-center"><?= icon('package', 'w-5 h-5') ?></span><div><p class="text-xs text-muted">À préparer / expédier</p><p class="font-display text-2xl"><?= (int) $toShip ?></p></div></a>
  <a href="commandes.php?statut=expediee" class="bg-white rounded-2xl border border-line p-4 flex items-center gap-3 hover:border-forest"><span class="w-11 h-11 rounded-full bg-[#EFE9DC] text-forest flex items-center justify-center"><?= icon('truck', 'w-5 h-5') ?></span><div><p class="text-xs text-muted">En cours de livraison</p><p class="font-display text-2xl"><?= (int) $shipping ?></p></div></a>
  <div class="bg-white rounded-2xl border border-line p-4 flex items-center gap-3 col-span-2 lg:col-span-1"><span class="w-11 h-11 rounded-full bg-[#EFE9DC] text-forest flex items-center justify-center"><?= icon('gift', 'w-5 h-5') ?></span><div><p class="text-xs text-muted">Livraison offerte dès</p><p class="font-display text-2xl"><?= fcfa(FREE_DELIVERY_THRESHOLD) ?></p></div></div>
</div>
<div class="grid xl:grid-cols-[1fr_340px] gap-5 items-start">
  <?= card_open('Zones de livraison', 'map-pin') ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="save">
      <div class="overflow-x-auto -mx-5 sm:-mx-6">
        <table class="tbl w-full min-w-[560px]">
          <thead><tr><th class="!pl-5 sm:!pl-6">Zone</th><th>Tarif (FCFA)</th><th>Commandes</th><th class="!pr-5 sm:!pr-6">Active</th></tr></thead>
          <tbody>
            <?php foreach ($zones as $z): ?>
              <tr>
                <td class="!pl-5 sm:!pl-6"><input name="zone[<?= (int) $z['id'] ?>][name]" value="<?= e($z['name']) ?>" class="field !h-9"></td>
                <td class="w-40"><input type="number" min="0" step="100" name="zone[<?= (int) $z['id'] ?>][fee]" value="<?= (int) $z['fee'] ?>" class="field !h-9 tabular-nums"></td>
                <td class="text-muted"><?= (int) $z['orders_count'] ?></td>
                <td class="!pr-5 sm:!pr-6"><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="zone[<?= (int) $z['id'] ?>][active]" value="1" class="sr-only peer" <?= $z['active'] ? 'checked' : '' ?>><span class="w-10 h-6 rounded-full bg-gray-200 peer-checked:bg-forest transition after:content-[''] after:absolute after:top-1 after:left-1 after:w-4 after:h-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span></label></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-5 flex justify-end"><button class="btn btn-primary"><?= icon('check', 'w-4 h-4') ?> Enregistrer</button></div>
    </form>
  </section>
  <?= card_open('Nouvelle zone', 'plus') ?>
    <form method="post" class="space-y-4"><?= csrf_field() ?><input type="hidden" name="form_action" value="add">
      <label class="block"><span class="label">Nom de la zone</span><input name="name" required class="field" placeholder="Ex. Thiès"></label>
      <label class="block"><span class="label">Tarif (FCFA)</span><input type="number" name="fee" min="0" step="100" required class="field" placeholder="3000"></label>
      <button class="btn btn-primary w-full"><?= icon('plus', 'w-4 h-4') ?> Ajouter</button>
      <p class="text-xs text-muted">Une zone désactivée n'est plus proposée aux clients mais reste dans l'historique des commandes. Le seuil de livraison offerte se règle dans <code>config.php</code> (FREE_DELIVERY_THRESHOLD).</p>
    </form>
  </section>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
