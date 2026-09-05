# unolia website domains

List the domains of a website

```
USAGE
  unolia website domains [<website>] [flags]

ARGUMENTS
  website  Website id or domain, the linked one by default

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
  # Domains of this website
  $ unolia website domains
  # SSL status only
  $ unolia website domains --json domain,ssl_status

LEARN MORE
  https://unolia.com/docs/cli/website-domains
```
