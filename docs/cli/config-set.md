# unolia config set

Write one CLI option

```
USAGE
  unolia config set <key> [<value>] [flags]

ARGUMENTS
  key    Option name
  value  Value, empty to unset it

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
  # Always use JSON
  $ unolia config set format json
  # Forget a value
  $ unolia config set browser

LEARN MORE
  https://unolia.com/docs/cli/config-set
```
