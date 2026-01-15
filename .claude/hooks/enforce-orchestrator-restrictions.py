#!/usr/bin/env python3
"""
PreToolUse hook to enforce orchestrator tool restrictions.

When .claude/orchestrator-mode marker file exists, this hook blocks:
- Edit tool
- Write tool
- Bash tool

This forces the orchestrator to delegate work to specialized agents.

Exit codes:
- 0: Allow the tool call
- 2: Deny the tool call (error message on stderr)
"""

import json
import sys
import os

# Tools that orchestrator is NOT allowed to use
BLOCKED_TOOLS = {"Edit", "Write", "Bash"}


def get_marker_path():
    """Get the path to the orchestrator-mode marker file."""
    # Use CLAUDE_PROJECT_DIR if available, otherwise use cwd from input
    project_dir = os.environ.get("CLAUDE_PROJECT_DIR")
    if project_dir:
        return os.path.join(project_dir, ".claude", "orchestrator-mode")
    # Fallback: get from hook input cwd
    return None


def main():
    # Read hook input from stdin
    try:
        hook_input = json.load(sys.stdin)
    except json.JSONDecodeError:
        # If we can't parse input, allow the tool call
        sys.exit(0)

    tool_name = hook_input.get("tool_name", "")
    cwd = hook_input.get("cwd", "")

    # Determine marker file path
    marker_path = get_marker_path()
    if not marker_path and cwd:
        marker_path = os.path.join(cwd, ".claude", "orchestrator-mode")

    if not marker_path:
        # Can't determine marker path, allow the call
        sys.exit(0)

    # Check if orchestrator mode is active
    if os.path.exists(marker_path):
        if tool_name in BLOCKED_TOOLS:
            # Block the tool - write message to stderr and exit with code 2
            error_msg = (
                f"ORCHESTRATOR RESTRICTION: {tool_name} tool is blocked.\n"
                f"You must delegate this work:\n"
                f"  - Code changes → Use the dev-agent\n"
                f"  - Code review → Use the testing-agent\n"
                f"  - Git/deployment → Use the deploy-agent\n\n"
                f"Use the Task tool to spawn the appropriate agent."
            )
            print(error_msg, file=sys.stderr)
            sys.exit(2)  # Exit code 2 = deny

    # Allow the tool call
    sys.exit(0)


if __name__ == "__main__":
    main()
