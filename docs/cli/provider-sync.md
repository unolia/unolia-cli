# unolia provider sync

Synchronize a connected provider

```
USAGE
  unolia provider sync <provider> [flags]

ARGUMENTS
  provider  Provider id

FLAGS
      --wait              Block until it finishes, also in a pipe
      --no-progress       Return as soon as it is started instead of following it
      --interval <value>  Seconds between checks (default 3)
      --timeout <value>   Give up waiting after this long (default 10m)
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
  # Sync a provider and watch it land
  $ unolia provider sync 14
  # Queue it and come back later
  $ unolia provider sync 14 --no-progress
  # Block in a script
  $ unolia provider sync 14 --wait

LEARN MORE
  https://unolia.com/docs/cli/provider-sync
```
