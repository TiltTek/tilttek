<!-- phil-dev source-sha256=e0afae1e50f24e6b7e43d735a66b757259e0663e80274c805affc49e5f04b489 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/sets` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.0-alpha - 2026-11-15

L'intégration Symfony quitte la bibliothèque pour `tilttek/sets-bundle`.

### Modifié

- L'intégration Symfony sous `Tilttek\Sets\Symfony` — la commande `tilttek:sets:deliver`, le message
  `DeliverPendingCompletions` et son handler, et le planificateur `tilttek_sets` — passe dans `tilttek/sets-bundle`, qui
  les enregistre à partir de sa configuration. La bibliothèque n'a aucune dépendance Symfony, requise ou suggérée.

### Ajouté

- `Sets::deliverPending($limit)` : les accomplissements non remis annoncés de nouveau pour les détenteurs qui attendent
  depuis le plus longtemps, de 1 à 10 000 détenteurs au plus, sans savoir quels détenteurs attendent ; un détenteur dont
  le handler lève une exception ne retient personne, et l'exécution se termine par `Exception\DeliveryFailed`, qui porte
  les comptes et la première exception levée.
- `DeliveryBackoff`, le dernier argument de `Sets` : un détenteur dont la remise a échoué est reporté, les exécutions
  suivantes le sautent jusqu'à la fin de son délai, un délai d'une minute après un premier échec, doublé après chaque
  échec qui suit, jamais plus d'un jour ; une remise qui réussit oublie les échecs. Les délais et le nombre d'échecs
  signalés appartiennent à l'application.
- `Event\DeliveryKeepsFailing` : annoncé à partir du troisième échec de suite de la remise d'un détenteur, avec le
  détenteur, les échecs, le moment de la prochaine tentative et ce que le handler a levé ; un écouteur qui lève une
  exception n'arrête pas l'exécution.
- `Contract\PendingDeliveries`, facultatif à côté de `Contract\CompletionStore` : les détenteurs qui ont des
  accomplissements non remis et aucune tentative reportée, celui qui attend depuis le plus longtemps en premier, et les
  échecs de suite de chaque détenteur, gardés, reportés et oubliés ; `Store\InMemoryCompletionStore` l'implémente et
  oublie les échecs d'un détenteur effacé. `Exception\UnsupportedStore` dit qu'un stockage d'accomplissements ne
  l'implémente pas.
- La reprise des remises et le report progressif documentés.

## 0.2.0-alpha - 2026-11-14

### Modifié

- L'événement d'un accomplissement ne peut plus se perdre quand un handler échoue. Le stockage garde si chaque
  accomplissement a été remis : `CompletionStore` reçoit `undelivered()` et `markDelivered()`, qu'un stockage écrit pour
  0.1 implémente. `Sets::record()` annonce chaque accomplissement non remis du détenteur dans l'ensemble, avec les
  récompenses de la version sous laquelle il a été gagné, et marque chacun comme remis une fois son événement distribué
  sans exception ; un handler qui lève une exception laisse son accomplissement non remis, les autres sont annoncés
  quand même, et la première exception est levée de nouveau à la fin. Un accomplissement est annoncé au moins une fois :
  les handlers sont idempotents.

### Ajouté

- `Sets::deliver()` : chaque accomplissement non remis d'un détenteur annoncé de nouveau, dans chaque ensemble, pour
  reprendre après un échec.
- La remise des accomplissements documentée : remise au moins une fois, handlers idempotents et reprise.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `SetDefinition` : une version d'un ensemble, de 1 à 1 000 000, avec de 1 à 1 000 membres nommés une seule fois par un
  identifiant stable dans l'ordre où ils sont montrés, jusqu'à 20 paliers vérifiés du plus facile au plus difficile, et
  jusqu'à 20 récompenses ; lue depuis et écrite vers des tableaux et du JSON d'au plus 1 Mio, chaque défaut une
  `InvalidSet`.
- `Tier` : un nombre de membres possédés à un niveau de 1 à 1 000 ou plus — chaque membre par défaut —, avec jusqu'à 20
  récompenses, pour qu'un ensemble refait à des niveaux plus élevés soit un seul ensemble avec un palier par niveau.
- `Reward` : une récompense opaque qu'un ensemble ou un palier déclare — un type, une référence et une quantité —,
  accordée par l'application.
- `Holdings` et `Evaluation` : jusqu'à 100 000 membres que possède un détenteur, chacun à son niveau le plus élevé,
  mesurés contre un ensemble — les membres possédés et manquants dans l'ordre de l'ensemble, le pourcentage arrondi vers
  le bas, les paliers atteints et le suivant.
- `Sets` : des versions définies, jamais réécrites, et définies de nouveau sans un mot quand elles n'ont pas changé ; la
  progression d'un détenteur contre la dernière version ; l'accomplissement de l'ensemble et de ses paliers enregistré
  une seule fois avec la version sous laquelle il a été gagné et son moment en UTC, jamais repris par une version
  ultérieure ; l'effacement d'un détenteur.
- `Progress` : l'évaluation avec les accomplissements du détenteur, ceux que cet appel a enregistrés, et si un
  accomplissement a été gagné sous une version antérieure que le détenteur n'a pas accomplie depuis.
- `Event\SetCompleted` et `Event\TierReached` : distribués une seule fois par accomplissement, avec les récompenses
  déclarées.
- `Identifiers` : les formes des codes et des membres, et des détenteurs désignés par un identifiant stable, jamais par
  une adresse courriel.
- `Contract\DefinitionStore`, `Contract\CompletionStore`, `Store\InMemoryDefinitionStore` et
  `Store\InMemoryCompletionStore` : les ports des stockages et leurs adaptateurs en mémoire.
- `UtcClock` : l'horloge du système en UTC, l'horloge par défaut.
- La cible de fuzzing d'une définition et les bancs d'essai du plus grand ensemble.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
