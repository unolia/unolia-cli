# unolia help

Help for any command

```
USAGE
  unolia help [<command_name>...] [flags]

ARGUMENTS
  command_name  The command to explain

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
  # Everything the CLI can do
  $ unolia help
  # One command
  $ unolia help website deploy
  # How to drive it from a script
  $ unolia help agents
```
