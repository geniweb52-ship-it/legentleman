<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Confidentialité';
$pageIntro = 'Quelles données nous recueillons, pourquoi, et comment exercer vos droits. Dernière mise à jour : 26 novembre 2026.';
ob_start(); ?>
<h2 class="!mt-0">Responsable</h2>
<p>Les données sont traitées par <?= e(SHOP_NAME) ?>, <?= e(SHOP_ADDRESS) ?>, représentée par sa propriétaire Mbosse Diatta (téléphone / WhatsApp : <?= e(phone_display(WHATSAPP_NUMBER)) ?>), conformément à la loi sénégalaise n° 2008-12 du 25 janvier 2008 sur la protection des données à caractère personnel.</p>

<h2>Données recueillies et utilisation</h2>
<p>Lors d’une commande : prénom, nom, numéro de téléphone, zone et adresse de livraison, instructions éventuelles, articles commandés et mode de paiement. Ces informations servent uniquement à traiter, faire payer, préparer et livrer votre commande, à établir la facture et à vous contacter à son sujet. Elles ne sont jamais revendues ni utilisées pour de la publicité sans votre accord.</p>

<h2>Paiement en ligne</h2>
<p>Si vous payez en ligne, nous transmettons à notre prestataire de paiement GeniusPay les informations nécessaires au paiement : référence et montant de la commande, nom et numéro de téléphone. Vous saisissez vos coordonnées de paiement (carte bancaire, compte Wave ou Orange Money, codes de validation) directement sur la page du prestataire ou de l’opérateur : elles ne passent jamais par notre site et nous n’y avons pas accès. Nous recevons seulement la confirmation du paiement et sa référence de transaction.</p>

<h2>WhatsApp et livraison</h2>
<p>Si vous nous écrivez ou commandez via WhatsApp, vos échanges sont soumis aux conditions de WhatsApp. Pour la livraison, votre nom, votre téléphone et votre adresse sont communiqués au livreur, uniquement pour vous remettre le colis.</p>

<h2>Cookies et mesure d’audience</h2>
<p>Un cookie de session est nécessaire au fonctionnement du panier et de la commande. Vos favoris sont enregistrés uniquement dans votre navigateur.</p>
<p>Pour mesurer la fréquentation de la boutique, un identifiant aléatoire est enregistré dans un cookie et les pages consultées ainsi que les interactions avec les produits sont comptabilisées de façon anonyme dans l’administration. Si vous arrivez par un QR code de campagne, un cookie conserve son identifiant pendant 30 jours afin d’attribuer une éventuelle commande à ce support. Aucune adresse IP n’est enregistrée à cette fin. Ces données servent uniquement à améliorer la boutique et ne sont pas revendues.</p>

<h2>Conservation et sécurité</h2>
<p>Les données de commande sont conservées le temps nécessaire au suivi des commandes, au service après-vente et à nos obligations comptables. L’accès à l’administration est réservé à notre équipe et protégé par mot de passe ; le site est servi en connexion sécurisée (https).</p>

<h2>Vos droits</h2>
<p>Vous pouvez demander à consulter, corriger ou supprimer vos données, ou vous opposer à leur utilisation, en nous écrivant sur WhatsApp au <?= e(phone_display(WHATSAPP_NUMBER)) ?>. Certaines informations liées à une commande payée doivent toutefois être conservées pour nos obligations comptables. Vous pouvez aussi saisir la Commission de Protection des Données Personnelles (CDP) du Sénégal.</p>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
