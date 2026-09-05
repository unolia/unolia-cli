# unolia ci list

List the CI runs of this repository

```
USAGE
  unolia ci list [flags]

FLAGS
      --repo <value>      Repository id or full name
      --branch <value>    Branch, the current one by default
      --all-branches      Every branch
      --status <value>    Filter by status
      --workflow <value>  Filter by workflow name or path

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
  # Runs on this branch
  $ unolia ci
  # Every branch
  $ unolia ci list --all-branches

LEARN MORE
  https://unolia.com/docs/cli/ci-list
```
