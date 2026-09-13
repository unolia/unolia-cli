# unolia deployment logs

Print the output of a deployment

```
USAGE
  unolia deployment logs <deployment> [flags]

ARGUMENTS
  deployment  Deployment id

FLAGS
  -f, --follow  Keep printing until the deployment ends
      --raw     Keep the ANSI codes

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
  # The whole log
  $ unolia deployment logs 4812
  # Follow it
  $ unolia deployment logs 4812 --follow

LEARN MORE
  https://unolia.com/docs/cli/deployment-logs
```
