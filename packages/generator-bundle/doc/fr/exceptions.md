<!-- phil-dev source-sha256=f0f116e30bcd07cfc33466051818f5bde272398ce682d4e37d010888e594342f -->
# Exceptions

Les exceptions propres au bundle sont celles de l'accès à l'API et des clés des états ; les autres sont celles de
`tilttek/generator`, que les commandes écrivent et auxquelles l'API répond.

| Exception | Quand | Réponse de l'API |
|---|---|---|
| `Application\Exception\AccessDenied` | Un jeton absent ou inconnu (`tokenMissing`), ou un générateur qui n'est pas ouvert au client | 401, ou 403 |
| `Application\Exception\InvalidDrawRequest` | Un nombre, une unicité, un germe ou un état illisible | 400 |
| `Application\Exception\InvalidStateKey` | Une clé de `MemoryTables` ou de `--key` vide ou de plus de 200 caractères | — |
| `Tilttek\Generator\Exception\UnknownGenerator` | Un générateur qui n'existe pas | 404 |
| Toute autre `Tilttek\Generator\Exception\GeneratorException` | Un tirage qui échoue | 422 |

Un catalogue invalide (`InvalidDefinition` ou `InvalidCatalog`) échoue là où le générateur est construit pour la
première fois : le premier gabarit qui appelle `tilttek_draw`, la première requête à l'API, ou la commande, qui énumère
ses problèmes. `tilttek:generator:validate` les trouve avant.
