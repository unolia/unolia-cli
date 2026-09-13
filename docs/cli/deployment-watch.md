# unolia deployment watch

Follow a deployment until it finishes

```
USAGE
  unolia deployment watch [<deployment>] [flags]

ARGUMENTS
  deployment  Deployment id, the running or next one of the linked website by default

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
      --page <value>     The page to show, when a list says there is more

EXAMPLES
  # The running or next deployment
  $ unolia deployment watch
  # Follow a deployment
  $ unolia deployment watch 4812
  # As a stream of events
  $ unolia watch deployment 4812 --format ndjson

LEARN MORE
  https://unolia.com/docs/cli/deployment-watch
```
