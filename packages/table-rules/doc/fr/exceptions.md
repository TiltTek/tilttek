<!-- phil-dev source-sha256=9b0584b943492c8381f3ed3ebe76c5268cb2280da01d7b118ea9fee327f65e06 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\TableRulesException` : attrapez-la pour les attraper toutes.

| Exception | Étend | Quand |
|---|---|---|
| `Exception\InvalidRules` | `InvalidArgumentException` | Une valeur hors de sa forme ou de ses bornes : un identifiant, un titre, un texte ou une note vide, trop long, de trop de lignes, en UTF-8 invalide ou contenant un caractère de contrôle, un numéro de version, une première version qui n'exige pas d'acceptation, une version sans règle ni variante, une règle ou une variante donnée deux fois, trop de règles, de variantes ou de caractères, une variante que le catalogue n'offre pas, un livre de règles dont la base de référence ou les décisions ne concordent pas avec ses versions, trop de joueurs. |
| `Exception\RulesRefused` | `DomainException` | Un changement non permis sur le livre de règles dans son état : une version identique à celle qui fait foi, le nombre maximal de versions, une réponse à une version qui ne fait plus foi, le nombre maximal de joueurs. |
| `Exception\RulesNotFound` | `RuntimeException` | La table n'a encore aucune règle publiée, ou aucune version de ce numéro. |
| `Exception\RulesConflict` | `RuntimeException` | Le livre de règles a changé depuis sa lecture : le changement n'est pas gardé, et il est refait sur ce qu'on relit. |
