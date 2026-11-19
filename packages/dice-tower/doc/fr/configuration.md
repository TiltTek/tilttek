<!-- phil-dev source-sha256=c3ec6df9141937a4845ee0f0fde484044d2c9ee5a47e4885e417f5202640f852 -->
# Configuration

```yaml
# config/packages/tilttek_dice_tower.yaml
tilttek_dice_tower:
    history: 20
    topic_prefix: 'urn:tilttek:dice-tower:'
    table_access: ~
    hub: Symfony\Component\Mercure\HubInterface
    hub_name: ~
    message_bus: ~
    store: doctrine
    entity_manager: default
    kit_subdirectory: ~
```

| Clé | Valeur par défaut | Ce qu'elle règle |
|---|---|---|
| `history` | `20` | Combien d'entrées un spectateur lit d'un coup, de 1 à 200 : les dernières quand la surcouche est dessinée, et celles qu'il a manquées quand la connexion revient |
| `topic_prefix` | `urn:tilttek:dice-tower:` | Ce qui précède la référence d'une table, encodée pour une URL, dans le sujet de sa tour |
| `table_access` | `null` | Le service, un `TableAccess`, qui dit si le spectateur courant peut regarder une table ; personne ne le peut si la valeur est nulle |
| `hub` | `Symfony\Component\Mercure\HubInterface` | Le service du hub Mercure où chaque nouvelle entrée est poussée ; une valeur nulle, ou un service qui n'existe pas, ne pousse rien |
| `hub_name` | `null` | Le nom du hub pour lequel est le témoin d'autorisation, quand l'application en a plusieurs |
| `message_bus` | `null` | Le bus de messages par lequel les relais sont joints ; ils sont appelés pendant le jet si la valeur est nulle |
| `store` | `doctrine` | Où le journal est gardé : `doctrine`, ou `memory` pour les tests |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine du journal |
| `kit_subdirectory` | `null` | L'endroit où sont les recettes du kit UX, sous le répertoire des composants ; une valeur nulle prend le sous-répertoire de la configuration `ux_toolkit` |

Les sujets sont privés : le hub ne livre une entrée qu'à un abonné dont le jeton nomme le sujet de la table. Le bundle
donne ce jeton, dans le témoin d'autorisation de `symfony/mercure-bundle`, à un spectateur autorisé quand la surcouche
est dessinée, pour cette table seulement.

La surcouche lit le sous-répertoire du Symfony UX Toolkit quand le conteneur est compilé. Dans un environnement où le
Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans `kit_subdirectory`.

Les services ne gardent aucun état entre les requêtes : le journal est lu depuis son stockage à chaque requête, et le
stockage en mémoire est vidé entre deux requêtes d'un processus de longue durée.
