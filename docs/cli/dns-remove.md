# unolia dns remove

Remove a DNS record

```
USAGE
  unolia dns remove <record> [<type>] [flags]

ARGUMENTS
  record  Record id, or its name
  type    Record type, when the name alone is not enough

FLAGS
      --zone <value>  The zone, the project's one by default

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
  # By name
  $ unolia dns remove old CNAME
  # By id, without asking
  $ unolia dns remove 88231 --yes

LEARN MORE
  https://unolia.com/docs/cli/dns-remove
```
