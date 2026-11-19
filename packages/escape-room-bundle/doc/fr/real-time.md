<!-- phil-dev source-sha256=547ad940af9c4697849ae5339caa79dae11ed65e8dd4bf3b8280fabd6dff1871 -->
# Temps réel

## Le hub

Avec Symfony Mercure installé, les écrans apprennent chaque coup par le hub par défaut de l'application, le service
`Symfony\Component\Mercure\HubInterface` qu'enregistre MercureBundle. Un autre hub se nomme par son service :

```yaml
tilttek_escape_room:
    mercure:
        hub: mercure.hub.rooms
        hub_name: rooms
```

`mercure: false` n'informe personne, et les écrans relisent la partie à intervalles.

## Les mises à jour

Chaque coup publie une mise à jour privée sur le sujet de sa partie — `tilttek-escape-room/games/{game}`, le préfixe
étant fixé par `mercure.topic_prefix` — dont les données ne nomment que le coup :

```json
{"move": "hint"}
```

Les coups sont ceux de `GameDesk::MOVES` : `start`, `hint`, `skip`, `pause`, `resume`, `message`, `clear`, `consent` et
`finish`. Aucun indice, message, équipe ni animateur n'est jamais envoyé : les écrans les lisent depuis le serveur, sous
l'accès de l'application. Un hub injoignable est journalisé et ne fait rien échouer.

## L'abonnement

Quand il produit le rendu de la console ou de l'écran d'indices, le bundle donne au spectateur le témoin d'autorisation
du sujet de la partie — par `Symfony\Component\Mercure\Authorization` — et l'URL à laquelle s'abonner, que les
contrôleurs Stimulus ouvrent avec `EventSource`. Le hub et l'application partagent le même domaine de deuxième niveau,
comme l'exigent les témoins de Mercure.

## Sans hub

Sans hub, les contrôleurs relisent l'écran toutes les `mercure.poll` secondes — 5 par défaut —, et l'écran d'indices
s'arrête une fois la partie terminée.
