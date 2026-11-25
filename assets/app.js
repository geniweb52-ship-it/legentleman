(() => {
  'use strict';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const store = {
    get(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } },
    set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* stockage indisponible */ } },
  };
  const formatMoney = (n) => new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' FCFA';

  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-product-click]');
    if (!link) return;
    const body = new URLSearchParams({
      csrf: link.dataset.csrf || '',
      event_type: 'product_click',
      product_id: link.dataset.productClick,
    });
    fetch('analytics-track.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      keepalive: true,
      body,
    }).catch(() => {});
  });

  /* ---------- Confirmation (admin) ---------- */
  $$('[data-confirm]').forEach((el) => el.addEventListener('click', (ev) => {
    if (!window.confirm(el.dataset.confirm)) ev.preventDefault();
  }));

  /* ---------- Notification ---------- */
  const toast = $('#toast');
  let toastTimer;
  function showToast(message, type = 'success', withLink = false) {
    if (!toast) return;
    $('[data-toast-message]', toast).textContent = message;
    $('[data-toast-link]', toast).classList.toggle('is-hidden', !withLink);
    toast.classList.toggle('!bg-terra', type === 'error');
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 4200);
  }
  if (window.__flash) showToast(window.__flash.message, window.__flash.type);

  /* ---------- Images : reflet pendant le chargement, puis fondu ---------- */
  $$('img.img-fade').forEach((img) => {
    const box = img.closest('.shimmer');
    const done = () => { img.classList.remove('is-loading'); box?.classList.remove('shimmer'); };
    if (img.complete && img.naturalWidth) { done(); return; }
    img.classList.add('is-loading');
    img.addEventListener('load', done, { once: true });
    img.addEventListener('error', done, { once: true });
  });

  /* ---------- Thème de fête : particules (neige, cœurs, confettis…) ---------- */
  const fx = $('[data-holiday-fx]');
  if (fx && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const shapes = { snow: ['❄', '❅', '•'], hearts: ['❤', '♥'], stars: ['✦', '✧', '★'], flowers: ['🌸', '🌷', '✿'] };
    const kind = fx.dataset.holidayFx;
    const colors = (fx.dataset.colors || '#C8A24A').split(',');
    const pick = (list) => list[Math.floor(Math.random() * list.length)];
    const isSmall = window.innerWidth < 640;
    const spawn = () => {
      const p = document.createElement('span');
      const duration = 6 + Math.random() * 5;
      if (kind === 'confetti') {
        p.className = 'fx-piece';
        p.style.background = pick(colors);
      } else {
        p.textContent = pick(shapes[kind] || shapes.stars);
        p.style.color = pick(colors);
        p.style.fontSize = (isSmall ? 10 : 12) + Math.random() * (isSmall ? 10 : 14) + 'px';
      }
      p.style.left = Math.random() * 100 + '%';
      p.style.animationDuration = duration + 's';
      p.style.setProperty('--fx-drift', (Math.random() * 120 - 60) + 'px');
      p.style.setProperty('--fx-spin', (kind === 'snow' ? 180 : 420) * (Math.random() < .5 ? -1 : 1) + 'deg');
      fx.appendChild(p);
      setTimeout(() => p.remove(), duration * 1000 + 200);
    };
    // Une pluie légère pendant ~12 s à chaque page, puis plus rien (économise la batterie).
    const timer = setInterval(spawn, isSmall ? 650 : 420);
    setTimeout(() => clearInterval(timer), 12000);
  }

  /* ---------- Tiroir « Ajouté au panier » ---------- */
  const cartDrawer = $('[data-cart-drawer]');
  let cartDrawerReturn = null;
  const closeCartDrawer = () => {
    if (!cartDrawer?.classList.contains('open')) return;
    cartDrawer.classList.remove('open');
    cartDrawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    cartDrawerReturn?.focus();
  };
  function openCartDrawer(info) {
    if (!cartDrawer) return false;
    const img = $('[data-cart-drawer-image]', cartDrawer);
    img.src = info.image || '';
    img.alt = info.name || '';
    img.parentElement.classList.toggle('is-hidden', !info.image);
    $('[data-cart-drawer-name]', cartDrawer).textContent = info.name || '';
    $('[data-cart-drawer-options]', cartDrawer).textContent = info.options || '';
    $('[data-cart-drawer-price]', cartDrawer).textContent = info.price || '';
    $('[data-cart-drawer-count]', cartDrawer).textContent = info.count ?? '';
    cartDrawerReturn = document.activeElement;
    cartDrawer.classList.add('open');
    cartDrawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    $('a', cartDrawer)?.focus({ preventScroll: true });
    return true;
  }
  $$('[data-cart-drawer-close]').forEach((el) => el.addEventListener('click', closeCartDrawer));
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') closeCartDrawer(); });

  /* ---------- Panneaux : menu, recherche, filtres ---------- */
  let lastFocus = null;
  function openPanel(panel) {
    lastFocus = document.activeElement;
    document.body.classList.add('no-scroll');
    if (panel.classList.contains('filter-drawer')) {
      panel.classList.add('open');
    } else {
      panel.classList.remove('is-hidden');
      const drawer = $('.drawer', panel);
      requestAnimationFrame(() => requestAnimationFrame(() => drawer && drawer.classList.remove('-translate-x-full')));
    }
    const focusable = $('input:not([type=hidden]), button, a', panel.querySelector('form, aside, .drawer') || panel);
    setTimeout(() => focusable && focusable.focus({ preventScroll: true }), 60);
  }
  function closePanel(panel) {
    document.body.classList.remove('no-scroll');
    if (panel.classList.contains('filter-drawer')) {
      panel.classList.remove('open');
    } else {
      const drawer = $('.drawer', panel);
      if (drawer) { drawer.classList.add('-translate-x-full'); setTimeout(() => panel.classList.add('is-hidden'), 380); }
      else panel.classList.add('is-hidden');
    }
    if (lastFocus) lastFocus.focus({ preventScroll: true });
  }
  $$('[data-open]').forEach((btn) => btn.addEventListener('click', () => {
    const panel = document.getElementById(btn.dataset.open);
    if (panel) openPanel(panel);
  }));
  $$('[data-close]').forEach((btn) => btn.addEventListener('click', () => {
    const panel = btn.closest('#search-panel, #mobile-menu, .filter-drawer');
    if (panel) closePanel(panel);
  }));
  document.addEventListener('keydown', (ev) => {
    if (ev.key !== 'Escape') return;
    const open = $('#search-panel:not(.is-hidden), #mobile-menu:not(.is-hidden), .filter-drawer.open');
    if (open) closePanel(open);
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
      const panel = $('#search-panel');
      if (panel) { ev.preventDefault(); openPanel(panel); }
    }
  });

  /* ---------- En-tête : ombre au défilement ---------- */
  const header = $('#site-header');
  if (header) {
    const onScroll = () => header.classList.toggle('shadow-[0_6px_24px_-12px_rgba(0,0,0,.18)]', window.scrollY > 8);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Apparition au défilement ---------- */
  const reveals = $$('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const siblings = Array.from(entry.target.parentElement.children).filter((c) => c.classList.contains('reveal'));
      entry.target.style.transitionDelay = Math.min(siblings.indexOf(entry.target) % 4, 3) * 80 + 'ms';
      entry.target.classList.add('in');
      io.unobserve(entry.target);
    }), { rootMargin: '0px 0px -40px 0px', threshold: 0.08 });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('in'));
  }

  /* ---------- Favoris ---------- */
  const WISH_KEY = 'lg_wishlist';
  const getWish = () => store.get(WISH_KEY, []).map(Number).filter(Boolean);
  function renderWish() {
    const list = getWish();
    $$('[data-wish]').forEach((btn) => {
      const active = list.includes(Number(btn.dataset.wish));
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', String(active));
    });
    $$('[data-wish-count]').forEach((badge) => {
      badge.textContent = list.length;
      badge.classList.toggle('is-hidden', list.length === 0);
    });
  }
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-wish]');
    if (!btn) return;
    ev.preventDefault();
    const id = Number(btn.dataset.wish);
    let list = getWish();
    const adding = !list.includes(id);
    list = adding ? [id, ...list] : list.filter((x) => x !== id);
    store.set(WISH_KEY, list);
    renderWish();
    btn.classList.remove('pop'); void btn.offsetWidth; btn.classList.add('pop');
    showToast(adding ? 'Ajouté à vos favoris' : 'Retiré de vos favoris');
    if (!adding && document.querySelector('[data-favorites-page]')) {
      const card = btn.closest('.product-card');
      if (card) { card.style.transition = 'opacity .3s'; card.style.opacity = '0'; setTimeout(() => card.remove(), 300); }
    }
  });
  renderWish();
  const favPage = $('[data-favorites-page="pending"]');
  if (favPage) {
    const list = getWish();
    if (list.length) window.location.replace(window.location.pathname + '?ids=' + list.join(','));
  }

  /* ---------- Quantité (− / +) ---------- */
  $$('[data-qty]').forEach((wrap) => {
    const input = $('input[type=number]', wrap);
    const autosubmit = wrap.hasAttribute('data-autosubmit-qty');
    let timer;
    const change = (delta) => {
      const min = Number(input.min || 1);
      const max = Number(input.max || 99) || 99;
      const next = Math.max(min, Math.min(max, (Number(input.value) || min) + delta));
      if (next === Number(input.value)) {
        if (delta > 0) showToast('Quantité maximale disponible atteinte', 'error');
        return;
      }
      input.value = next;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };
    $('[data-qty-minus]', wrap).addEventListener('click', () => change(-1));
    $('[data-qty-plus]', wrap).addEventListener('click', () => change(1));
    if (autosubmit) input.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(() => wrap.submit(), 450); });
  });

  /* ---------- Tri : soumission automatique ---------- */
  $$('[data-autosubmit]').forEach((select) => select.addEventListener('change', () => {
    const form = select.form;
    if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
  }));

  /* ---------- Curseur de prix ---------- */
  $$('[data-price-range]').forEach((wrap) => {
    const lo = $('[data-lo]', wrap), hi = $('[data-hi]', wrap), fill = $('[data-fill]', wrap);
    const floor = Number(wrap.dataset.floor), ceil = Number(wrap.dataset.ceil);
    const loIn = $('[data-lo-input]', wrap), hiIn = $('[data-hi-input]', wrap);
    const update = (ev) => {
      let a = Number(lo.value), b = Number(hi.value);
      if (a > b) { if (ev && ev.target === lo) lo.value = a = b; else hi.value = b = a; }
      const span = ceil - floor || 1;
      fill.style.left = ((a - floor) / span) * 100 + '%';
      fill.style.right = (100 - ((b - floor) / span) * 100) + '%';
      $('[data-lo-label]', wrap).textContent = formatMoney(a);
      $('[data-hi-label]', wrap).textContent = formatMoney(b);
      loIn.value = a > floor ? a : '';
      hiIn.value = b < ceil ? b : '';
    };
    lo.addEventListener('input', update);
    hi.addEventListener('input', update);
    update();
  });

  /* ---------- Fiche produit : galerie ---------- */
  const mainImg = $('#product-image');
  $$('[data-gallery]').forEach((thumb) => thumb.addEventListener('click', () => {
    if (!mainImg) return;
    mainImg.style.opacity = '0';
    setTimeout(() => {
      if (thumb.dataset.srcset) mainImg.srcset = thumb.dataset.srcset; else mainImg.removeAttribute('srcset');
      mainImg.src = thumb.dataset.gallery;
      mainImg.style.opacity = '1';
    }, 160);
    $$('.gallery-thumb').forEach((t) => {
      const on = t === thumb;
      t.classList.toggle('border-ink', on);
      t.classList.toggle('border-transparent', !on);
      t.classList.toggle('opacity-70', !on);
    });
  }));
  if (mainImg) {
    // Zoom au survol (bureau)
    const box = mainImg.parentElement;
    box.addEventListener('mousemove', (ev) => {
      if (window.matchMedia('(hover: none)').matches) return;
      const r = box.getBoundingClientRect();
      mainImg.style.transformOrigin = `${((ev.clientX - r.left) / r.width) * 100}% ${((ev.clientY - r.top) / r.height) * 100}%`;
      mainImg.style.transform = 'scale(1.6)';
    });
    box.addEventListener('mouseleave', () => { mainImg.style.transform = ''; });
    mainImg.style.transition = 'opacity .3s, transform .25s ease-out';
  }

  /* ---------- Fiche produit : variantes, stock, WhatsApp, ajout AJAX ---------- */
  const productForm = $('[data-ajax-cart]');
  if (productForm) {
    const stock = JSON.parse(productForm.dataset.stock || '{}');
    const colorInputs = $$('input[name=color]', productForm);
    const sizeInputs = $$('input[name=size]', productForm);
    const qtyInput = $('input[name=quantity]', productForm);
    const hint = $('[data-stock-hint]', productForm);
    const wa = $('[data-whatsapp]');
    const share = $('[data-product-share]');
    const buyLink = $('[data-buy-link]');
    const bundle = productForm.dataset.bundle === '1';
    const bundleStock = Number(productForm.dataset.bundleStock || 0);
    const bundleSelectors = $$('[data-bundle-component]', productForm);
    const submitBtns = [$('[data-submit]', productForm), ...$$('button[form="add-to-cart"]')].filter(Boolean);
    const selected = (inputs) => (inputs.find((i) => i.checked) || {}).value;
    const componentChoiceFields = (component, key) => $$(`[data-component-choice="${key}"]`, component);
    const componentChoiceValue = (component, key) => {
      const fields = componentChoiceFields(component, key);
      if (!fields.length) return '';
      return fields[0].type === 'radio' ? (fields.find((field) => field.checked)?.value || '') : (fields[0].value || '');
    };
    const componentChoiceMissing = (component, key) => {
      const fields = componentChoiceFields(component, key);
      if (!fields.some((field) => field.required)) return false;
      return fields[0].type === 'radio' ? !fields.some((field) => field.checked) : !fields[0].value;
    };
    const incompleteBundleComponent = () => bundleSelectors.find((component) => ['size', 'color'].some((key) => componentChoiceMissing(component, key)));
    const hasMissingBundleChoice = () => !!incompleteBundleComponent();
    let bundleAvailable = null;
    productForm.noValidate = true;

    const trackProductEvent = (eventType) => {
      const csrf = $('input[name=csrf]', productForm)?.value;
      if (!csrf) return;
      fetch('analytics-track.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        keepalive: true,
        body: new URLSearchParams({
          csrf,
          event_type: eventType,
          product_id: share?.dataset.productId || buyLink?.dataset.productId || '0',
        }),
      }).catch(() => {});
    };

    const refresh = () => {
      const color = selected(colorInputs);
      $('[data-color-label]', productForm) && ($('[data-color-label]', productForm).textContent = color || '');
      sizeInputs.forEach((input) => {
        const qty = color ? (stock[color + '|' + input.value] || 0)
          : Object.entries(stock).filter(([k]) => k.endsWith('|' + input.value)).reduce((s, [, v]) => s + v, 0);
        input.disabled = qty <= 0;
        if (input.disabled && input.checked) input.checked = false;
      });
      const size = selected(sizeInputs);
      const available = color && size ? (stock[color + '|' + size] || 0) : null;
      let maxBundles = null;
      if (bundle) {
        maxBundles = bundleStock;
        bundleAvailable = maxBundles;
        qtyInput.max = Math.max(1, maxBundles);
        if (Number(qtyInput.value) > maxBundles) qtyInput.value = Math.max(1, maxBundles);
        hint.textContent = maxBundles < 1 ? 'Stock du coffret épuisé.' : maxBundles <= 3 ? `${maxBundles} coffret(s) disponible(s).` : '';
        submitBtns.forEach((button) => { button.disabled = maxBundles < 1; });
      } else if (available !== null) {
        qtyInput.max = Math.max(1, available);
        if (Number(qtyInput.value) > available) qtyInput.value = Math.max(1, available);
        const readyLabel = productForm.dataset.readyLabel || '';
        const readySpan = document.createElement('span');
        readySpan.className = 'text-forest font-medium inline-flex items-center gap-1.5';
        readySpan.innerHTML = '<span class="w-2 h-2 rounded-full bg-forest"></span>';
        readySpan.append(readyLabel);
        if (readyLabel) {
          // Article sur commande : on annonce la date à laquelle il sera prêt, pas « prêt à être expédié ».
          hint.replaceChildren(readySpan);
        } else {
          hint.innerHTML = available < 1
            ? ''
            : available <= 3
            ? `<span class="text-terra font-medium">Plus que ${available} en stock, commandez vite !</span>`
            : '<span class="text-forest font-medium inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-forest"></span>En stock, prêt à être expédié</span>';
        }
      } else {
        hint.textContent = '';
      }
      if (wa) {
        const parts = [wa.dataset.product];
        if (bundle) {
          const contents = bundleSelectors.map((component) => {
            const choices = ['size', 'color'].map((key) => {
              const value = componentChoiceValue(component, key);
              return value ? (key === 'size' ? 'taille ' : 'couleur ') + value : '';
            }).filter(Boolean);
            const selectedColor = componentChoiceValue(component, 'color');
            const colorLabel = $('[data-component-color-label]', component);
            if (colorLabel) colorLabel.textContent = selectedColor ? ': ' + selectedColor : '';
            return `${component.dataset.componentName}${choices.length ? ' (' + choices.join(', ') + ')' : ''} × ${component.dataset.componentQuantity}`;
          }).filter(Boolean);
          if (contents.length) parts.push('composition : ' + contents.join(', '));
        } else {
          if (size) parts.push('taille ' + size);
          if (color) parts.push(color.toLowerCase());
        }
        const msg = `Bonjour, je souhaite commander : ${parts.join(', ')} × ${qtyInput.value} (${wa.dataset.price}).\n${wa.dataset.url}`;
        wa.href = wa.href.split('?')[0] + '?text=' + encodeURIComponent(msg);
      }
      if (bundle && buyLink && bundleSelectors.length) {
        const params = new URLSearchParams({ id: buyLink.dataset.productId, quantity: qtyInput.value });
        bundleSelectors.forEach((component) => {
          ['size', 'color'].forEach((key) => {
            const value = componentChoiceValue(component, key);
            if (value) params.append(`components[${component.dataset.componentId}][${key}]`, value);
          });
        });
        buyLink.href = 'acheter.php?' + params.toString();
      } else if (size && color) {
        const params = new URLSearchParams({ id: share?.dataset.productId || buyLink?.dataset.productId || '', size, color, quantity: qtyInput.value });
        if (buyLink) buyLink.href = 'acheter.php?' + params.toString();
      }
      $('[data-size-error]', productForm)?.classList.toggle('is-hidden', !!size || !productForm.dataset.tried);
    };
    [...colorInputs, ...sizeInputs, ...bundleSelectors.flatMap((component) => $$('select[data-component-choice], input[type=radio][data-component-choice]', component))].forEach((i) => i.addEventListener('change', refresh));
    qtyInput.addEventListener('change', refresh);
    if (wa && bundle) {
      wa.addEventListener('click', (event) => {
        if (hasMissingBundleChoice() || (bundleAvailable !== null && bundleAvailable < 1)) {
          event.preventDefault();
          showToast(bundleAvailable !== null && bundleAvailable < 1 ? 'Stock du coffret épuisé' : 'Choisissez les tailles et couleurs demandées', 'error');
          incompleteBundleComponent()?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    }
    if (share) {
      share.addEventListener('click', async () => {
        trackProductEvent('share_click');
        const data = {
          title: share.dataset.shareTitle,
          text: share.dataset.shareText,
          url: share.dataset.shareUrl,
        };
        try {
          if (navigator.share) await navigator.share(data);
          else {
            await navigator.clipboard.writeText(data.url);
            showToast('Lien du produit copie');
          }
        } catch (error) {
          if (error?.name !== 'AbortError') showToast('Impossible de partager ce produit', 'error');
        }
      });
    }
    if (buyLink) {
      buyLink.addEventListener('click', (event) => {
        if (bundle) {
          if (hasMissingBundleChoice() || (bundleAvailable !== null && bundleAvailable < 1)) {
            event.preventDefault();
            showToast(bundleAvailable !== null && bundleAvailable < 1 ? 'Stock du coffret épuisé' : 'Choisissez les tailles et couleurs demandées', 'error');
            incompleteBundleComponent()?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          // Le clic « Acheter directement » est enregistré côté serveur par acheter.php.
          return;
        }
        const size = selected(sizeInputs);
        const color = selected(colorInputs);
        if (!size || !color) {
          event.preventDefault();
          productForm.dataset.tried = '1';
          refresh();
          showToast('Choisissez une taille et une couleur', 'error');
          (size ? colorInputs[0] : sizeInputs[0] || colorInputs[0])?.closest('fieldset')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    }
    refresh();

    productForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      productForm.dataset.tried = '1';
      if (bundle && hasMissingBundleChoice()) {
        showToast('Choisissez les tailles et couleurs demandées', 'error');
        incompleteBundleComponent()?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      if (colorInputs.length && !selected(colorInputs)) { showToast('Choisissez une couleur', 'error'); colorInputs[0].closest('fieldset').scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
      if (sizeInputs.length && !selected(sizeInputs)) {
        refresh();
        showToast('Choisissez votre taille', 'error');
        sizeInputs[0].closest('fieldset').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      const labels = submitBtns.map((b) => b.innerHTML);
      submitBtns.forEach((b) => { b.disabled = true; b.innerHTML = '<span class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span> Ajout…'; });
      let data;
      try {
        const res = await fetch(productForm.action, { method: 'POST', body: new FormData(productForm), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        data = await res.json();
      } catch {
        productForm.submit(); // repli sans JavaScript asynchrone
        return;
      }
      const options = bundle
        ? ''
        : [selected(sizeInputs) && 'Taille ' + selected(sizeInputs), selected(colorInputs)].filter(Boolean).join(' · ');
      const opened = data.ok && openCartDrawer({
        name: productForm.dataset.name,
        image: productForm.dataset.image,
        price: productForm.dataset.price + (Number(qtyInput.value) > 1 ? ' × ' + qtyInput.value : ''),
        options,
        count: data.count,
      });
      if (!opened) showToast(data.message, data.ok ? 'success' : 'error', data.ok);
      submitBtns.forEach((b, i) => {
        b.disabled = false;
        b.innerHTML = data.ok ? '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 5 5L20 7"/></svg> Ajouté !' : labels[i];
        if (data.ok) setTimeout(() => { b.innerHTML = labels[i]; }, 1600);
      });
      if (data.ok) {
        $$('[data-cart-count]').forEach((b) => { b.textContent = data.count; b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); });
      }
    });
  }

  /* ---------- Commande : total en direct ---------- */
  const checkout = $('[data-checkout]');
  if (checkout) {
    const zone = $('[data-zone]', checkout);
    const subtotal = Number(checkout.dataset.subtotal);
    const free = subtotal >= Number(checkout.dataset.free);
    const update = () => {
      const opt = zone.selectedOptions[0];
      const fee = free || !opt || !opt.dataset.fee ? 0 : Number(opt.dataset.fee);
      $('[data-fee-label]', checkout).innerHTML = free ? '<span class="text-forest font-semibold">Offerte</span>' : (opt && opt.value ? formatMoney(fee) : 'Choisissez une zone');
      $('[data-total-label]', checkout).textContent = formatMoney(subtotal + fee);
    };
    zone.addEventListener('change', update);
    update();
    checkout.addEventListener('submit', () => {
      const btn = $('button:not([type=button])', checkout);
      setTimeout(() => { btn.disabled = true; btn.textContent = 'Validation en cours…'; }, 0);
    });
  }
})();
