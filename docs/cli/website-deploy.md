# unolia website deploy

Deploy a website and optionally wait for the result

```
USAGE
  unolia website deploy [<website>] [flags]

ARGUMENTS
  website  Website id or domain, the linked one by default

FLAGS
      --wait              Block until the deployment finishes, also in a pipe
      --no-progress       Return as soon as the deployment is queued instead of following it
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
  # Deploy this directory and watch it
  $ unolia deploy
  # Queue it and come back later
  $ unolia deploy --no-progress
  # Block in a script
  $ unolia deploy --wait --yes
  # Stream the steps to a script
  $ unolia website deploy 118 --wait --format ndjson

LEARN MORE
  https://unolia.com/docs/cli/website-deploy
```
