# unolia deployment view

Show one deployment

```
USAGE
  unolia deployment view <deployment> [flags]

ARGUMENTS
  deployment  Deployment id

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
  # One deployment
  $ unolia deployment view 4812
  # Its status only
  $ unolia deployment view 4812 --json status

LEARN MORE
  https://unolia.com/docs/cli/deployment-view
```
