# unolia dns add

Add a DNS record

```
USAGE
  unolia dns add [<name>] [<type>] [<value>] [<legacy>] [flags]

ARGUMENTS
  name    Record name, relative to the zone: www, @ for the zone itself, or a full hostname
  type    Record type
  value   Record value
  legacy  Unused; lets the old "domain add <zone> <name> <type> <value>" keep working

FLAGS
      --zone <value>      The zone, the project's one by default
      --ttl <value>       Time to live in seconds
      --priority <value>  Priority, for MX and SRV
      --wait              Block until the record is verified, also in a pipe
      --no-progress       Return as soon as the record is saved instead of following it
      --interval <value>  Seconds between checks (default 2)
      --timeout <value>   Give up waiting after this long (default 2m)
      --notify            Send a desktop notification at the end

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
  # Point a subdomain
  $ unolia dns add www A 203.0.113.10
  # A TXT record on the zone itself
  $ unolia dns add @ TXT "v=spf1 include:_spf.example.com ~all"
  # A mail server
  $ unolia dns add @ MX mail.acme.com --priority 10
  # In another zone
  $ unolia dns add www A 203.0.113.10 --zone acme.dev

LEARN MORE
  https://unolia.com/docs/cli/dns-add
```
