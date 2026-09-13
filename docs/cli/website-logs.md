# unolia website logs

Print the output of the latest deployment

```
USAGE
  unolia website logs [<website>] [flags]

ARGUMENTS
  website  Website id or domain, the linked one by default

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
  # The last deployment log
  $ unolia website logs
  # Follow it
  $ unolia website logs --follow

LEARN MORE
  https://unolia.com/docs/cli/website-logs
```
