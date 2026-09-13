# unolia provider list

List the connected providers

```
USAGE
  unolia provider list [flags]

FLAGS
      --attention  Only providers that need attention

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
  # Every provider
  $ unolia provider list
  # What needs attention
  $ unolia provider list --attention

LEARN MORE
  https://unolia.com/docs/cli/provider-list
```
