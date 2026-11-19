# Explanations

`Statistics\Explanation::explain()` writes a `FitResult` for the table, in a `Language` — `English` or `French`;
`Language::ofLocale()` reads `fr`, `fr_CA` or `fr-CA` — and for an `Origin`: `Physical`, a die thrown at the table,
or `Digital`, rolls drawn by software.

```php
new Explanation()->explain($result, Language::French, Origin::Digital);
```

| Verdict | English |
|---|---|
| Not enough rolls | Not enough rolls to judge this die yet: 29 recorded, 30 needed. Until then, a streak is only chance. |
| Nothing unusual | Nothing unusual in 30 rolls: a fair die spreads its faces at least this unevenly 8.2% of the time (p = 0.0821). |
| Somewhat unusual | Somewhat unusual in 30 rolls: a fair die spreads its faces this unevenly about once in 181 series (p = 0.0055). It proves nothing; roll it again and judge the new rolls alone. |
| Very unusual, physical | Very unusual in 30 rolls: … fewer than once in 1,000 series (p = 0.0004). It is a reason to look at the die — its edges, its balance, how it is thrown — not proof that it is loaded. |
| Very unusual, digital | … Test new rolls before suspecting anything: chance remains the likeliest cause. |

The average roll follows, against the fair one: « Average roll: 1.67, against 2.00 for a fair die. » For digital
dice, a last sentence says that the generator serves every die alike and that the jail is only a game.

The French sentences say the same, with French typography: a comma for decimals, a narrow no-break space between
thousands, a no-break space before « : » and « % », a narrow one before « ; ». `Explanation::pValue()` writes a
p-value alone: `p = 0.0407`, or `p < 0.0001` below it.

What the sentences never say: that a die is loaded, cursed or cheating; that a member cheats; that a p-value is the
chance of anything about the die.
