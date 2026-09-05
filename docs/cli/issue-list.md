# unolia issue list

List the open issues of a project

```
USAGE
  unolia issue list [flags]

FLAGS
      --all-projects      Every project of the team
      --severity <value>  Comma list, such as error,warning
      --state <value>     Filter by state, open by default
      --fixable           Only issues the CLI can fix
      --check <value>     Filter by check slug
      --domain <value>    Only issues about this domain

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
  # Open issues here
  $ unolia issues
  # What can be fixed
  $ unolia issue list --fixable

LEARN MORE
  https://unolia.com/docs/cli/issue-list
```
