# unolia automation list

List the automations of this team

```
USAGE
  unolia automation list [flags]

FLAGS
      --state <value>   Filter by state; every state but archived by default, any for all
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
      --page <value>     The page to show, when a list says there is more

EXAMPLES
  # Every automation but the archived
  $ unolia automation list
  # The archived ones too
  $ unolia automation list --state any
  # Paused ones
  $ unolia automation list --state paused

LEARN MORE
  https://unolia.com/docs/cli/automation-list
```
