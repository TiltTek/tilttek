<!-- phil-dev source-sha256=1fb32d76b47a408db2d0d21e659f45b8c81ce202ed10d5e47df2c54363a231c6 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/loot-tables-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- Les prérequis de développement acceptent `phil/ux-kit` 0.6, la version publiée le 2026-11-24, et aucune version
  antérieure, pour que le paquet s'installe seul à côté de la version publiée du kit.

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `symfony/service-contracts` 3.8,
  `symfony/translation-contracts` 3.8.

### Corrigé

- Il requiert `phil/commerce-bundle` 0.5, la version publiée le même jour ; son prérequis de 0.2 ou 0.3 l'empêchait de
  s'installer à côté des versions publiées.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `TilttekLootTablesBundle` et sa configuration : les tables et les boîtes, lues dans un `LootCatalog` et vérifiées à la
  compilation du conteneur ; si un tirage doit être engagé avant le paiement ; si les tirages sont engagés quand la
  commande est passée ; où les tirages sont gardés ; où sont les recettes du kit UX.
- `Drawers` : les tireurs qu'une option nomme pour être tirés ici, `loot-box:<key>` pour une boîte du catalogue et
  `loot-text` pour un texte tiré parmi les entrées de son option, et la boîte que chacun tire.
- `SecureSeeds` : les germes des tirages, 32 octets tirés de `Random\Engine\Secure`, le seul moteur qu'il accepte.
- `LootCommitments` : un tirage engagé par unité de chaque option tirée ici quand la commande est passée, son germe
  gardé secret et son engagement montré, les entrées d'un texte gardées telles qu'elles étaient ; engager de nouveau
  garde le premier.
- `CommitOnPlace` : l'écouteur du workflow de commande du noyau du commerce qui engage les tirages dès que la commande
  est passée, désactivé par `commit_on_place: false`.
- `LootOptionDrawer` : le tireur d'options du commerce qui révèle chaque unité une seule fois après le paiement, à
  partir du seul germe du serveur avec l'identifiant du tirage comme germe du client, la ligne de commande gardant le
  texte du tirage d'une boîte ou le texte tiré ; une unité jamais engagée est refusée à moins que `require_commitment`
  soit désactivé.
- `PhysicalDice` : les dés physiques de chaque unité entrés à une caisse comme codes de boutique, enregistrés sur la
  ligne de commande et gardés comme tirages de dés physiques ; la boîte et le nombre d'unités que demande un formulaire.
- `LootResults` : les tirages d'une commande et le tirage d'un identifiant, le résultat de chacun avec ses entrées, son
  code de boutique, sa vérification contre l'engagement montré, et le tirage relancé à partir du germe qu'il a révélé.
- `LootPrint`, `PrintedDraw` et `PrintState` : ce qui est imprimé de chaque tirage d'une commande, lu dans
  `LootResults` — ses entrées avec leurs images et leurs emplacements, le texte tiré, le code de boutique, et où il en
  est.
- `ReplayDrawCommand`, `tilttek:loot-tables:replay` : un tirage révélé relancé à partir de son germe, vérifié contre ce
  qui a été gardé et contre son engagement.
- `PhysicalDiceType`, le formulaire des dés physiques, un champ par unité, chaque code lu contre la boîte avant que rien
  ne soit entré, et le composant `TilttekLootTables:PhysicalDice` qui l'affiche sur la page de la caisse.
- Le composant Twig `TilttekLootTables:Commitments` : les tirages d'une commande sur la page de l'acheteur sans
  JavaScript, chacun une `Card` du kit UX avec un `Badge` qui dit où il en est, son engagement et, une fois révélé, son
  germe, ses entrées et son code de boutique ; `KitComponents` et `KitSubdirectoryPass` nomment les recettes d'après le
  sous-dossier du Toolkit ; traductions en anglais et en français.
- `LootSheetPdf` : la fiche de production des tirages d'une commande en PDF, écrite à partir de `LootPrint`, par
  `phil/paged-media` quand il est installé.
- `LootDraw` et `LootDrawRepository` : les tirages dans la table Doctrine `tilttek_loot_draw`, leurs germes publiés une
  fois révélés, ou en mémoire pour les tests, réinitialisés avec le noyau ; `LootDrawRefused`.
- La recette Flex : le bundle enregistré et sa configuration copiée.
- La documentation : installation, configuration, tirage des commandes, dés physiques, résultats, écrans, fiche de
  production, exceptions, points d'extension, tests et référence.
- Les fichiers de la communauté : guide de contribution, code de conduite, politique de sécurité et licence MIT.
