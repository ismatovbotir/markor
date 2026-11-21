# /next-model

Check the build plan and start working on the next unfinished model.

Reads `.claude/PLAN.md`, finds the first model where status is not ✅, and runs `/make-module` for it.

Print a one-line summary of what you are about to build before starting.
