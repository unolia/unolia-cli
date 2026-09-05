# unolia ci watch

Follow a CI run until it finishes

```
USAGE
  unolia ci watch [<run>] [flags]

ARGUMENTS
  run  Action id or #run number, the newest on this branch by default

FLAGS
      --repo <value>      Repository id or full name
      --all-branches      Look at every branch when picking the newest run
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

EXAMPLES
  # Follow this branch
  $ unolia ci watch
  # As events
  $ unolia ci watch --format ndjson

LEARN MORE
  https://unolia.com/docs/cli/ci-watch
```
