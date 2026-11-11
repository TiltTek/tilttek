<!-- phil-dev source-sha256=ec5b55df364aee76ac09bb9e704f57994e6b4fe2134cc19fdb64eace1beadbba -->
# Exceptions

Le bundle lève les exceptions de `tilttek/collections`, sous son interface `CollectionsException` :

| Quand | Exception | Message, par exemple |
|---|---|---|
| Le conteneur est compilé avec un catalogue erroné | `InvalidCollection`, `NotFound` | `No rarity is named "rare".` |
| Le fuseau horaire n'en est pas un | `InvalidCollection` | `"Mars/Olympus" is not a timezone.` |
| Une date calculée a besoin de `phil/almanac`, absent | `InvalidCollection` | `The computed dates of the item of the day need phil/almanac.` |
| Une date calculée ne peut pas être lue | `InvalidCollection` | `"someday" is not a rule the almanac reads.` |
| Une définition dans la base de données n'est pas du JSON | `InvalidCollection` | `The definition of the rarity "common" is not JSON: Syntax error.` |

Une valeur de configuration erronée — un stockage inconnu, une connexion vide, un objet du jour activé sans verbes —
est refusée par la configuration de Symfony avec une `InvalidConfigurationException`. Une colonne qui contient une
valeur inattendue lève une `UnexpectedValueException`. Un échange refusé par les collections parvient à
`tilttek/exchange` sous la forme de son `ExchangeRefused` avec `NotOwned`.
