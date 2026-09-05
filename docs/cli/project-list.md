# unolia project list

List the projects you can reach

```
USAGE
  unolia project list [flags]

FLAGS
      --status <value>  Filter by status
      --q <value>       Filter by name or domain

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
  # Every project
  $ unolia project list
  # Active ones
  $ unolia project list --status active

LEARN MORE
  https://unolia.com/docs/cli/project-list
```
