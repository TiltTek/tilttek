# Exceptions

Every exception implements `Exception\DiceJailException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidRolls` | `InvalidArgumentException` | A face the die cannot show, more than 1 000 000 rolls, fewer than 2 or more than 1 000 faces, an empty range, an estimated or cut distribution, a face too rare to judge |
| `InvalidJailEntry` | `InvalidArgumentException` | A keeper or a die empty, longer than 128 bytes, not UTF-8 or with control characters; a keeper with `@`; a reason over 500 characters or with control characters |
| `JailRefused` | `DomainException` | A die jailed twice, a free die released, a sentence ending now or before, a keeper with 1 000 dice in jail |
| `JailConflict` | `RuntimeException` | Another writer appended to the die's history first |
