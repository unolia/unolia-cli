# unolia mcp setup

Connect your AI agents to the Unolia MCP server

```
USAGE
  unolia mcp setup [flags]

FLAGS
      --global         Configure the agents for every project, in their user level config
      --local          Configure the agents for this directory only
      --agent <value>  An agent to configure, repeatable or comma separated: claude, cursor, vscode, codex, gemini, junie, kiro, opencode, amp, manual
      --url <value>    The MCP server URL, https://<host>/mcp/team by default
      --print          Print the JSON snippet for any client and do nothing else

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
  # Pick the agents and the scope at the terminal
  $ unolia mcp setup
  # Claude Code and Cursor, for every project
  $ unolia mcp setup --global --agent claude,cursor
  # This directory only, from a script or an agent
  $ unolia mcp setup --local --agent vscode --yes
  # See what would be written
  $ unolia mcp setup --local --agent claude --dry-run
  # Only the snippet
  $ unolia mcp setup --print

LEARN MORE
  https://unolia.com/docs/cli/mcp-setup
```
