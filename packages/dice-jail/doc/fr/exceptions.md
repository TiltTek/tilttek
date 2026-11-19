<!-- phil-dev source-sha256=4b5332fc8ee8a06bcb763ae21ef40057d2c04cc44d2b71f5cfb80a2b8135ae6f -->
# Exceptions

Chaque exception implémente `Exception\DiceJailException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidRolls` | `InvalidArgumentException` | Une face que le dé ne peut pas montrer, plus de 1 000 000 de lancers, moins de 2 faces ou plus de 1 000, un intervalle vide, une distribution estimée ou tronquée, une face trop rare pour être jugée |
| `InvalidJailEntry` | `InvalidArgumentException` | Un gardien ou un dé vide, de plus de 128 octets, qui n'est pas en UTF-8 ou qui contient des caractères de contrôle ; un gardien avec `@` ; une raison de plus de 500 caractères ou avec des caractères de contrôle |
| `JailRefused` | `DomainException` | Un dé emprisonné deux fois, un dé libre libéré, une peine qui se termine maintenant ou avant, un gardien qui a 1 000 dés en prison |
| `JailConflict` | `RuntimeException` | Un autre rédacteur a ajouté à l'historique du dé avant vous |
