# unolia team tokens

Open the team tokens page

```
USAGE
  unolia team tokens [flags]

FLAGS
      --print  Print the URL instead of opening it

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
  # Manage team tokens
  $ unolia team tokens
  # Just the URL
  $ unolia team tokens --print

LEARN MORE
  https://unolia.com/docs/cli/team-tokens
```
