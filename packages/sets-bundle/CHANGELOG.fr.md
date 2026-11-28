<!-- phil-dev source-sha256=55f2062d6446ee0d327e8cc322095e6b59853c3dc19000be5d3ab63f86c76f86 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/sets-bundle` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

La première version est 0.1.0-alpha, pas encore publiée.

### Ajouté

- `TilttekSetsBundle` et sa configuration : les services des stockages des définitions et des complétions, en mémoire
  quand aucun n'est nommé ; l'horloge ; le délai d'attente progressif d'un détenteur dont la remise a échoué — premier
  délai, plus long délai, échecs signalés — ; et la planification — intervalle, limite, verrou —, qui peut être
  désactivée.
- Le service `Sets`, public, sur le répartiteur d'événements de l'application et le délai d'attente progressif
  configuré.
- La commande `tilttek:sets:deliver` : les complétions non remises des détenteurs qui attendent depuis le plus
  longtemps, au plus `--limit`, ou toutes celles du détenteur que nomme `--holder` ; elle échoue, en disant combien ont
  été remises, quand un gestionnaire a levé une exception. Enregistrée quand `symfony/console` est installé.
- Le message `DeliverPendingCompletions`, qui ne porte qu'une limite, et son *handler*. Enregistrés quand
  `symfony/messenger` est installé.
- La planification `tilttek_sets`, qui envoie ce message toutes les cinq minutes par défaut, exécute une seule fois une
  exécution manquée et prend un verrou facultatif. Enregistrée quand `symfony/scheduler` est installé.
