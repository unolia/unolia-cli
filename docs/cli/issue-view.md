# unolia issue view

Show one issue

```
USAGE
  unolia issue view <issue> [flags]

ARGUMENTS
  issue  Issue id, or the six characters unolia issue list prints

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
  # One issue
  $ unolia issue view 8d0e1f
  # Its fix metadata
  $ unolia issue view 8d0e1f --json fix

LEARN MORE
  https://unolia.com/docs/cli/issue-view
```
