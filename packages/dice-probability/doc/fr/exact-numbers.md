<!-- phil-dev source-sha256=484d75c153c62d670e803763d04500bb0a248cb5f59cb1abfa9d908a08a0ae97 -->
# Nombres exacts

## `Natural`

Un nombre naturel de toute taille, zéro ou plus : le nombre de façons de lancer trente dés ne tient pas dans un entier
PHP. Il est gardé en tranches de neuf chiffres décimaux et réécrit en décimal.

| Membre | Ce qu'il fait |
|---|---|
| `Natural::of($int)`, `Natural::parse($digits)`, `zero()`, `one()` | Construit un nombre ; un entier négatif ou un texte autre que des chiffres est refusé |
| `add()`, `subtract()`, `multiply()`, `power()` | Arithmétique ; une soustraction sous zéro et un exposant négatif sont refusés |
| `divide($divisor)` | Le quotient et le reste ; une division par zéro est refusée |
| `gcd()` | Le plus grand commun diviseur |
| `compare()`, `equals()`, `isZero()` | Comparaisons |
| `toInt()` | Le nombre en entier quand il y tient, null sinon |
| `Natural::ratio($a, $b)` | `a / b` en nombre à virgule flottante, avec la précision d'un flottant, quelle que soit la taille des deux nombres |

## `Fraction`

Un nombre rationnel exact, signé, sur le dénominateur qui a servi à le calculer : `16/36` reste `16/36`, pour qu'une
probabilité se lise sur le nombre de façons de lancer. `reduced()` donne `4/9`, et `over($denominator)` l'écrit sur un
multiple de son plus petit dénominateur.

| Membre | Ce qu'il fait |
|---|---|
| `numerator`, `denominator`, `negative` | Ses parties ; zéro n'est jamais négatif |
| `Fraction::of($numerator, $denominator)`, `zero()`, `one()` | Construit une fraction ; un dénominateur nul est refusé |
| `add()`, `subtract()`, `multiply()`, `negate()`, `power()` | Arithmétique exacte |
| `complement()` | Un moins la fraction, sur le même dénominateur quand c'est possible : `20/36` à partir de `16/36` |
| `compare()`, `equals()`, `isZero()` | Comparaisons par valeur : `16/36` est égal à `4/9` |
| `toFloat()` | Un nombre à virgule flottante, pour un pourcentage |
| `(string)` | `16/36`, `-7/2`, `3/1` |

Les arguments invalides de la bibliothèque lèvent l'`InvalidArgumentException` de PHP.
