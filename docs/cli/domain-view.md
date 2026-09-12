# unolia domain view

Show one DNS zone

```
USAGE
  unolia domain view [<zone>] [flags]

ARGUMENTS
  zone  The zone, the project's one by default

FLAGS
      --zone <value>  Same as the argument

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
  # This project's zone
  $ unolia domain view
  # Another one
  $ unolia domain view acme.com
  # Its nameservers
  $ unolia domain view acme.com --json nameservers

LEARN MORE
  https://unolia.com/docs/cli/domain-view
```
