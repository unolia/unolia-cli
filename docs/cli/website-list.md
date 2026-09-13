# unolia website list

List the websites of a project

```
USAGE
  unolia website list [flags]

FLAGS
      --all-projects      Every website of the team, not just this project
      --provider <value>  Filter by provider short name
      --q <value>         Filter by domain or name

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
  # Websites of this project
  $ unolia website list
  # Every website of the team
  $ unolia website list --all-projects
  # Domains only
  $ unolia website list --json id,domain

LEARN MORE
  https://unolia.com/docs/cli/website-list
```
