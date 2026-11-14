<!-- phil-dev source-sha256=e86208384440da56979f9dd155d779bc1d45e59b275d8f7cf01eddef520ab5f2 -->
# API de tirage

## La route

`GET <prefix>/{generator}` (`tilttek_generator_draw`) fait un tirage dans un générateur et répond par ses résultats en
JSON, jamais par ses listes :

```text
GET /api/generators/tavern?count=2&unique=1&seed=table-7
Authorization: Bearer <token>
```

```json
{"generator": "tavern", "results": [{"name": "…", "keeper": "…", "menu": ["…"]}, {"…": "…"}], "requested": 2, "short": false}
```

| Paramètre | Signification |
|---|---|
| `count` | Un nombre entier de 1 à `api.max_count` ; 1 par défaut. |
| `unique` | `1`, `true`, `0` ou `false` ; faux par défaut. |
| `seed` | Un texte de 1 à 128 caractères, en UTF-8, qui fait revenir le même tirage. |
| `state` | Pour une table à mémoire, l'état que sa dernière réponse a donné, comme `state[day]=3&state[season]=winter` : au plus 32 noms vers des textes d'au plus 1024 caractères, en UTF-8. |

La réponse donne les attributions des listes, des entrées et des générateurs que ses résultats ont utilisés, quand ils
en ont, chacune avec les textes qu'elle a : `"attributions": [{"source": "Old tables", "licence": "CC-BY-4.0", "attribution": "Tables by A. Author"}]` ;
un client les montre avec les résultats.

Une table à mémoire répond son nouvel état avec ses résultats (`"state": {"day": "4", "season": "winter"}`), et le
client le renvoie avec son tirage suivant. L'API ne garde aucun état : un site qui veut que le bundle le garde appelle
`MemoryTables` dans son propre contrôleur, derrière ses propres règles d'accès ([Tables à mémoire](memory.md)).

Les réponses portent `Cache-Control: no-store`. Une erreur est un détail de problème, `application/problem+json`, avec
`status` et `detail` :

| Statut | Quand |
|---|---|
| 404 | L'API est désactivée, ou le générateur n'existe pas. |
| 401 | Aucun jeton, un jeton qui n'est pas `Bearer`, ou un jeton inconnu, pour un générateur qui n'est pas public. |
| 403 | Un client connu demande un générateur qui ne lui est pas ouvert. |
| 400 | Un nombre, une unicité, un germe ou un état illisible, ou un nom, un germe ou un état qui n'est pas en UTF-8. |
| 429 | Au-delà de la limite de débit, avec `Retry-After` en secondes. |
| 422 | Un tirage qui échoue : un gabarit qui échoue, une liste épuisée, une limite. Le tirage d'un client en dit la cause ; un tirage public répond `The draw failed.`, sans les noms des listes et des gabarits. |

## Accès

L'application choisit ce qu'elle ouvre, à qui et sous quelles limites :

- **rien** tant que `api.enabled` est faux ;
- **les générateurs publics** de `api.public` à tout le monde, sans jeton, comptés par adresse de l'appelant contre
  `api.rate_limiter`, ou contre la limite propre du bundle, `api.public_limit` (soixante tirages par minute), quand
  aucun n'est nommé ;
- **les générateurs de chaque client** au client qui envoie son jeton sous la forme `Authorization: Bearer <token>`,
  comptés par client contre son propre limiteur de débit. Une requête avec un jeton est toujours celle d'un client : un
  jeton inconnu est refusé, même pour un générateur public.

Les jetons sont comparés en temps constant, et le bundle ne les écrit jamais dans un journal. Derrière un mandataire,
l'adresse est celle que Symfony lit dans `Request::getClientIp()`, qui suit les `trusted_proxies` de l'application.

La route n'a pas de pare-feu à elle : une application qui veut sa propre authentification place le préfixe derrière son
pare-feu et garde l'accès de l'API pour ce qu'il ajoute.
