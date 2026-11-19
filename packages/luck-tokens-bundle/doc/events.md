# Events

`LuckTokens` dispatches its events to the application's event dispatcher, once their entries are written, never for an
operation sent again:

| Event | When |
|---|---|
| `Tilttek\LuckTokens\Event\TokensGranted` | Tokens granted |
| `Tilttek\LuckTokens\Event\TokensSpent` | Tokens spent |
| `Tilttek\LuckTokens\Event\TokensTransferred` | Tokens passed from one holder to another |
| `Tilttek\LuckTokens\Event\SessionStarted` | A session started, with its refreshes |
| `Tilttek\LuckTokens\Event\HolderErased` | A member erased under a pseudonym; it names neither the member nor a table |

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\LuckTokens\Event\TokensSpent;

#[AsEventListener]
final readonly class OfferTheReroll
{
    public function __invoke(TokensSpent $spent): void
    {
        // $spent->entry->table, ->holder, ->kind, ->reason, ->operation, ->position
    }
}
```

A listener runs inside the request that wrote the entries. Work that takes time — a notification, a push to the table's
screen — goes through Messenger with the table, the operation and the position only: the handler reads the entry back
from the ledger, so that a message carries no personal data.
