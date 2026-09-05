# unolia domain update

Update a record

```
USAGE
  unolia domain update <record> [<name>] [<value>] [flags]

ARGUMENTS
  record  Record id
  name    The new record name
  value   The new record value

FLAGS
      --ttl <value>  The new time to live in seconds

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
  # Change a value
  $ unolia domain update 88231 www.acme.com 203.0.113.11
  # Change the TTL only
  $ unolia domain update 88231 --ttl 300

LEARN MORE
  https://unolia.com/docs/cli/domain-update
```
