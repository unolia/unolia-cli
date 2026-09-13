# unolia website view

Show one website

```
USAGE
  unolia website view [<website>] [flags]

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
      --page <value>     The page to show, when a list says there is more

EXAMPLES
  # The linked website
  $ unolia website view
  # Another one
  $ unolia website view marketing.acme.com

LEARN MORE
  https://unolia.com/docs/cli/website-view
```
