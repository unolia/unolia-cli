# unolia logout

Remove the stored token for this host

```
USAGE
  unolia logout [flags]

FLAGS
      --force         Drop the local token even when the API call fails
      --host <value>  Host to log out of

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
  # Log out
  $ unolia logout
  # Drop a revoked token
  $ unolia logout --force

LEARN MORE
  https://unolia.com/docs/cli/logout
```
