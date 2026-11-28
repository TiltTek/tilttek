# Exceptions

Every exception of the library implements `Exception\RandomCharacterException`: catch it to catch them all.

| Exception | Extends | Raised when |
|---|---|---|
| `InvalidRules` | `InvalidArgumentException` | Rules, stats or a field break a rule when they are built; rules rolled while `check()` finds problems, every problem listed. |
| `InvalidDocument` | `InvalidArgumentException` | A document cannot be read; the message starts with the place of the field. |
| `InvalidSeed` | `InvalidArgumentException` | A seed is empty, not in UTF-8, or longer than 256 characters. |
| `InvalidEdit` | `InvalidArgumentException` | A character is asked for, or given, a value it does not have, or a value out of bounds. |
| `UnknownRules` | `OutOfBoundsException` | A catalogue holds no rules of the key. |
| `RollFailed` | `RuntimeException` | A stat falls outside its bounds, a roll gives no number or exceeds its limits, a generator fails or gives too few different results, or a field's value cannot name a generator. |

Some messages:

```text
The creation rules "lanterns.wanderer" cannot be rolled:
- The attribute "luck" is not one of the system "lanterns".
- The field "name" draws from the generator "lanterns.names", which is unknown.
The stats gave 7 to the attribute "grit", outside its bounds of 1 to 6.
The field "name" names its generator by "{lineage}", which gave no text.
The generator "lanterns.trait" gave 1 of the 2 different results the field "traits" asks for.
The field "name" names its generator by the field "lineage", which is not before it.
rules[0].fields[3].count: a whole number is expected.
```

A `RollFailed` caused by `tilttek/generator` or `tilttek/dice` keeps their exception as its previous one.
