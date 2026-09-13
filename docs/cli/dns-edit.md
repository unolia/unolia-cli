# unolia dns edit

Change the name, value or TTL of a DNS record

```
USAGE
  unolia dns edit <record> [<type>] [flags]

ARGUMENTS
  record  Record id, or its name
  type    Record type, when the name alone is not enough

FLAGS
      --zone <value>      The zone, the project's one by default
      --name <value>      The new name, relative to the zone
      --value <value>     The new value
      --ttl <value>       The new time to live in seconds
      --priority <value>  The new priority, for MX and SRV
      --wait              Block until the record is verified, also in a pipe
      --no-progress       Return as soon as the record is saved instead of following it
      --interval <value>  Seconds between checks (default 2)
      --timeout <value>   Give up waiting after this long (default 2m)
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
  # Change a value
  $ unolia dns edit www A --value 203.0.113.11
  # Change the TTL by id
  $ unolia dns edit 88231 --ttl 300
  # Rename
  $ unolia dns edit old CNAME --name new

LEARN MORE
  https://unolia.com/docs/cli/dns-edit
```
