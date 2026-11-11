<!-- phil-dev source-sha256=da45ba4f7046553d47295de13dcc0d90c9b03c42bc256ffbe1ed0b4c2ad6e80f -->
# Exceptions

Le bundle lève les exceptions de la bibliothèque — voir `doc/exceptions.md` de `tilttek/badges` — ainsi que :

| Exception | Quand |
|---|---|
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | Une valeur de configuration hors de ses bornes, ou Open Badges activé sans que `PhilOpenBadgesBundle` soit enregistré ou sans l'URL de la réalisation |
| `Tilttek\Badges\Exception\InvalidBadge` | À la compilation du conteneur, un badge ou un fuseau horaire de la configuration qui n'est pas cohérent |
| `UnexpectedValueException` | Une ligne dont une colonne ne contient pas le type qu'écrivent les stockages |
