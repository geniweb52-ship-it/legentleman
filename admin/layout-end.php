    </main>
    <footer class="px-4 sm:px-6 lg:px-8 py-5 text-xs text-muted border-t border-line">© <?= date('Y') ?> <?= e(SHOP_NAME) ?> · Administration</footer>
  </div>
</div>

<!-- Fenêtre « Partager un produit » (boutons data-share-product) -->
<dialog id="share-dialog" class="w-[min(440px,calc(100%-2rem))] rounded-2xl p-0 backdrop:bg-ink/50 shadow-2xl">
  <div class="p-5 sm:p-6">
    <div class="flex items-start gap-3">
      <img data-share-img src="" alt="" class="w-16 h-20 rounded-lg object-cover bg-[#EDE7DC] hidden">
      <div class="flex-1 min-w-0">
        <p class="text-xs text-muted">Partager</p>
        <p class="font-display text-xl leading-tight" data-share-name></p>
        <p class="mt-1 text-xs text-terra hidden" data-share-hidden>Ce produit est masqué : les clients ne pourront pas l'ouvrir tant qu'il n'est pas publié.</p>
      </div>
      <button type="button" class="w-9 h-9 rounded-lg hover:bg-cream flex items-center justify-center" data-share-close aria-label="Fermer"><?= icon('x', 'w-5 h-5') ?></button>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-2.5">
      <a data-share-wa target="_blank" rel="noopener" class="btn btn-outline w-full"><?= icon('whatsapp', 'w-5 h-5 text-[#1DA851]') ?> WhatsApp</a>
      <a data-share-fb target="_blank" rel="noopener" class="btn btn-outline w-full"><svg class="w-5 h-5 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.3H8v3h2.5V21z"/></svg> Facebook</a>
      <button type="button" data-share-native class="btn btn-primary w-full col-span-2"><?= icon('external', 'w-4 h-4') ?> TikTok, Instagram, Snapchat…</button>
      <button type="button" data-share-copy class="btn btn-outline w-full">Copier le texte</button>
      <a data-share-download download class="btn btn-outline w-full"><?= icon('upload', 'w-4 h-4 rotate-180') ?> Photo</a>
    </div>
    <p class="mt-4 text-xs text-muted leading-relaxed" data-share-help>Sur téléphone, « TikTok, Instagram… » envoie la photo et le texte directement dans l'application choisie. Sur ordinateur : téléchargez la photo, copiez le texte, puis collez-le dans votre publication TikTok.</p>
  </div>
</dialog>

<script>
(() => {
  // Partage d'un produit
  const shareDialog = document.getElementById('share-dialog');
  let shareData = null;
  const shareMessage = (d) => `${d.text}\n👉 ${d.url}`;
  const flashShare = (msg) => {
    const help = shareDialog.querySelector('[data-share-help]');
    const old = help.textContent;
    help.textContent = msg; help.classList.add('!text-forest', 'font-semibold');
    setTimeout(() => { help.textContent = old; help.classList.remove('!text-forest', 'font-semibold'); }, 3500);
  };
  const copyShareText = async () => {
    try { await navigator.clipboard.writeText(shareMessage(shareData)); flashShare('Texte et lien copiés : collez-les dans votre publication.'); }
    catch { prompt('Copiez ce texte :', shareMessage(shareData)); }
  };
  document.querySelectorAll('[data-share-product]').forEach((btn) => btn.addEventListener('click', (event) => {
    event.preventDefault(); // évite d'ouvrir/fermer un bloc repliable quand le bouton est dans son titre
    shareData = { ...btn.dataset };
    const msg = shareMessage(shareData);
    shareDialog.querySelector('[data-share-name]').textContent = shareData.name;
    shareDialog.querySelector('[data-share-hidden]').classList.toggle('hidden', shareData.active === '1');
    const img = shareDialog.querySelector('[data-share-img]');
    img.classList.toggle('hidden', !shareData.image); if (shareData.image) img.src = shareData.image;
    shareDialog.querySelector('[data-share-wa]').href = 'https://wa.me/?text=' + encodeURIComponent(msg);
    shareDialog.querySelector('[data-share-fb]').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareData.url);
    const dl = shareDialog.querySelector('[data-share-download]');
    dl.classList.toggle('hidden', !shareData.image); dl.href = shareData.image || '#';
    shareDialog.showModal();
  }));
  shareDialog?.querySelector('[data-share-close]').addEventListener('click', () => shareDialog.close());
  shareDialog?.addEventListener('click', (e) => { if (e.target === shareDialog) shareDialog.close(); });
  shareDialog?.querySelector('[data-share-copy]').addEventListener('click', copyShareText);
  shareDialog?.querySelector('[data-share-native]').addEventListener('click', async () => {
    const data = { title: shareData.name, text: shareMessage(shareData) };
    // Avec la photo jointe, TikTok / Instagram la proposent directement en publication.
    if (shareData.image && navigator.canShare) {
      try {
        const blob = await (await fetch(shareData.image)).blob();
        const file = new File([blob], 'produit.' + (blob.type.split('/')[1] || 'jpg'), { type: blob.type });
        if (navigator.canShare({ files: [file] })) data.files = [file];
      } catch {}
    }
    if (navigator.share) {
      try { await navigator.share(data); } catch (e) { if (e.name !== 'AbortError') copyShareText(); }
    } else {
      await copyShareText();
    }
  });

  const side = document.getElementById('sidebar'), overlay = document.getElementById('side-overlay');
  const toggle = (open) => { side.classList.toggle('-translate-x-full', !open); overlay.classList.toggle('hidden', !open); document.body.style.overflow = open ? 'hidden' : ''; };
  document.querySelectorAll('[data-side-open]').forEach((b) => b.addEventListener('click', () => toggle(true)));
  document.querySelectorAll('[data-side-close]').forEach((b) => b.addEventListener('click', () => toggle(false)));
  // Un seul menu déroulant ouvert à la fois, fermeture au clic extérieur / Échap
  const drops = [...document.querySelectorAll('[data-dropdown]')];
  drops.forEach((d) => d.addEventListener('toggle', () => { if (d.open) drops.forEach((o) => o !== d && (o.open = false)); }));
  document.addEventListener('click', (e) => drops.forEach((d) => { if (!d.contains(e.target)) d.open = false; }));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { drops.forEach((d) => (d.open = false)); toggle(false); } });
  // Confirmation des actions sensibles
  document.querySelectorAll('[data-confirm]').forEach((el) => el.addEventListener('click', (e) => { if (!confirm(el.dataset.confirm)) e.preventDefault(); }));
  // Soumission automatique (filtres, statuts)
  document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form && el.form.submit()));
  // Aperçu des images avant envoi
  document.querySelectorAll('input[type=file][data-preview]').forEach((input) => input.addEventListener('change', () => {
    const img = document.getElementById(input.dataset.preview);
    if (img && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.classList.remove('hidden'); }
  }));
  // Un lien vers #ancre ouvre le bloc repliable correspondant (ex. formulaire « Nouveau produit »)
  const openHash = () => {
    const target = location.hash && document.getElementById(location.hash.slice(1));
    if (target && target.tagName === 'DETAILS') {
      target.open = true;
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      const first = target.querySelector('input:not([type=hidden]), select, textarea');
      if (first) setTimeout(() => first.focus({ preventScroll: true }), 300);
    }
  };
  openHash();
  window.addEventListener('hashchange', openHash);
  const toast = document.getElementById('toast');
  if (toast) setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(10px)'; }, 3800);
})();
</script>
</body>
</html>
