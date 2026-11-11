# Projections and commands

## Writing a projection

A service that implements `Tilttek\Activity\Projection\Projection` is tagged `tilttek_activity.projection` by
autoconfiguration and joins `Projections`. Its checkpoint is kept in `tilttek_activity_checkpoint` under its name. The
projection keeps its own state where it wants — its own table, a cache —, and must tolerate seeing an entry twice
after a crash (see the library's documentation).

## Live projections

With `projections: live`, the default, each new entry catches up, in the same request, every projection whose filter
reads it. A failing projection lets its exception through after the entry is written, and its checkpoint stays before
the entry.

## Projections through Messenger

With `projections: messenger`, each new entry sends, for every projection whose filter reads it, a
`CatchUpProjection` message on the `projection_bus`; `CatchUpProjectionHandler` catches that projection up. The
request only writes the entry and sends the message: route the message to an asynchronous transport, consumed by one
worker so that a projection never runs twice at the same time.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            projections: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjection: projections
```

```sh
bin/console messenger:consume projections --limit=1000
```

The bundle routes nothing itself: the transport, its retries and the number of its consumers are the application's
choice, written in its own `framework.messenger.routing`. Without a route, Messenger handles the message on the bus
when it is sent, in the request, as live projections do.

The message carries identifiers only: the projection's name and the entry's position. The activity stays in the
journal, which the handler reads.

- **The journal is the outbox.** The entry is written before the message is sent: a message lost on the way is made up
  by the projection's next message, which catches it up past every entry it has not seen, or by
  `tilttek:activity:catch-up`.
- **The checkpoints are the inbox.** A message delivered twice, or after a later one, finds the projection already past
  its entry and applies nothing; a message for a projection the application no longer has is dropped.

Without `symfony/messenger`, `projections: messenger` fails when the container is compiled.

## Manual projections

With `projections: manual`, nothing follows the journal as it is written: run `tilttek:activity:catch-up` from a
scheduled task.

## Commands

| Command | Does |
|---|---|
| `tilttek:activity:catch-up [projection…]` | Hands every projection, or those named, the entries it has not seen, and says how many each applied |
| `tilttek:activity:rebuild <projection> [--since=2026-11-01]` | Resets the projection from the date on — entirely without it — and replays that period of the journal |
| `tilttek:activity:status` | Lists the projections and how many entries each has yet to see |

An unknown projection or a date that does not parse ends the command with an error and changes nothing. Run one
catch-up or rebuild of a projection at a time.
