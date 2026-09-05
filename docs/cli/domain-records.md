# unolia domain records

List the records of a domain

```
USAGE
  unolia domain records [<domain>] [flags]

ARGUMENTS
  domain  The domain name

FLAGS
      --type <value>  Only records of this type

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
  # Every record
  $ unolia domain records acme.com
  # TXT records only
  $ unolia domain records acme.com --type TXT

LEARN MORE
  https://unolia.com/docs/cli/domain-records
```
