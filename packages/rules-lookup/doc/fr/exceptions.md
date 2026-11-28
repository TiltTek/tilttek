<!-- phil-dev source-sha256=ab32edee2026492568f0315f39cf842f83a583e5b69fb00f7facce9eea8b5f1a -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\RulesLookup\Exception\RulesLookupException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidRule` | Une règle, un identifiant, un renvoi, un dépôt ou un message est refusé à sa construction : une clé, une version, un titre manquant, un renvoi à elle-même, deux règles de même identifiant | `The rule "a:b" refers to itself.` |
| `InvalidQuery` | Une requête sort de ses bornes : texte trop long ou pas en UTF-8, trop de mots, ni mots ni catégorie, une locale, un filtre ou une page refusés | `A search holds at most 16 words.` |

Les textes d'une règle sont des `Translations` de `tilttek/rpg-systems`, qui refusent un texte de leur propre chef avec
leur `InvalidRecord`.
