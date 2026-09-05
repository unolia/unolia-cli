# unolia incident view

Show one incident

```
USAGE
  unolia incident view <incident> [flags]

ARGUMENTS
  incident  Incident id

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
  # One incident
  $ unolia incident view 77
  # What deployment started it
  $ unolia incident view 77 --json origin_deployment

LEARN MORE
  https://unolia.com/docs/cli/incident-view
```
