# unolia auth refresh

Sign in again with more or fewer scopes

```
USAGE
  unolia auth refresh [flags]

FLAGS
      --scopes <value>         Scopes to add, comma separated
      --remove-scopes <value>  Scopes to drop, comma separated
      --host <value>           Host whose token to refresh

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
  # Add a scope the API asked for
  $ unolia auth refresh --scopes deployment:write
  # Drop one
  $ unolia auth refresh --remove-scopes env:read
  # Same scopes, new token
  $ unolia auth refresh

LEARN MORE
  https://unolia.com/docs/cli/auth-refresh
```
