# unolia automation cancel

Cancel an automation run

```
USAGE
  unolia automation cancel <run> [flags]

ARGUMENTS
  run  Run ULID or a prefix of it

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
  # Cancel a run
  $ unolia automation cancel 01J9A2
  # Without asking
  $ unolia automation cancel 01J9A2 --yes

LEARN MORE
  https://unolia.com/docs/cli/automation-cancel
```
