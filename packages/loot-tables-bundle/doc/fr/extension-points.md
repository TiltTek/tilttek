<!-- phil-dev source-sha256=9dec1ec7a4f192216b91a6817262e1ba0b97e88588946fba872a4f914ed7967d -->
# Points d'extension

## Le catalogue du noyau du commerce

Le `SellableOptions` de l'application déclare les options de chaque produit : une option de tirage qui nomme
`Drawers::box('<key>')`, ou une option de texte tirée parmi ses entrées qui nomme `Drawers::TEXT`. Le bundle lit les
entrées d'un texte par le `LineOptions` du noyau du commerce.

## Quand les tirages sont engagés

`CommitOnPlace` engage les tirages quand le workflow de la commande passe la commande. Une application qui passe les
commandes autrement règle `commit_on_place: false` et appelle `LootCommitments::commit()` une fois la commande passée,
avant que l'acheteur ne paie.

## Les écrans

Remplacez les gabarits comme pour tout bundle, sous `templates/bundles/TilttekLootTablesBundle/` :

| Gabarit | Ce qu'il dessine |
|---|---|
| `components/Commitments.html.twig` | Les tirages de l'acheteur, leurs engagements, et une fois révélés leurs germes et leurs entrées |
| `components/PhysicalDice.html.twig` | Le formulaire des dés physiques |

Les mots sont dans le domaine `tilttek_loot_tables`. Pour une autre langue, ajoutez un fichier
`translations/tilttek_loot_tables.<locale>.yaml` avec les clés des fichiers du bundle. Le formulaire des dés peut être
rendu avec n'importe quel thème de formulaire. Les tirages de l'acheteur sont dessinés avec les recettes `Card` et
`Badge` du kit UX que l'application a installé : modifiez leur style là.

## Ce qui s'imprime

`LootPrint` compose ce qui s'imprime de chaque tirage ; une application qui envoie des fichiers d'impression à un
atelier y lit les images de chaque unité.

## La fiche de production

`LootSheetPdf` prend ses étiquettes, sa page et ses polices en arguments : enregistrez de nouveau le service pour les
changer, comme le montre [la fiche de production](production-sheet.md).

## Où sont gardés les tirages

`LootDrawRepository` est une interface : le bundle fournit un stockage Doctrine et un stockage en mémoire. Une
application dotée d'un autre stockage donne pour alias à l'interface son propre service.

## Le hasard

Les germes viennent de `SecureSeeds`, qui prend `Random\Engine\Secure` et rien d'autre : PHP déclare ce moteur
final, si bien qu'aucune autre source de hasard ne peut remplacer le générateur cryptographique du système
d'exploitation.
