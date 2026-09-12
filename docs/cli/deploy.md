# unolia deploy

Deploy the website linked to this directory

```
USAGE
  unolia deploy [<environment>] [flags]

ARGUMENTS
  environment  An environment name from .unolia/config.json

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

EXAMPLES
  # Deploy this directory and watch it
  $ unolia deploy
  # Deploy an environment
  $ unolia deploy staging
  # Queue it and come back later
  $ unolia deploy --no-progress

LEARN MORE
  https://unolia.com/docs/cli/deploy
```
