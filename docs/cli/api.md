# unolia api

Make an authenticated request to the Unolia API

```
USAGE
  unolia api <endpoint> [flags]

ARGUMENTS
  endpoint  A path such as v1/websites, or a full URL on this host

FLAGS
  -X, --method <value>     HTTP method, GET by default
  -f, --raw-field <value>  A key=value string field
  -F, --field <value>      A typed key=value field, @file and @- read a file or stdin
  -H, --header <value>     An extra header, "Key: value"
      --input [<value>]    Send this file as the body, --input or --input=- reads stdin
  -i, --include            Print the status line and headers
      --slurp              With --paginate, merge every data array into one

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
  # Read a list
  $ unolia api v1/websites
  # Filter the answer
  $ unolia api v1/websites --jq '.data[].domain'
  # Send a body
  $ unolia api v1/websites/118/deployments -X POST -F dry_run=true
  # Send a file
  $ unolia api v1/websites/118/deployments --input=body.json

LEARN MORE
  https://unolia.com/docs/cli/api
```
