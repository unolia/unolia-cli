# unolia domain add

Add a record to a domain

```
USAGE
  unolia domain add [<domain>] [<name>] [<type>] [<value>] [flags]

ARGUMENTS
  domain  The domain name
  name    The full record name, @ for the domain itself
  type    Record type
  value   Record value

FLAGS
      --ttl <value>  Time to live in seconds

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
  $ unolia domain add acme.com www.acme.com A 203.0.113.10
  # Publish a TXT record
  $ unolia domain add acme.com _dmarc.acme.com TXT "v=DMARC1; p=none"

LEARN MORE
  https://unolia.com/docs/cli/domain-add
```
