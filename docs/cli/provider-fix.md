# unolia provider fix

Open the page where a provider connection is repaired

```
USAGE
  unolia provider fix <provider> [flags]

ARGUMENTS
  provider  Provider id

FLAGS
      --print  Print the URL instead of opening it

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
  # Repair a connection
  $ unolia provider fix 39
  # Only the URL
  $ unolia provider fix 39 --print

LEARN MORE
  https://unolia.com/docs/cli/provider-fix
```
