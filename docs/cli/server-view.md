# unolia server view

Show one managed server

```
USAGE
  unolia server view <server> [flags]

ARGUMENTS
  server  Server id

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
  # One server
  $ unolia server view 61
  # What it runs
  $ unolia server view 61 --json php_version,database

LEARN MORE
  https://unolia.com/docs/cli/server-view
```
