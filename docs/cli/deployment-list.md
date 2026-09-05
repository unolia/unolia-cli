# unolia deployment list

List deployments

```
USAGE
  unolia deployment list [flags]

FLAGS
      --status <value>  Filter by status
      --branch <value>  Filter by branch
      --since <value>   Only deployments since, such as 7d
      --all-websites    Every website of the project, not just the linked one

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
  # Recent deployments here
  $ unolia deployment list
  # Failures on main
  $ unolia deployment list --status failed --branch main

LEARN MORE
  https://unolia.com/docs/cli/deployment-list
```
