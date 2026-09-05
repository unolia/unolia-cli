# unolia team switch

Choose the default team

```
USAGE
  unolia team switch [<team>] [flags]

ARGUMENTS
  team  Team slug

FLAGS
      --local  Write it into .unolia/config.json instead

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
  # Everywhere
  $ unolia team switch acme
  # This repository only
  $ unolia team switch acme --local

LEARN MORE
  https://unolia.com/docs/cli/team-switch
```
