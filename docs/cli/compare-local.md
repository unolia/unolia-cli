# unolia compare local

Compare this machine with production

```
USAGE
  unolia compare local [flags]

FLAGS
      --env <value>   Which environment to compare against
      --only <value>  Comma list of components
      --all           Include components production does not report
      --strict        Warnings also fail

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
  # Am I in sync
  $ unolia compare local
  # Everything
  $ unolia compare local --all
  # In CI
  $ unolia compare local --strict --json

LEARN MORE
  https://unolia.com/docs/cli/compare-local
```
