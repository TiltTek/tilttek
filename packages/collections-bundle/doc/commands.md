# Commands

| Command | Does |
|---|---|
| `tilttek:collections:daily [--from=Y-m-d] [--until=Y-m-d] [--regenerate]` | Generates the calendar of the item of the day — thirty days from today by default — and shows each day's item and why; a day already generated keeps its item unless `--regenerate`, a day chosen always does |
| `tilttek:collections:grant <member> <item> --key=<key> [--source=granted] [--bound]` | Gives a member a copy under a key, once |
| `tilttek:collections:album <member>` | Shows the progress of each set and the items the member owns with their doubles |

A wrong member, item, key, source or day prints an error and returns 1.
