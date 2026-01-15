#!/usr/bin/env python3
"""
PreToolUse hook to enforce orchestrator tool restrictions.

When .claude/orchestrator-mode marker file exists, this hook blocks:
- Edit tool
- Write tool
- Bash tool

This forces the orchestrator to delegate work to specialized agents.
"""

import json
import sys
import os

# Tools that orchestrator is NOT allowed to use
BLOCKED_TOOLS = {"Edit", "Write", "Bash"}

# Marker file that indicates orchestrator mode is active
MARKER_FILE = os.path.join(os.path.dirname(os.path.dirname(__file__)), "orchestrator-mode")


def main():
    # Read hook input from stdin
    try:
        hook_input = json.load(sys.stdin)
    except json.JSONDecodeError:
        # If we can't parse input, allow the tool call
        print(json.dumps({"decision": "allow"}))
        return

    tool_name = hook_input.get("tool_name", "")

    # Check if orchestrator mode is active
    if os.path.exists(MARKER_FILE):
        if tool_name in BLOCKED_TOOLS:
            # Block the tool and provide helpful message
            print(json.dumps({
                "decision": "block",
                "message": f"ORCHESTRATOR RESTRICTION: {tool_name} tool is blocked in orchestrator mode. "
                          f"You must delegate this work:\n"
                          f"  - Code changes → Use the dev-agent\n"
                          f"  - Code review → Use the testing-agent\n"
                          f"  - Git/deployment → Use the deploy-agent\n\n"
                          f"Use the Task tool to spawn the appropriate agent."
            }))
            return

    # Allow the tool call
    print(json.dumps({"decision": "allow"}))


if __name__ == "__main__":
    main()
