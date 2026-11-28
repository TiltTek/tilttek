# Testing

The unit suite, under `tests/Unit`, builds the container of the bundle without a kernel: the services of the defaults
and of a configuration, the stores in memory, the backoff, an integration left out when its component is missing, the
schedule disabled, the bounds refused; the command, with its limit, its holder, a failing handler and a holder that
does not read; the message, its handler and the schedule, with its interval, its limit and its lock.

The functional suite, under `tests/Functional`, boots an application with FrameworkBundle, Messenger and the
Scheduler: a completion recorded and told, the command found by the console, the transport of the schedule and the
message handled by its handler.

In an application, `InMemoryDefinitionStore` and `InMemoryCompletionStore`, the defaults when no store is named, keep
the tests away from a database.
