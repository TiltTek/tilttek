# Commands

When `symfony/console` is installed:

| Command | What it does |
|---|---|
| `tilttek:exchange:expire` | Expires the reservations past their lifetime, giving their points back, and — when trades are enabled — the offers past theirs, freeing their items. Prints `2 reservations and 1 offers expired.` A scheduler runs it. |
| `tilttek:exchange:transfers [member] [--limit=50]` | Shows the journal of transfers: the latest of every member, newest first, at most 1000, or every transfer a member gave or received, like `member:44`. Exists when transfers are enabled. |
