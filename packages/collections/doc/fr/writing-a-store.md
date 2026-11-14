<!-- phil-dev source-sha256=f06c60880e8cb0dc069558dba18982b83110d2cb0ddb04c191393620f1718f3f -->
# Écrire un stockage

La bibliothèque garde son état derrière cinq interfaces. Les implémentations en mémoire servent aux tests et aux
essais, et `tilttek/collections-bundle` fournit celles sur Doctrine DBAL.

| Interface | Garde | Doit |
|---|---|---|
| `Store\CopyStore` | Les exemplaires, par identifiant | Écrire un exemplaire une seule fois, même quand deux processus l'écrivent ; réattribuer un exemplaire seulement à partir du propriétaire nommé, en le marquant déplacé ; reprendre un exemplaire seulement à son membre et s'il n'a jamais été déplacé ; oublier les exemplaires d'une source à partir d'un moment, sauf ceux déplacés |
| `Store\CompletionStore` | Les ensembles complétés | Écrire un ensemble une seule fois par membre |
| `Pack\OpeningStore` | Les paquets ouverts, par clé | Écrire une ouverture une seule fois par clé, la vérification et l'écriture en une seule étape atomique ; enregistrer les exemplaires d'une ouverture ajoutée sans eux |
| `Dice\ChoiceStore` | Les dés choisis | Garder un seul choix par membre et par nombre de faces |
| `Daily\DailyStore` | Le calendrier, par jour | Garder un seul choix d'objet par jour ; lire dans l'ordre les choix entre deux jours |

Chaque liste est renvoyée dans un ordre stable : les exemplaires par moment puis par identifiant, les complétions et
les ouvertures par moment, les choix de dés par nombre de faces, les choix d'objets par jour.

Les tests de la bibliothèque contiennent un cas de test abstrait par contrat — `CopyStoreTestCase`,
`CompletionStoreTestCase`, `OpeningStoreTestCase`, `ChoiceStoreTestCase`, `DailyStoreTestCase` — : étendez-le avec une
méthode qui renvoie un stockage neuf, et votre stockage passe les mêmes vérifications que celui de la bibliothèque.
