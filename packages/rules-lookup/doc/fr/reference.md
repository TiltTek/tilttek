<!-- phil-dev source-sha256=7abb6a3b28bb284dd88bcfcbee86e17be14d8333f2b3f4deca1832ee4c63709f -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Rule` | Une règle : identifiant, titres et textes par locale, catégorie, renvois et source |
| `RuleId` | L'identifiant d'une règle : système, clé et version, écrit `system@version:key` |
| `RuleQuery` | Une recherche : mots normalisés, locale, système, version, catégorie et page |
| `RuleMatch` | Une règle qu'un dépôt a trouvée, avec son score |
| `RuleMatches` | Une page de correspondances et leur total |
| `RuleHit` | Une règle comme un lecteur la voit dans les résultats, avec la mention de sa source |
| `SearchResults` | Une page de résultats, et les mentions de ses sources en texte ou en HTML |
| `RulesLookup` | Cherche, trouve une règle par son identifiant, et suit les renvois dans les deux sens |
| `RulesFromSystem` | Les règles d'un système de `tilttek/rpg-systems` |
| `Contract\RuleStore` | Le port du dépôt : lire, chercher, et les règles qui renvoient à une règle |
| `Store\InMemoryRuleStore` | Le dépôt en mémoire, indexé une fois, jamais modifié |
| `Message\ReindexRules` | Le message d'un système dont les règles ont changé, avec sa clé et sa version |
| `Exception\RulesLookupException` et ses deux exceptions | [Exceptions](exceptions.md) |
