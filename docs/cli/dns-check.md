# unolia dns check

Compare the records Unolia knows with what a resolver answers

```
USAGE
  unolia dns check [<zone>] [flags]

ARGUMENTS
  zone  The zone, the project's one by default

FLAGS
      --server <value>  Resolver to ask (default 1.1.1.1)
      --type <value>    Only these types, comma separated

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
  # This project's zone
  $ unolia dns check
  # Against Google
  $ unolia dns check acme.com --server 8.8.8.8

LEARN MORE
  https://unolia.com/docs/cli/dns-check
```
