# unolia website deployments

List the deployments of a website

```
USAGE
  unolia website deployments [<website>] [flags]

ARGUMENTS
  website  Website id or domain, the linked one by default

FLAGS
      --status <value>  Filter by status
      --branch <value>  Filter by branch

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
  # Deployments of this website
  $ unolia website deployments
  # Failures only
  $ unolia website deployments --status failed

LEARN MORE
  https://unolia.com/docs/cli/website-deployments
```
