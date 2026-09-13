# unolia login

Authenticate with Unolia

```
USAGE
  unolia login [flags]

FLAGS
      --token <value>   Store this token instead of signing in through the browser
      --with-token      Read the token from stdin
      --scopes <value>  Scopes to ask for, comma separated. The host picks sensible defaults
      --name <value>    Name of the token in the dashboard, user@hostname by default
      --no-browser      Print the URL instead of opening the browser
      --host <value>    Host to authenticate against

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
  # Sign in through the browser
  $ unolia login
  # Ask for chosen scopes
  $ unolia login --scopes project:read,deployment:write
  # From a script
  $ unolia login --with-token < token.txt
  # Against a local host
  $ unolia login --host unolia.test

LEARN MORE
  https://unolia.com/docs/cli/login
```
