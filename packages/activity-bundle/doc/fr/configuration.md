<!-- phil-dev source-sha256=3a254b2352f1808381c59adfcc42ccbf10ef2c377f3ff172efcd54d86c8ab0f5 -->
# Configuration

```yaml
tilttek_activity:
    store: doctrine            # or memory, for tests
    connection: default        # the DBAL connection of the journal
    verbs: []                  # the declared verbs, as patterns; empty accepts every verb
    batch_size: 500            # entries read at a time by a projection, and by the export of a member
    projections: live          # live, messenger or manual
    projection_bus: messenger.bus.default   # the bus of the projections' messages, in messenger mode
    dates:
        past: P30D             # how long before now a gesture may be dated; ~ for no bound
        ahead: PT5M            # how long after now a gesture may be dated
    privacy:
        member_kind: member    # a data subject "44" is the member "member:44"
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `store` | `doctrine` | `doctrine` garde le journal et les points de reprise dans la base de données ; `memory` les garde dans le processus, pour les tests |
| `connection` | `default` | La connexion DBAL, `doctrine.dbal.<name>_connection` ; l'écouteur du schéma y est lié |
| `verbs` | `[]` | Des motifs comme `game.played`, `game.*` ou `*` ; un motif erroné échoue à la compilation du conteneur ; un verbe non déclaré lève `UnknownVerb` à son enregistrement |
| `batch_size` | `500` | Au moins 1 |
| `projections` | `live` | `live` : chaque nouvelle entrée fait rattraper les projections qui la lisent, dans la même requête ; `messenger` : elle leur envoie un message `CatchUpProjection` ([Projections](projections.md)) ; `manual` : seule `tilttek:activity:catch-up` les fait rattraper |
| `projection_bus` | `messenger.bus.default` | Le bus de Messenger sur lequel les messages sont envoyés et traités, en mode `messenger` |
| `dates.past` | `P30D` | Une durée ISO 8601 : un geste daté plus tôt avant l'horloge lève `InvalidActivity` ; `~` lève la borne, pour une application qui importe un historique |
| `dates.ahead` | `PT5M` | Une durée ISO 8601 : un geste daté plus tard après l'horloge lève `InvalidActivity` |
| `privacy.member_kind` | `member` | La sorte de la référence que représente une personne concernée de `phil/privacy-bundle` |

Les services ne gardent aucun état entre les requêtes : ils sont sûrs dans un *worker*. Le journal et les points de
reprise en mémoire, pour les tests, se vident quand le noyau se réinitialise.
