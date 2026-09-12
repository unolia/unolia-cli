# unolia dns list

List the DNS records of a zone

```
USAGE
  unolia dns list [<zone>] [flags]

ARGUMENTS
  zone  The zone, the project's one by default

FLAGS
      --type <value>   Only these types, comma separated
      --name <value>   Only this name, relative or full
      --state <value>  Only records in this state

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
  # Records of this project's zone
  $ unolia dns
  # Another zone
  $ unolia dns acme.com
  # Mail records only
  $ unolia dns --type MX,TXT

LEARN MORE
  https://unolia.com/docs/cli/dns-list
```
