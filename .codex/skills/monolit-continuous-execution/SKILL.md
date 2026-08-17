---
name: monolit-continuous-execution
description: Execute Monolit migration tasks end-to-end without empty progress messages. Use for any implementation work in this project.
---

# Monolit continuous execution

- Work through the migration plan in order.
- Edit project files directly; do not stage ordinary changes in /tmp. Never create ordinary implementation files in /tmp: the user inspects changes in the project tree as they happen.
- Do not send messages such as “starting”, “continuing”, or “next step” without a completed artifact, test result, or concrete blocker.
- Complete the agreed task before returning to the user. Do not ask for confirmation between its ordinary implementation steps.
- Ask the user only when a decision changes data ownership, production cutover behaviour, security, or task scope.
- Every final progress report must name changed files and verification performed.
- Do not call a task complete or invite verification until every agreed layer is implemented: migration, import/command, repository, read-model/DTO, controller, template, and tests. State each layer as done or not done.
- When the user asks to implement X, continue working on X until it is complete. Do not ask “what next?” or switch to a neighbouring task.
- Keep legacy MariaDB read-only unless the user explicitly authorizes a production cutover.
- Use braces on the signature line in PHP classes and methods.
- Avoid premature abstractions and methods that are not currently used.
