# Exact numbers

## `Natural`

A natural number of any size, zero or more: the count of the ways to roll thirty dice does not fit a PHP integer. It
is kept in limbs of nine decimal digits and written back in decimal.

| Member | What it does |
|---|---|
| `Natural::of($int)`, `Natural::parse($digits)`, `zero()`, `one()` | Builds a number; a negative integer or other text than digits is refused |
| `add()`, `subtract()`, `multiply()`, `power()` | Arithmetic; a subtraction below zero and a negative exponent are refused |
| `divide($divisor)` | The quotient and the remainder; a division by zero is refused |
| `gcd()` | The greatest common divisor |
| `compare()`, `equals()`, `isZero()` | Comparisons |
| `toInt()` | The number as an integer when it fits one, null otherwise |
| `Natural::ratio($a, $b)` | `a / b` as a float, to a float's precision however large both are |

## `Fraction`

An exact rational number, signed, over the denominator it was computed over: `16/36` stays `16/36`, so that a
probability reads over the number of ways to roll; `reduced()` gives `4/9`, and `over($denominator)` writes it over a
multiple of its lowest denominator.

| Member | What it does |
|---|---|
| `numerator`, `denominator`, `negative` | Its parts; zero is never negative |
| `Fraction::of($numerator, $denominator)`, `zero()`, `one()` | Builds a fraction; a zero denominator is refused |
| `add()`, `subtract()`, `multiply()`, `negate()`, `power()` | Arithmetic, exact |
| `complement()` | One minus the fraction, over the same denominator when it can: `20/36` from `16/36` |
| `compare()`, `equals()`, `isZero()` | Comparisons by value: `16/36` equals `4/9` |
| `toFloat()` | A float, for a percentage |
| `(string)` | `16/36`, `-7/2`, `3/1` |

The library's invalid arguments throw PHP's `InvalidArgumentException`.
