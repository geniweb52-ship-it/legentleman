  <?php if (empty($hideReassurance)): ?>
  <section class="border-t border-line bg-white/60 mt-20">
    <div class="max-w-site mx-auto px-4 sm:px-6 py-10 grid grid-cols-2 lg:grid-cols-4 gap-8">
      <?php foreach ([
        ['truck', 'Livraison rapide', 'À Dakar et dans les régions'],
        ['card', 'Paiement sécurisé', 'Wave, Orange Money ou à la livraison'],
        ['refresh', 'Échange facile', 'Sous 7 jours en cas de taille non adaptée'],
        ['shield', 'Produits de qualité', 'Sélection soignée et marques authentiques'],
      ] as [$ic, $title, $text]): ?>
        <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-3">
          <span class="w-12 h-12 rounded-full bg-cream border border-line flex items-center justify-center text-forest shrink-0"><?= icon($ic, 'w-6 h-6') ?></span>
          <div><p class="font-semibold text-sm"><?= e($title) ?></p><p class="text-xs text-muted mt-1 leading-relaxed"><?= e($text) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php else: ?><div class="mt-20"></div><?php endif; ?>

  <footer class="bg-forest-dark text-white/80">
    <div class="max-w-site mx-auto px-4 sm:px-6 pt-14 pb-8">
      <div class="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr_1.3fr]">
        <div>
          <img src="<?= e(url('assets/img/brand/logo-light.png')) ?>" alt="<?= e(SHOP_NAME) ?>" width="900" height="524" loading="lazy" class="w-48 h-auto -ml-1">
          <p class="mt-3 text-sm"><?= e(SHOP_TAGLINE) ?></p>
          <p class="mt-5 text-sm leading-relaxed max-w-xs text-white/65">Vêtements, chaussures, accessoires et coffrets cadeaux sélectionnés pour l'homme moderne, livrés partout au Sénégal.</p>
          <?php if (defined('SHOP_TIKTOK') && SHOP_TIKTOK !== ''): ?><a href="<?= e(SHOP_TIKTOK) ?>" target="_blank" rel="noopener" class="mt-5 inline-flex items-center gap-2 text-sm text-white/80 hover:text-white transition-colors"><span class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 5.82A4.28 4.28 0 0 1 15.54 3h-3.09v12.4a2.59 2.59 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg></span> Suivez-nous sur TikTok</a><?php endif; ?>
        </div>
        <div>
          <p class="text-white font-semibold text-sm mb-4">Boutique</p>
          <ul class="space-y-2.5 text-sm">
            <li><a class="hover:text-white transition-colors" href="<?= e(url('boutique.php')) ?>">Tous les produits</a></li>
            <li><a class="hover:text-white transition-colors" href="<?= e(url('boutique.php?sort=new')) ?>">Nouveautés</a></li>
            <li><a class="hover:text-white transition-colors" href="<?= e(url('coffrets.php')) ?>">Coffrets cadeaux</a></li>
            <li><a class="hover:text-white transition-colors" href="<?= e(url('favoris.php')) ?>">Mes favoris</a></li>
          </ul>
        </div>
        <div>
          <p class="text-white font-semibold text-sm mb-4">Aide</p>
          <ul class="space-y-2.5 text-sm">
            <li><a class="hover:text-white transition-colors" href="<?= e(url('suivi.php')) ?>">Suivre ma commande</a></li>
            <li><a class="hover:text-white transition-colors" href="<?= e(url('livraison.php')) ?>">Livraison & échanges</a></li>
            <li><a class="hover:text-white transition-colors" href="<?= e(url('guide-tailles.php')) ?>">Guide des tailles</a></li>
            <li><a class="hover:text-white transition-colors" href="<?= e(url('contact.php')) ?>">Contact</a></li>
          </ul>
        </div>
        <div>
          <p class="text-white font-semibold text-sm mb-4">Une question ?</p>
          <p class="text-sm text-white/65">Notre équipe vous répond rapidement sur WhatsApp pour une taille, une disponibilité ou une commande.</p>
          <a href="<?= e(whatsapp_url('Bonjour LE GENTLEMAN, j\'ai une question.')) ?>" target="_blank" rel="noopener" class="btn bg-white text-forest-dark hover:bg-cream mt-5 !min-h-11"><?= icon('whatsapp') ?> Discuter sur WhatsApp</a>
          <p class="mt-6 text-xs text-white/60">Paiements acceptés</p><div class="mt-2 flex flex-wrap gap-2 text-xs"><span class="px-2.5 py-1 rounded bg-white/10 text-white whitespace-nowrap">Wave</span><span class="px-2.5 py-1 rounded bg-white/10 text-white whitespace-nowrap">Orange Money</span><span class="px-2.5 py-1 rounded bg-white/10 text-white whitespace-nowrap">Espèces</span></div>
        </div>
      </div>
      <div class="mt-12 pt-6 border-t border-white/10 flex flex-col sm:flex-row gap-3 justify-between text-xs text-white/50">
        <p>© <?= date('Y') ?> <?= e(SHOP_NAME) ?>. Tous droits réservés.</p>
        <div class="flex gap-5"><a href="<?= e(url('cgv.php')) ?>" class="hover:text-white">Conditions générales</a><a href="<?= e(url('confidentialite.php')) ?>" class="hover:text-white">Confidentialité</a></div>
      </div>
    </div>
  </footer>

  <a href="<?= e(whatsapp_url('Bonjour, je souhaite des informations sur vos produits.')) ?>" target="_blank" rel="noopener" class="fixed <?= !empty($hasStickyBar) ? 'bottom-24' : 'bottom-5' ?> md:bottom-6 right-4 md:right-6 z-50 w-14 h-14 rounded-full bg-[#25D366] text-white flex items-center justify-center shadow-[0_10px_30px_-8px_rgba(37,211,102,.7)] hover:scale-105 transition-transform" aria-label="Contacter sur WhatsApp"><?= icon('whatsapp', 'w-7 h-7') ?></a>

  <!-- Tiroir « Ajouté au panier » -->
  <div class="cart-drawer" data-cart-drawer aria-hidden="true">
    <div class="overlay absolute inset-0 bg-ink/40" data-cart-drawer-close></div>
    <aside class="drawer absolute top-0 right-0 bottom-0 w-[88%] max-w-sm bg-cream shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title">
      <div class="flex items-center justify-between px-5 h-16 border-b border-line">
        <p id="cart-drawer-title" class="font-semibold inline-flex items-center gap-2"><span class="w-7 h-7 rounded-full bg-forest text-white flex items-center justify-center"><?= icon('check', 'w-4 h-4') ?></span> Ajouté au panier</p>
        <button type="button" class="icon-btn" data-cart-drawer-close aria-label="Fermer"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
      </div>
      <div class="p-5 flex gap-4">
        <div class="w-24 aspect-[4/5] rounded-lg overflow-hidden bg-[#EDE7DC] shrink-0"><img src="" alt="" class="w-full h-full object-cover" data-cart-drawer-image></div>
        <div class="min-w-0">
          <p class="font-medium leading-snug" data-cart-drawer-name></p>
          <p class="text-sm text-muted mt-1" data-cart-drawer-options></p>
          <p class="text-terra font-semibold mt-2 tabular-nums" data-cart-drawer-price></p>
        </div>
      </div>
      <div class="mt-auto p-5 border-t border-line space-y-3">
        <p class="text-sm text-muted text-center"><span data-cart-drawer-count></span> article(s) dans votre panier</p>
        <a href="<?= e(url('panier.php')) ?>" class="btn btn-primary w-full">Voir le panier</a>
        <button type="button" class="btn btn-outline w-full" data-cart-drawer-close>Continuer mes achats</button>
      </div>
    </aside>
  </div>

  <?php if (!empty($holiday) && $holiday['effect_enabled'] && $holiday['effect'] !== 'none'): ?>
    <div class="holiday-fx" aria-hidden="true" data-holiday-fx="<?= e($holiday['effect']) ?>" data-colors="<?= e(implode(',', $holiday['fx'])) ?>"></div>
  <?php endif; ?>

  <script src="<?= e(asset('assets/app.js')) ?>" defer></script>
</body>
</html>
