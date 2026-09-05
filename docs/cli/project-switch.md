# unolia project switch

Point this directory at another project

```
USAGE
  unolia project switch <project> [flags]

ARGUMENTS
  project  Project id or name

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
  # Switch project
  $ unolia project switch 12
  # See what would change
  $ unolia project switch 12 --dry-run

LEARN MORE
  https://unolia.com/docs/cli/project-switch
```
