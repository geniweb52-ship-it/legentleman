<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Confidentialité';
ob_start(); ?>
<p>Les informations collectées (nom, téléphone, adresse) servent uniquement au traitement et à la livraison de vos commandes. Elles ne sont jamais revendues.</p>
<p>Vos favoris sont enregistrés uniquement dans votre navigateur. Pour toute demande de modification ou de suppression de vos données, écrivez-nous sur WhatsApp.</p>
<p>Pour mesurer la fréquentation de la boutique, un identifiant aléatoire est enregistré dans un cookie et les pages consultées ainsi que les interactions avec les produits sont comptabilisées de façon anonyme dans l’administration. Si vous arrivez par un QR code de campagne, un cookie conserve son identifiant pendant 30 jours afin d’attribuer une éventuelle commande à ce support. Aucune adresse IP n’est enregistrée à cette fin. Ces données servent uniquement à améliorer la boutique et ne sont pas revendues.</p>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
