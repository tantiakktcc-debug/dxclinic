# Database Root Password & Credential Security Rule

The MySQL ROOT password is stored in the project's `.env` file.

The following rules MUST be followed for every database-related task:

1. Read the ROOT password only from the project's `.env` file when it is required.
2. NEVER hardcode the ROOT password in:
   - PHP source code
   - Laravel config files
   - migrations
   - seeders
   - tests
   - Playwright scripts
   - Node.js scripts
   - PowerShell scripts
   - BAT files
   - helper scripts
   - documentation
   - Git-tracked files
   - command-line arguments that may be logged
3. NEVER print, echo, log, display, screenshot, or return the actual ROOT password.
4. NEVER include the ROOT password in task reports, terminal output, error messages, generated markdown, or chat responses.
5. NEVER commit `.env` or any file containing credentials to Git.
6. Use Laravel's environment/config mechanism where applicable.
7. For temporary CLI/database operations, retrieve the credential securely from `.env` without exposing it in output.
8. Do NOT ask the user to paste the ROOT password into chat when it is already available in `.env`.
9. Before modifying any database-related script, inspect the existing `.env`/configuration and reuse the existing secure credential mechanism rather than creating another password source.
10. If a script currently contains a hardcoded password, remove the hardcoded credential and convert it to secure `.env`-based loading.
11. Do not expose credentials even when reporting success/failure. Report only sanitized results.
12. Treat all database credentials, API keys, encryption keys, backup passphrases, and tokens as secrets.
