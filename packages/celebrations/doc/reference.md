# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Celebrations` | Receives, withdraws, groups, counts, celebrates, marks seen, reads and erases the members' inboxes |
| `Achievement` | What a member earned: source, kind, subject, occurrence, moment, parameters, moment to stop being shown |
| `Entry` | An achievement in an inbox: sequence, state, received and settled moments |
| `EntryState` | `Pending`, `Seen`, `Withdrawn` or `Lapsed` |
| `Group` | The pending entries of one source and one kind: the first shown, the count |
| `Celebration` | What the window shows next: a group, the motion, the sound, the groups remaining |
| `Preferences` | Whether a window celebrates, its motion and its sound, for a member |
| `Motion` | `Full`, `Reduced` or `None` |
| `Limits` | Pending entries and entries per member, entries shown per group, the history page |
| `CelebrationChanged` | Told after each act, identifiers only |
| `Change` | What happened in an inbox |
| `Contract\CelebrationStore` | Where inboxes and preferences are kept |
| `Contract\CelebrationObserver` | What the application does with the events |
| `Store\InMemoryCelebrationStore` | The store in memory |
| `Exception\CelebrationException` | Every exception of the library |
| `Exception\InvalidCelebration` | A value out of bounds |
