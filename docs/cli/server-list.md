# unolia server list

List the managed servers of a project

```
USAGE
  unolia server list [flags]

FLAGS
      --all-projects      Every server of the team
      --provider <value>  Filter by provider
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
      --page <value>     The page to show, when a list says there is more

EXAMPLES
  # Servers here
  $ unolia server list
  # PHP versions
  $ unolia server list --json name,php_version

LEARN MORE
  https://unolia.com/docs/cli/server-list
```
