# unolia open

Open this project, website, live site, repository, or the site at its provider

```
USAGE
  unolia open [<what>] [<id>] [flags]

ARGUMENTS
  what  project, website, live, repo, deployment, domain, or a provider: forge, ploi, cloud, ovh, pages, github, gitlab (default project)
  id    The deployment id, or the zone when opening a domain

FLAGS
      --print  Print the URL instead of opening it

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
  # The project page
  $ unolia open
  # The website page
  $ unolia open website
  # The deployed site itself
  $ unolia open live
  # The site at its host
  $ unolia open forge
  # The repository at GitHub, only the URL
  $ unolia open github --print

LEARN MORE
  https://unolia.com/docs/cli/open
```
