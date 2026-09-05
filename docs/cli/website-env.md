# unolia website env

Show the production environment keys of a website

```
USAGE
  unolia website env [<website>] [flags]

ARGUMENTS
  website  Website id or domain, the linked one by default

FLAGS
      --values     Print the values too, which are live credentials
      --keys-only  Print the key names, one per line

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
  # The keys in production
  $ unolia website env
  # With values, on purpose
  $ unolia website env --values --yes

LEARN MORE
  https://unolia.com/docs/cli/website-env
```
