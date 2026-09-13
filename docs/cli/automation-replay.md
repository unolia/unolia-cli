# unolia automation replay

Replay an automation run

```
USAGE
  unolia automation replay <run> [flags]

ARGUMENTS
  run  Run ULID or a prefix of it

FLAGS
      --wait              Follow the new run
      --interval <value>  Seconds between checks (default 3)
      --timeout <value>   Give up waiting after this long (default 15m)
      --notify            Send a desktop notification at the end

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
  # Replay a run
  $ unolia automation replay 01J9A2
  # Replay and follow
  $ unolia automation replay 01J9A2 --wait

LEARN MORE
  https://unolia.com/docs/cli/automation-replay
```
