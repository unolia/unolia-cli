# unolia config get

Read one CLI option

```
USAGE
  unolia config get <key> [flags]

ARGUMENTS
  key  Option name

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
  # The default team
  $ unolia config get default_team
  # The host every request goes to
  $ unolia config get host

LEARN MORE
  https://unolia.com/docs/cli/config-get
```
