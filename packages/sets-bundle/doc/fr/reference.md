<!-- phil-dev source-sha256=d597d4ddcd34bbb0087a044b67a291ad16517541d00ac4d89fd6bea12fe5534e -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekSetsBundle` | Le bundle et sa configuration, sous `tilttek_sets` |
| `Infrastructure\DependencyInjection\SetsServices` | Enregistre les stockages en mémoire, le recul, `Sets`, et la commande, le *handler* et le planificateur dont les composants sont installés |
| `Infrastructure\Console\DeliverCommand` | La commande `tilttek:sets:deliver` : `Sets::deliverPending()`, ou `Sets::deliver()` avec `--holder` |
| `Infrastructure\Messenger\DeliverPendingCompletions` | Le message qui demande une remise en attente ; il porte seulement la limite, 100 par défaut |
| `Infrastructure\Messenger\DeliverPendingCompletionsHandler` | Son *handler*, qui appelle `Sets::deliverPending()` |
| `Infrastructure\Scheduler\DeliverySchedule` | Le planificateur `tilttek_sets` : le message à intervalles, cinq minutes par défaut, avec un verrou facultatif |

| Service | Ce que c'est |
|---|---|
| `Tilttek\Sets\Sets` | Public : les ensembles de l'application |
| `Tilttek\Sets\DeliveryBackoff` | Le recul de la configuration |
| `Tilttek\Sets\Store\InMemoryDefinitionStore`, `Tilttek\Sets\Store\InMemoryCompletionStore` | Les stockages, quand la configuration n'en nomme aucun |
