# Recording activity

Inject `Tilttek\Activity\Journal` where a gesture happens — an event listener, a Messenger handler, a controller — and
record the gesture with a key built from what makes it unique:

```php
$this->journal->record(Activity::of(
    'game:'.$game->id.':player:'.$player->id,
    'member:'.$player->id,
    'game.played',
    $game->endedAt,
    'game:'.$game->id,
    context: ['system' => $game->system],
));
```

A listener that runs twice, a message delivered again or a command run again records nothing more: the key counts
once. When the gesture is undone, cancel it by the same key:

```php
$this->journal->cancel('order:'.$order->number.':paid');
```

The verbs of `tilttek_activity.verbs` are checked before anything is written. See the library's documentation for the
activity, the key, the context and the observers.
