# unolia automation runs

List automation runs

```
USAGE
  unolia automation runs [flags]

FLAGS
      --automation <value>  Only runs of this automation, by id or any part of its name
      --state <value>       Filter by state
      --since <value>       Only runs since, such as 7d

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
  # Recent runs
  $ unolia automation runs
  # What is stuck
  $ unolia automation runs --state awaiting_input

LEARN MORE
  https://unolia.com/docs/cli/automation-runs
```
