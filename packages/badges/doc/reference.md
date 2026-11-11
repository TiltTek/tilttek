# Reference

| Type | Role |
|---|---|
| `Badge` | A recognition: code, texts, image, criterion, repeatable, hidden, revocable, tier |
| `Tier` | The series and rank of a badge |
| `Catalog` | The application's badges, their leaves by path, their series, the verbs they read |
| `Progress` | How far a member is towards a badge |
| `Standing` | A badge, a member's progress and how many times they hold it |
| `Contribution` | What one activity brought to one criterion of one badge |
| `Award` | A badge a member holds, with its number, moment, cause and whether it was awarded by hand |
| `Profile` | What a member shows: badges, showcase, title |
| `Showcase` | What anyone may see of a member's badges |
| `Erasure` | What erasing a member removed |
| `Evaluator` | Reads a member's progress from the contributions and the measures |
| `BadgeProjection` | The projection of `tilttek/activity` that awards and withdraws the badges |
| `Badges` | What the application calls: awards, progress, profile, showcase, sharing, consents, erasure |
| `AwardObserver` | Told when a badge is earned or withdrawn |
| `Measures` | The values the application measures for its members |
| `ShareChannel` | Where a member shares an achievement |
| `Criterion\Criterion`, `Criterion\Leaf`, `Criterion\Reading` | A criterion, a criterion that reads activity, what a member's activity brought |
| `Criterion\Scope` | The verbs, context and period a leaf reads |
| `Criterion\Count`, `Total`, `Streak`, `Measure`, `Manual`, `AtLeast` | The kinds of criterion |
| `Criterion\CriterionReader` | A criterion from plain values |
| `Store\ProgressStore`, `AwardStore`, `ProfileStore`, `GrantStore` | Where the library keeps its state |
| `Store\InMemory*Store` | The stores in memory |
| `Exception\BadgesException` and its exceptions | See [Exceptions](exceptions.md) |
