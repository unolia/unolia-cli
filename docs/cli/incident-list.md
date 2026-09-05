# unolia incident list

List incidents

```
USAGE
  unolia incident list [flags]

FLAGS
      --status <value>  Filter by status, open by default
      --kind <value>    Filter by kind
      --since <value>   Only incidents since, such as 7d
      --all-projects    Every project of the team

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
  # Open incidents
  $ unolia incident list
  # Everything this week
  $ unolia incident list --status any --since 7d

LEARN MORE
  https://unolia.com/docs/cli/incident-list
```
