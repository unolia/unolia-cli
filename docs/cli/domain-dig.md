# unolia domain dig

Query a DNS server about a domain

```
USAGE
  unolia domain dig [<domain>] [<type>] [flags]

ARGUMENTS
  domain  The name to look up
  type    Record type, A by default

FLAGS
      --server <value>  Resolver to ask (default 1.1.1.1)

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
  # Look up an address
  $ unolia dig acme.com
  # Ask another resolver
  $ unolia dig acme.com TXT --server 8.8.8.8

LEARN MORE
  https://unolia.com/docs/cli/domain-dig
```
