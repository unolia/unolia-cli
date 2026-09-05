# unolia domain remove

Remove a record

```
USAGE
  unolia domain remove <record> [flags]

ARGUMENTS
  record  Record id

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
  # Remove a record
  $ unolia domain remove 88231
  # Without asking
  $ unolia domain remove 88231 --yes

LEARN MORE
  https://unolia.com/docs/cli/domain-remove
```
