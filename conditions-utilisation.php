<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Conditions d’utilisation';
$pageIntro = 'Les règles d’utilisation du site ' . SHOP_NAME . '. Dernière mise à jour : 26 novembre 2026.';
ob_start(); ?>
<h2 class="!mt-0">1. Éditeur du site</h2>
<p>Le site est édité par <?= e(SHOP_NAME) ?>, boutique de mode masculine basée à <?= e(SHOP_ADDRESS) ?>.</p>
<ul>
  <li><strong>Propriétaire et responsable de la publication :</strong> Mbosse Diatta</li>
  <li><strong>Téléphone / WhatsApp :</strong> <?= e(phone_display(WHATSAPP_NUMBER)) ?></li>
  <li><strong>Conception et développement du site :</strong> Mbosse Diatta</li>
</ul>
<p>Vous pouvez aussi nous écrire depuis la page <a href="<?= e(url('contact.php')) ?>">Contact</a>.</p>

<h2>2. Accès au site</h2>
<p>Le site est accessible gratuitement à toute personne disposant d’un accès à Internet. Les frais de connexion restent à la charge de l’utilisateur. Nous faisons notre possible pour que le site soit disponible en permanence, mais il peut être interrompu pour maintenance, mise à jour ou en cas de problème technique, sans que notre responsabilité puisse être engagée.</p>

<h2>3. Utilisation du site</h2>
<p>L’utilisateur s’engage à utiliser le site de manière loyale, et notamment à :</p>
<ul>
  <li>fournir des informations exactes lors d’une commande ;</li>
  <li>ne pas passer de fausses commandes ni utiliser l’identité ou le numéro de téléphone d’un tiers ;</li>
  <li>ne pas tenter d’accéder aux parties réservées du site (administration), d’en perturber le fonctionnement ou d’en extraire le contenu de manière automatisée.</li>
</ul>
<p>Nous pouvons refuser ou annuler toute commande en cas d’utilisation abusive ou frauduleuse.</p>

<h2>4. Commandes et paiement</h2>
<p>Les achats effectués sur le site sont régis par nos <a href="<?= e(url('cgv.php')) ?>">conditions générales de vente</a>. Le paiement en ligne est assuré par un prestataire de paiement spécialisé : les coordonnées de paiement sont saisies sur sa page sécurisée et ne sont jamais enregistrées par notre site.</p>

<h2>5. Informations publiées</h2>
<p>Nous veillons à l’exactitude des informations (descriptions, tailles, prix, disponibilités). Une erreur peut toutefois se produire : en cas d’erreur manifeste, notamment de prix, nous vous contactons avant toute expédition et la commande peut être corrigée ou annulée.</p>

<h2>6. Propriété intellectuelle</h2>
<p>Le nom et le logo <?= e(SHOP_NAME) ?>, les photos, les textes et la présentation du site sont protégés. Toute reproduction ou réutilisation, totale ou partielle, sans notre accord écrit est interdite. Le partage des pages produits par les boutons prévus à cet effet (WhatsApp, réseaux sociaux) est bien sûr autorisé.</p>

<h2>7. Liens vers d’autres services</h2>
<p>Le site contient des liens vers des services externes (WhatsApp, TikTok, page de paiement du prestataire, opérateurs de paiement mobile). Ces services ont leurs propres conditions et politiques de confidentialité, sur lesquelles nous n’avons pas de contrôle.</p>

<h2>8. Données personnelles et cookies</h2>
<p>Les données collectées et les cookies utilisés sont décrits dans notre <a href="<?= e(url('confidentialite.php')) ?>">politique de confidentialité</a>.</p>

<h2>9. Modification et droit applicable</h2>
<p>Ces conditions peuvent être modifiées à tout moment ; la version en ligne est celle qui s’applique. Elles sont soumises au droit sénégalais. En cas de litige, une solution amiable sera recherchée en priorité ; à défaut, les tribunaux compétents de Dakar seront seuls compétents.</p>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
