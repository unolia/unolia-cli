# unolia automation run

Start an automation

```
USAGE
  unolia automation run <automation> [flags]

ARGUMENTS
  automation  Automation id or exact name

FLAGS
      --wait              Block until it finishes, also in a pipe
      --no-progress       Return as soon as it is started instead of following it
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

EXAMPLES
  # See the plan
  $ unolia automation run 7 --dry-run
  # Run and follow it
  $ unolia automation run 7
  # Start it and come back later
  $ unolia automation run 7 --no-progress
  # Block in a script
  $ unolia automation run 7 --yes --wait

LEARN MORE
  https://unolia.com/docs/cli/automation-run
```
