# unolia domain list

List the DNS zones of a project

```
USAGE
  unolia domain list [flags]

FLAGS
      --all-projects      Every zone of the team, not just this project
      --all-teams         Every zone your token can reach, across teams
      --provider <value>  Filter by DNS provider
      --q <value>         Filter by name

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
  # Zones of this project
  $ unolia domains
  # Every zone of the team
  $ unolia domains --all-projects
  # Names only
  $ unolia domains --json domain

LEARN MORE
  https://unolia.com/docs/cli/domain-list
```
