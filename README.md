# LE GENTLEMAN

Boutique en ligne de mode masculine (PHP + MySQL + Tailwind CSS) : catalogue, panier, commande WhatsApp, paiement Wave / Orange Money, coffrets cadeaux, thèmes de fête et administration.

## Installation

1. Copier le projet dans le dossier web (ex. `C:\wamp64\www\gentleman`).
2. Créer la base `le_gentleman` et importer `sql/schema.sql`, puis les fichiers `sql/migration-*.sql`.
3. Copier `config.example.php` en `config.php` et renseigner :
   - les accès à la base de données ;
   - `APP_SECRET` : une longue clé aléatoire (`php -r "echo bin2hex(random_bytes(24));"`) ;
   - `BASE_URL` (`/gentleman` en local, `''` si le site est à la racine du domaine) ;
   - les paramètres WhatsApp et Wave.
4. Styles : `npm install` puis `npm run build:css` (après toute modification de classes Tailwind).
5. Rendre `assets/uploads/` accessible en écriture (photos ajoutées depuis l'admin).

Les chemins d'images enregistrés en base (ex. `/gentleman/assets/uploads/…` venant de l'installation locale)
sont recalculés automatiquement avec le `BASE_URL` du serveur : aucune modification de la base n'est nécessaire.

## Administration

`/admin/` : produits, stocks, commandes, clients, coffrets, promotions, thèmes de fête, livraisons, statistiques, QR codes.

⚠️ Le compte créé par `sql/schema.sql` (`admin@legentleman.sn`) a un mot de passe par défaut : changez-le immédiatement
(`php sql/reset-admin.php` ou Admin → Paramètres).
