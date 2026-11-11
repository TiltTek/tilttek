# Exceptions

Every exception of the library implements `Tilttek\Badges\Exception\BadgesException`: catch it to catch them all.

| Exception | Extends | Raised when |
|---|---|---|
| `InvalidBadge` | `InvalidArgumentException` | A badge, criterion, tier, catalog, progress, award, profile or application name does not hold together; the message says what is wrong |
| `UnknownBadge` | `OutOfBoundsException` | A code the catalog does not hold: `No badge is named "gold".` |
| `NotEarned` | `DomainException` | A badge the member has not earned is shown, featured, worn, shared or issued: `member:44 has not earned the badge "gold".` |
| `AccessDenied` | `RuntimeException` | Another application reads a member's badges without their consent: `member:44 has not allowed "portfolio" to read their badges.` |
