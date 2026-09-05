# unolia automation list

List the automations of this team

```
USAGE
  unolia automation list [flags]

FLAGS
      --state <value>   Filter by state
      --recipe <value>  Filter by recipe slug
      --q <value>       Filter by name

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
  # Every automation
  $ unolia automation list
  # Active ones
  $ unolia automation list --state active

LEARN MORE
  https://unolia.com/docs/cli/automation-list
```
