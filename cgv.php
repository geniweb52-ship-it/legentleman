<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Conditions générales de vente';
ob_start(); ?>
<h2 class="!mt-0">Commandes</h2>
<p>Les commandes sont enregistrées après validation du panier et du formulaire de livraison. Une référence unique vous est attribuée et permet de suivre la commande.</p>
<h2>Prix</h2>
<p>Les prix sont indiqués en francs CFA, toutes taxes comprises. Les frais de livraison dépendent de la zone choisie et sont affichés avant validation.</p>
<h2>Paiement</h2>
<p>Le paiement s'effectue par Wave, Orange Money ou en espèces à la livraison. Pour les paiements mobiles, le client transmet sa preuve de paiement sur WhatsApp.</p>
<h2>Échanges</h2>
<p>Un article peut être échangé sous 7 jours après réception s'il est non porté et dans son état d'origine.</p>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
