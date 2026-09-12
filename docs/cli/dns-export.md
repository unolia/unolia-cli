# unolia dns export

Print a zone as a zone file

```
USAGE
  unolia dns export [<zone>] [flags]

ARGUMENTS
  zone  The zone, the project's one by default

FLAGS
      --type <value>  Only these types, comma separated

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
  # To a file
  $ unolia dns export acme.com > acme.com.zone
  # As rows
  $ unolia dns export acme.com --json

LEARN MORE
  https://unolia.com/docs/cli/dns-export
```
