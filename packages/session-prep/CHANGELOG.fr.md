<!-- phil-dev source-sha256=7b9a73483ae3744dfabd0662fe9962b745d4598f9562c32a81d252a8ee353c5f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/session-prep` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Preparation` et `PreparationStatus` : la préparation par le meneur de jeu d'une séance d'une campagne — son titre, la
  séance qu'elle vise par son seul identifiant, jusqu'à 50 scènes, 50 secrets, 100 personnages non joueurs, 50 lieux,
  100 notes et une liste de contrôle de 50 éléments, en brouillon, prête ou close — bornée et vérifiée dans son
  ensemble, chaque changement renvoyant une nouvelle valeur.
- `Part`, `Scene`, `SceneStatus`, `Secret`, `Clue`, `NonPlayerCharacter`, `Place`, `Note` et `ChecklistItem` : les
  parties d'une préparation, chacune trouvée par sa clé ; une scène ne fait paraître que les personnages et les lieux
  que contient sa préparation, et un secret n'est lié à aucune scène.
- Les textes d'une préparation : de l'UTF-8 valide, sans espaces en bordure, comptés en caractères, une ligne sans
  caractères de contrôle ou des lignes dont les fins sont écrites de la même façon, sans caractères de forçage de
  direction.
- Les listes de parties gardées dans l'ordre du meneur de jeu : une partie mise à sa place ou après les autres, retirée,
  ou déplacée avant une autre ; un personnage ou un lieu retiré est enlevé des scènes où il paraît.
- `ChecklistTemplate` et `ChecklistTemplate::standard()` : les étapes d'une préparation, chacune nommée selon son but,
  l'ouverture, les scènes et les secrets requis, ajoutées après les propres éléments d'une préparation.
- `SessionPreparer` : créer, modifier par le meneur de jeu — qui peut confier une préparation à un autre — en gardant la
  séance, le statut et les faits de la table, et lier à une séance ou à aucune ; il ne garde aucun état entre les
  appels.
- `SessionPreparer::markScene()`, `markSecret()`, `markClue()` et `markItem()` : une scène jouée, sautée ou de nouveau
  prévue, un secret révélé ou caché, un indice trouvé ou non, un élément coché ou non, chacun annoncé une seule fois.
- `SessionPreparer::ready()`, `close()` et `reopen()` : un brouillon prêt une fois ses éléments requis cochés, clos
  quand la séance est finie et modifiable ensuite seulement une fois rouvert.
- `SessionPreparer::carryOver()` : les secrets non révélés et les scènes encore prévues d'une préparation, avec les
  personnages et les lieux qui paraissent dans ces scènes, reportés dans une autre préparation de la campagne, de
  nouveau seulement ce qui est nouveau.
- `SessionPreparer::delete()`, `preparation()` et `preparations()`, avec `PreparationQuery` : par campagne, meneur de
  jeu, séance et statut, par identifiant, jusqu'à 1000.
- Les événements `PreparationCreated`, `PreparationChanged`, `SessionLinked`, `SceneMarked`, `SecretMarked`,
  `ClueMarked`, `ItemMarked`, `PreparationReady`, `PreparationReopened`, `PreparationClosed`, `PreparationCarriedOver`
  et `PreparationDeleted`, par identifiants seulement, annoncés une seule fois à un `Contract\PreparationObserver`.
- `Contract\PreparationStore`, gardé à une version, supprimé à une version et lu par requête, et
  `Store\InMemoryPreparationStore` pour les tests.
- Les exceptions `InvalidPreparation`, `PreparationRefused`, `PreparationNotFound` et `PreparationConflict`, sous
  `SessionPrepException`.
- Des bancs d'essai de la plus grande préparation : un secret marqué, un report, une vérification complète.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
