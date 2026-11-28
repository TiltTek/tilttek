# Resuming deliveries

A completion whose event handler threw stays undelivered until it is told again. The command, the message and the
schedule call `Sets::deliverPending()`, which tells the completions of the holders waiting the longest; a holder whose
delivery failed is passed by until the delay of the backoff is over, and repeated failures are told by a
`Tilttek\Sets\Event\DeliveryKeepsFailing` event.

## The command

```sh
bin/console tilttek:sets:deliver                      # the 100 holders waiting the longest
bin/console tilttek:sets:deliver --limit=1000         # up to 1 000 holders, at most 10 000
bin/console tilttek:sets:deliver --holder=member:1042 # every undelivered completion of one holder
```

It prints how many completions were delivered. When a handler throws, the other holders are told all the same and
the command exits with a failure that says how many completions were delivered and which exception was thrown first,
so that a cron job or a monitor sees it. `--limit` does not apply with `--holder`, which tells the holder at once,
postponed or not.

## The schedule

The `tilttek_sets` schedule sends a `DeliverPendingCompletions` message every five minutes, for the 100 holders
waiting the longest. A worker consumes the schedule through its transport, `scheduler_tilttek_sets`:

```sh
bin/console messenger:consume scheduler_tilttek_sets
```

A run missed while no worker consumed the schedule is run once, not once per interval. With a lock, two workers that
consume the schedule do not both send the message; without one, both may, which the idempotent handlers absorb. A run
whose handler threw ends with `DeliveryFailed`, which the worker logs; the next run tells the rest again.

```yaml
# config/services.yaml
services:
    app.sets_delivery_lock:
        class: Symfony\Component\Lock\LockInterface
        factory: ['@lock.factory', 'createLock']
        arguments: ['tilttek_sets_delivery']
```

## Routing the message

The message is handled by the worker that consumes the schedule. To handle it elsewhere, route it to another
transport:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletions: async
```

Without the Scheduler, a cron job that runs the command does the same.
