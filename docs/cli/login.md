# unolia login

Authenticate with Unolia

```
USAGE
  unolia login [flags]

FLAGS
      --token <value>  Authenticate with this token
      --with-token     Read the token from stdin
      --web            Open the token page in the browser first
      --host <value>   Host to authenticate against

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
  # Paste a token
  $ unolia login
  # From a script
  $ unolia login --with-token < token.txt
  # Against a local host
  $ unolia login --host unolia.test --token utk_...

LEARN MORE
  https://unolia.com/docs/cli/login
```
