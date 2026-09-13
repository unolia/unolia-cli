# unolia init

Link this directory to a project and website

```
USAGE
  unolia init [flags]

FLAGS
      --environment <value>  An extra name=website mapping
      --force                Overwrite an existing configuration

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
  # Link this directory
  $ unolia init
  # Without a terminal
  $ unolia init --website 118
  # With an extra environment
  $ unolia init --website 118 --environment staging=121

LEARN MORE
  https://unolia.com/docs/cli/init
```
