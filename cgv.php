<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Conditions générales de vente';
$pageIntro = 'Les règles qui s’appliquent à toute commande passée sur la boutique ' . SHOP_NAME . '. Dernière mise à jour : 26 novembre 2026.';
$online = geniuspay_enabled();
ob_start(); ?>
<h2 class="!mt-0">1. Objet</h2>
<p>Les présentes conditions générales de vente (CGV) régissent les ventes conclues entre <?= e(SHOP_NAME) ?>, boutique de mode masculine basée à <?= e(SHOP_ADDRESS) ?> et dirigée par sa propriétaire Mbosse Diatta (téléphone / WhatsApp : <?= e(phone_display(WHATSAPP_NUMBER)) ?>), et toute personne (« le client ») qui passe commande sur le site, par le formulaire de commande ou par le bouton « Commander sur WhatsApp ». Valider une commande vaut acceptation de ces conditions.</p>

<h2>2. Produits</h2>
<p>Les articles sont décrits et photographiés avec le plus grand soin. De légères différences de couleur peuvent exister selon l’écran utilisé. Les offres sont valables dans la limite des stocks disponibles.</p>
<p>Certains articles sont proposés « sur commande » : la date à laquelle ils seront prêts est indiquée sur la fiche produit, dans le panier, puis sur la confirmation et la facture.</p>

<h2>3. Prix</h2>
<p>Les prix sont indiqués en francs CFA (FCFA), toutes taxes comprises. Les frais de livraison dépendent de la zone choisie et sont affichés avant la validation ; la livraison est offerte dès <?= money(FREE_DELIVERY_THRESHOLD) ?> d’achat. Le prix applicable est celui affiché au moment de la validation de la commande.</p>

<h2>4. Commande</h2>
<p>La commande est enregistrée lorsque le client valide le formulaire (prénom, nom, téléphone, zone et adresse de livraison, mode de paiement). Une référence unique (par exemple <strong>LG-XXXXXXXX</strong>) lui est attribuée : elle permet de suivre la commande et d’accéder à la facture. Les articles sont réservés dès l’enregistrement.</p>
<p>Le client s’engage à fournir des informations exactes, en particulier son numéro de téléphone, indispensable pour organiser la livraison.</p>

<h2>5. Paiement</h2>
<?php if ($online): ?>
  <p>Deux modes de paiement sont proposés :</p>
  <ul>
    <li><strong>Paiement en ligne</strong> : au moment de la commande, le client est dirigé vers la page de paiement sécurisée de notre prestataire GeniusPay, où il règle par Wave, Orange Money ou carte bancaire. Les coordonnées de paiement (numéro de carte, code secret, code de validation) sont saisies uniquement chez le prestataire ou l’opérateur : elles ne transitent jamais par notre site et ne sont jamais conservées par <?= e(SHOP_NAME) ?>. La commande est confirmée automatiquement dès que le prestataire nous notifie le paiement.</li>
    <li><strong>Paiement à la livraison</strong> : le montant exact est réglé en espèces au livreur, à la réception du colis.</li>
  </ul>
  <p>Si le paiement en ligne n’aboutit pas, la commande est conservée et le client peut réessayer depuis la page de confirmation ou la page « Suivre ma commande ». Une commande payable en ligne et restée impayée 48 heures après sa création peut être annulée et les articles remis en vente.</p>
<?php else: ?>
  <p>Le paiement s’effectue par Wave, Orange Money ou en espèces à la livraison. Pour les paiements mobiles, le lien ou les instructions de paiement sont transmis sur WhatsApp, et le client nous envoie sa preuve de paiement dans la même conversation.</p>
<?php endif; ?>
<p>Une facture est disponible pour chaque commande ; elle peut être consultée, imprimée ou enregistrée en PDF.</p>

<h2>6. Livraison</h2>
<p>Nous livrons à Dakar et dans les régions du Sénégal. Délais indicatifs : 24 à 48 h à Dakar et en banlieue, 2 à 4 jours ouvrés dans les régions ; pour les articles sur commande, à partir de la date où ils sont prêts. Nous contactons le client par téléphone ou WhatsApp avant chaque livraison. Le détail des zones et des tarifs figure sur la page <a href="<?= e(url('livraison.php')) ?>">Livraison & échanges</a>.</p>
<p>Si le client est injoignable ou absent à plusieurs reprises, ou refuse un colis conforme, la commande peut être annulée ; les frais de livraison déjà engagés peuvent alors être retenus.</p>

<h2>7. Annulation</h2>
<p>Le client peut annuler sa commande sur simple demande sur WhatsApp, en indiquant sa référence, dans les <strong>48 heures</strong> qui suivent sa validation. Si elle a déjà été payée en ligne, le montant est remboursé intégralement ; si le colis était déjà parti en livraison, les frais de livraison peuvent être retenus. Passé ce délai de 48 heures, la commande ne peut plus être annulée, mais les échanges restent possibles dans les conditions de l’article 8.</p>
<p><?= e(SHOP_NAME) ?> peut annuler une commande en cas d’indisponibilité d’un article, d’erreur manifeste de prix ou d’informations de livraison impossibles à vérifier. Le client en est informé sans délai et, si la commande a été payée, il est remboursé intégralement.</p>

<h2>8. Échanges et retours</h2>
<p>Un article peut être échangé dans les 7 jours suivant sa réception, sur simple demande sur WhatsApp, s’il est non porté, non lavé et dans son état et son emballage d’origine. Les frais de livraison de l’échange sont à la charge du client, sauf erreur de notre part.</p>
<p>Un article défectueux ou ne correspondant pas à la commande est échangé ou remboursé, au choix du client, et les frais de livraison sont alors à notre charge. Les articles de coffrets composés à la demande ne sont échangeables qu’en cas de défaut ou d’erreur de notre part.</p>

<h2>9. Remboursements</h2>
<p>Les remboursements sont effectués sous 7 jours ouvrés après acceptation, de préférence par le moyen de paiement utilisé (Wave, Orange Money ou carte bancaire) ou, à défaut, par un autre moyen convenu avec le client. Les délais d’affichage sur le compte du client dépendent de son opérateur ou de sa banque.</p>

<h2>10. Données personnelles</h2>
<p>Les informations recueillies servent au traitement, au paiement et à la livraison des commandes. Leur utilisation et les droits du client sont décrits dans notre <a href="<?= e(url('confidentialite.php')) ?>">politique de confidentialité</a>.</p>

<h2>11. Réclamations et litiges</h2>
<p>Pour toute question ou réclamation, contactez-nous sur WhatsApp au <?= e(phone_display(WHATSAPP_NUMBER)) ?> en indiquant la référence de la commande. Nous nous engageons à rechercher une solution amiable. Les présentes conditions sont soumises au droit sénégalais ; à défaut d’accord amiable, les tribunaux compétents de Dakar sont seuls compétents.</p>

<h2>12. Modification des conditions</h2>
<p><?= e(SHOP_NAME) ?> peut modifier ces conditions à tout moment. Les conditions applicables sont celles en vigueur à la date de la commande.</p>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
