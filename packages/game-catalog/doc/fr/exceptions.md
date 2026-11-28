<!-- phil-dev source-sha256=7418e76db6a4cefa95722b95977488cf5eb005d2434b3ec9cb15bdf1a9183281 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\GameCatalog\Exception\CatalogException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidEntry` | Une valeur d'entrée, un identifiant, un lien, un erratum, une correction ou un code-barres est hors de sa forme ou de ses bornes | `The name of a game is 1 to 200 characters.` |
| `InvalidVote` | Un bulletin contient un vote hors de son échelle, plus de 30 nombres de joueurs, ou aucun vote | `The weight is from 1 to 5.` |
| `InvalidCollectionFile` | Un fichier de collection ne se lit pas en entier, ou des colonnes ou des limites ne sont pas valides | `A collection file is at most 2097152 bytes.` |
| `CatalogRefused` | Un identifiant, une origine ou un code-barres qu'une autre entrée détient ; un lien au-delà de la limite ; une correction déjà tranchée, qui ne change rien, ou au-delà de la limite d'un membre ; une source qui envoie les entrées d'une autre | `The entry e-1 already holds rpggeek:7.` |
| `CorrectionConflict` | Un champ qu'une correction change a été changé sur l'entrée depuis qu'elle a été proposée ; son `fields` les nomme | `The correction c-2 changes year, changed on the entry since it was proposed.` |
| `CatalogConflict` | L'entrée ou la correction a changé, ou a été ajoutée, depuis sa lecture : rien n'est gardé | `The entry e-1 changed since it was read.` |
| `EntryNotFound` | Aucune entrée n'a l'identifiant | `No entry of the catalogue has the identifier e-9.` |
| `CorrectionNotFound` | Aucune correction n'a l'identifiant | `No correction has the identifier c-9.` |
