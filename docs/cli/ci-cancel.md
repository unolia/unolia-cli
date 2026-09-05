# unolia ci cancel

Cancel a CI run

```
USAGE
  unolia ci cancel [<run>] [flags]

ARGUMENTS
  run  Action id or #run number, the newest by default

FLAGS
      --repo <value>  Repository id or full name
      --all-branches  Look at every branch when picking the newest run

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
  # Cancel the newest run
  $ unolia ci cancel
  # Cancel a run number
  $ unolia ci cancel "#1187" --yes

LEARN MORE
  https://unolia.com/docs/cli/ci-cancel
```
