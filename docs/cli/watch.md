# unolia watch

Follow a deployment, CI run, automation run or DNS record

```
USAGE
  unolia watch [<kind>] [<id>] [flags]

ARGUMENTS
  kind  deployment, ci, automation or record
  id    The id of the thing to watch. For a deployment, the running or next one by default

FLAGS
      --last              Follow the latest deployment even when it has finished
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
  # Whatever this directory started
  $ unolia watch
  # The running or next deployment, after a push
  $ unolia watch deployment
  # A deployment
  $ unolia watch deployment 4812
  # A DNS record
  $ unolia watch record 88231

LEARN MORE
  https://unolia.com/docs/cli/watch
```
