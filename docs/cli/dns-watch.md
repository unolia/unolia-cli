# unolia dns watch

Watch a DNS record until it propagates

```
USAGE
  unolia dns watch <record> [<type>] [flags]

ARGUMENTS
  record  Record id, or its name
  type    Record type, when the name alone is not enough

FLAGS
      --zone <value>      The zone, the project's one by default
      --once              Check once and stop
      --interval <value>  Seconds between checks (default 2)
      --timeout <value>   Give up waiting after this long (default 30s)
      --notify            Send a desktop notification at the end

INHERITED FLAGS
  -t, --team <value>     Team slug or id
  -p, --project <value>  Project id or name
  -w, --website <value>  Website id or domain
      --json [<value>]   JSON output, optionally a comma list of fields
      --format <value>   table, json, ndjson, csv or yaml
      --jq <value>       Filter the JSON output through jq
  -y, --yes              Skip confirmations
      --no-input         Never prompt, fail instead
      --dry-run          Show the plan without changing anything
      --paginate         Follow every page
      --limit <value>    Page size, up to 100 (default 30)

EXAMPLES
  # Watch a record
  $ unolia dns watch www A
  # Check it once
  $ unolia dns watch 88231 --once

LEARN MORE
  https://unolia.com/docs/cli/dns-watch
```
