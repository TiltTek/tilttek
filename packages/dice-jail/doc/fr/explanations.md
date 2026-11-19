<!-- phil-dev source-sha256=9c252478e96f0095410d7efa6a00fd22c048761f1d8de5c330ce90f5c261bab6 -->
# Les explications

`Statistics\Explanation::explain()` écrit un `FitResult` pour la table, dans une `Language` — `English` ou `French` ;
`Language::ofLocale()` lit `fr`, `fr_CA` ou `fr-CA` — et pour une `Origin` : `Physical`, un dé lancé à la table, ou
`Digital`, des lancers tirés par un logiciel.

```php
new Explanation()->explain($result, Language::French, Origin::Digital);
```

| Verdict | Anglais |
|---|---|
| Pas assez de lancers | Not enough rolls to judge this die yet: 29 recorded, 30 needed. Until then, a streak is only chance. |
| Rien d'inhabituel | Nothing unusual in 30 rolls: a fair die spreads its faces at least this unevenly 8.2% of the time (p = 0.0821). |
| Plutôt inhabituel | Somewhat unusual in 30 rolls: a fair die spreads its faces this unevenly about once in 181 series (p = 0.0055). It proves nothing; roll it again and judge the new rolls alone. |
| Très inhabituel, physique | Very unusual in 30 rolls: … fewer than once in 1,000 series (p = 0.0004). It is a reason to look at the die — its edges, its balance, how it is thrown — not proof that it is loaded. |
| Très inhabituel, numérique | … Test new rolls before suspecting anything: chance remains the likeliest cause. |

Le lancer moyen suit, à côté de celui d'un dé équitable : « Average roll: 1.67, against 2.00 for a fair die. » Pour
les dés numériques, une dernière phrase dit que le générateur sert tous les dés de la même façon et que la prison n'est
qu'un jeu.

Les phrases françaises disent la même chose, avec la typographie française : une virgule pour les décimales, une espace
insécable fine entre les milliers, une espace insécable avant « : » et « % », une espace fine avant « ; ».
`Explanation::pValue()` écrit une valeur p seule : `p = 0.0407`, ou `p < 0.0001` en dessous.

Ce que les phrases ne disent jamais : qu'un dé est pipé, maudit ou triche ; qu'un membre triche ; qu'une valeur p est la
probabilité de quoi que ce soit au sujet du dé.
