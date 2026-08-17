# Legacy auth and permissions contract

## Sources

- `sklad_users`: identity, `groupID`, `user_priv`; modern Argon2id hash is imported only when present.
- `sklad_user_permissions`: semicolon-separated explicit legacy division IDs and `didDefault`.
- `sklad_divisions`: navigation hierarchy and division labels.
- `Users::$_connectedPerm`: implicit grants; importer expands them before writing user permissions.

## Target mapping

`identity_users.id` equals `sklad_users.user_id`. Each division becomes permission `legacy.did.<xID>`. Explicit user grants are imported to `identity_user_permissions`; legacy group IDs are preserved as UI context in `identity_groups`.

Groups do not grant access. Their legacy checks remain an explicit migration concern for each screen. `user_priv` is also preserved as context, not treated as an authorization decision. Only explicit user permissions protect new Symfony routes.

Passwords use only `password_hash` (Argon2id) when Symfony login is enabled. `user_password` is never copied.
