# unolia automation view

Show one automation and its last runs

```
USAGE
  unolia automation view <automation> [flags]

ARGUMENTS
  automation  Automation id, or any part of its name

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
  # One automation
  $ unolia automation view 7
  # By name
  $ unolia automation view "Update Ubuntu servers"

LEARN MORE
  https://unolia.com/docs/cli/automation-view
```
