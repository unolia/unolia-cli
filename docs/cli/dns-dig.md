# unolia dns dig

Ask a DNS resolver about a name

```
USAGE
  unolia dns dig [<name>] [<type>] [flags]

ARGUMENTS
  name  The name to look up, a full hostname or one relative to the zone
  type  Record type, A by default

FLAGS
      --server <value>  Resolver to ask (default 1.1.1.1)
      --all             Ask 1.1.1.1, 8.8.8.8, 9.9.9.9 and the zone's nameservers
      --zone <value>    The zone a relative name belongs to

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
  # A relative name, in a checkout
  $ unolia dns dig www
  # Every resolver at once
  $ unolia dig acme.com TXT --all
  # Another resolver
  $ unolia dig acme.com --server 8.8.8.8

LEARN MORE
  https://unolia.com/docs/cli/dns-dig
```
