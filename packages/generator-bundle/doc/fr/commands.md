<!-- phil-dev source-sha256=7323f864b16a185bb2cc795d52282ec7425323c64155a311c03c36eb48924e6a -->
# Commandes

Les commandes sont enregistrées quand `symfony/console` est installé.

## `tilttek:generator:validate`

Lit les listes et les générateurs dans leurs fichiers (et non dans le cache) et les vérifie comme le fait le
générateur : documents de mauvaise forme, gabarits qui ne compilent pas ou qui sortent du bac à sable, listes,
étiquettes et générateurs qui n'existent pas. Elle énumère chaque problème avec son gabarit et échoue, ou indique
combien de listes et de générateurs sont valides.

```text
$ bin/console tilttek:generator:validate
 [ERROR] 2 problems were found.
 * tavern:field:menu: the generator "tavern.dishes" does not exist
 * tavern.name:template:0: no entry of the list "tavern.animal" has the tag "colour"
```

Lancez-la dans l'intégration continue de l'application : une faute de frappe dans un générateur fait alors échouer la
construction plutôt qu'une page.

## `tilttek:generator:create <name>`

Écrit un nouveau générateur et sa première liste dans `<name>.yaml` du premier répertoire des chemins. Elle refuse un
nom invalide, un type ou une table qu'elle ne connaît pas, un fichier qui existe, ou des chemins sans répertoire.

| Option | Signification |
|---|---|
| `--kind=text` | Un générateur de texte (par défaut). |
| `--kind=fields` | Un résultat structuré avec deux champs. |
| `--kind=map` | Une carte carrée de 4 cellules sur 4. |
| `--kind=memory` | Une table à mémoire, dont l'état compte ses tirages. |
| `--locale=fr` | La langue du générateur. |
| `--from=shop` | Une copie de l'une des tables aléatoires de la bibliothèque, ses noms `table.shop…` renommés d'après le nouveau générateur ; le type est alors celui de la table, et `--locale` choisit sa langue, `en` (par défaut) ou `fr`. |

```text
bin/console tilttek:generator:create tavern.sign --kind=fields --locale=fr
bin/console tilttek:generator:create tavern.shop --from=shop
```

## `tilttek:generator:draw <name>`

Fait un tirage dans un générateur : un texte par ligne, ou un résultat structuré ou une carte en JSON. Un tirage qui
échoue, un générateur inconnu ou un catalogue qui ne se charge pas est une erreur.

| Option | Signification |
|---|---|
| `--count=5` | Combien de résultats, 1 par défaut. |
| `--unique` | Seulement des résultats différents ; un avertissement indique quand moins de résultats ont été trouvés. |
| `--seed=x` | Un germe qui fait revenir le même tirage. |
| `--key=campaign-7` | Une table à mémoire repart de l'état gardé sous cette clé, et garde celui qu'elle laisse ; l'état est écrit après les résultats. |
| `--reset` | Avec `--key` : oublie d'abord l'état, pour que la table recommence. |

```text
$ bin/console tilttek:generator:draw weather --count=3 --key=campaign-7
Fair
Overcast
Sunny
 // State: {"cell":"13"}
```

Une clé sans stockage des états (ni `psr/cache` ni autre stockage que `cache`) est une erreur.
