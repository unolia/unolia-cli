# unolia automation watch

Follow an automation run

```
USAGE
  unolia automation watch [<run>] [flags]

ARGUMENTS
  run  Run ULID or its short id, the last six characters. The run going now, or the last one, by default

FLAGS
      --interval <value>  Seconds between checks (default 3)
      --timeout <value>   Give up waiting after this long (default 15m)
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
      --page <value>     The page to show, when a list says there is more

EXAMPLES
  # Follow the run going now, or read the last one back
  $ unolia automation watch
  # Follow a run
  $ unolia automation watch PC0XCA
  # As events
  $ unolia automation watch PC0XCA --format ndjson

LEARN MORE
  https://unolia.com/docs/cli/automation-watch
```
