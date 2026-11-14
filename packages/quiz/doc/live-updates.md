# Live updates

The screens of a live session — the host's, the shared screen, each player's phone — follow its moves as they happen.
`Mercure\LiveSessionPublisher` pushes them through Mercure, as private updates that name the session and the position
of a question, never a player, a pseudonym nor an answer: each screen that hears an update reads what it may show from
the application, with its own rights. It is optional, and needs `symfony/mercure`:

```sh
composer require symfony/mercure
```

## Wiring the publisher

```php
use Tilttek\Quiz\Mercure\LiveSessionPublisher;

$publisher = new LiveSessionPublisher(
    $hub,                                   // Symfony\Component\Mercure\HubInterface
    'https://example.com/quiz/live/',       // the topic prefix; the session's identifier follows it
);
```

Each method listens to one event of the library:

| Method | Event | Data of the update |
|---|---|---|
| `playerJoined()` | `PlayerJoined` | `{"type": "player-joined", "session": …}` |
| `questionShown()` | `QuestionShown` | `{"type": "question-shown", "session": …, "position": …}` |
| `questionClosed()` | `QuestionClosed` | `{"type": "question-closed", "session": …, "position": …}` |
| `liveQuizFinished()` | `LiveQuizFinished` | `{"type": "finished", "session": …}` |

In a Symfony application, register them on the event dispatcher:

```yaml
# config/services.yaml
Tilttek\Quiz\Mercure\LiveSessionPublisher:
    arguments: ['@mercure.hub.default', '%env(QUIZ_TOPIC_PREFIX)%']
    tags:
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\PlayerJoined, method: playerJoined }
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\QuestionShown, method: questionShown }
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\QuestionClosed, method: questionClosed }
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\LiveQuizFinished, method: liveQuizFinished }
```

## Subscribing

`topic($session)` gives the topic of a session: the prefix followed by the session's identifier, encoded for a URL.
The updates are private: the application gives each screen a subscriber token for its session's topic only, for
instance with the `mercure()` function of Symfony's Twig extension, once it checked that the member or the guest joined
that session. A screen that hears `question-shown` fetches the question from the application and starts its timer from
the deadline the application gives, never from the moment the update arrived.

The publisher keeps nothing between two calls: one instance serves every request of a worker.
