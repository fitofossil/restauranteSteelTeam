# AGENTS.md

## Project overview

This repository is a PHP + MySQL web app for a restaurant internal panel called Dogão Lanches. The main flow is:

- `templates/login.php` renders the login screen.
- `auth.php` receives credentials and calls `Auth::login()`.
- `src/Auth.php` centralizes session management, login validation, and role checks.
- Role-based screens are rendered from `templates/` and access is guarded by static `Auth::*` methods.
- Data access is done through `config/conexao.php` with PDO and prepared statements.

## Important files

- `config/conexao.php`: database connection and `BASE_URL` definition.
- `src/Auth.php`: session lifecycle, login/logout, permission checks, and sanitization helpers.
- `src/Cardapio.php`: menu/product logic.
- `src/Pedidos.php`: order-related logic and status handling.
- `templates/*.php`: UI screens and role-specific flows.
- `public/index.php`: entry redirect to the login page.
- `restaurante.sql`: database schema and seed data.
- `DOCUMENTACAO.md`: project behavior and architecture notes.

## Local run instructions

1. Start Apache + MySQL in XAMPP.
2. Create the `restaurante` database.
3. Import `restaurante.sql`.
4. Confirm the credentials in `config/conexao.php` match the local DB user.
5. Open `http://localhost/restauranteSteelTeam/templates/login.php`.

## Coding conventions

### PHP and architecture

- Keep business logic in `src/` and presentation in `templates/`.
- Prefer `require_once` for shared files and class loading.
- Keep the existing pattern of static session checks from `Auth` rather than creating ad hoc session logic.
- When adding permission checks, use the relevant `Auth::require*()` method instead of duplicating custom role logic.
- Preserve the `BASE_URL` convention when redirecting or building URLs.

### Database access

- Use PDO with prepared statements for all user-driven queries.
- Do not interpolate raw request data into SQL.
- Keep database credentials and environment assumptions centralized in `config/conexao.php`.
- Prefer reusing the existing connection object (`$conn`) rather than creating parallel DB connections.

### Security and output

- Escape user-controlled output with `htmlspecialchars()` before rendering in HTML.
- Validate emails, IDs, and role values before writing to the database.
- Keep login and permission flows aligned with `Auth` behavior and role values.
- Do not bypass `Auth::requireLogin()` or admin-only guards.

### UI / templates

- Keep templates focused on HTML and PHP rendering, not business logic heavy code.
- Follow the current naming and structure used in `templates/*.php`.
- If a screen is role-specific, preserve the existing redirection logic for unauthorized users.

## Validation

- For PHP edits, run `php -l <file.php>` on modified files when possible.
- Because this project does not appear to have a formal test suite, prefer focused validation around the touched flow.
- Validate login, permission redirects, and DB interactions after any auth or query change.

## Scope guidance for AI agents

- Prefer minimal edits that match the current codebase patterns.
- Do not introduce frameworks, modern PHP patterns, or a new architecture unless explicitly requested.
- Preserve backwards compatibility with the existing restaurant workflow and role mapping.
- If documentation is relevant, prefer the project rules in `DOCUMENTACAO.md` over generic PHP advice.

## Related files to consult

- [README.md](README.md)
- [DOCUMENTACAO.md](DOCUMENTACAO.md)
- [config/conexao.php](config/conexao.php)
- [src/Auth.php](src/Auth.php)
- [templates/login.php](templates/login.php)
