# Anteckningar (Notes)

*[Svensk version](README.md)*

A small, self-contained notes tool in PHP and MySQL/MariaDB. It works on both phone and desktop, with no framework and no build step. Upload the files and go.

> The user interface is in **Swedish**. This README explains everything in English.

## Features

- **One list** of all notes. Pinned notes come first, then your chosen sort order: last edited, created or name.
- **Tags**: several per note. Tags you've used before are suggested while you type, and "Recipe" and "recipe" count as the same tag.
- **Tag cloud** with counts. Click a tag to filter.
- **Search** across names, text and tags. It can be combined with a tag filter.
- **Markdown**: headings, bold and italics, lists, checkboxes (`- [ ]`), quotes, code and links. The *Visa* (view) tab makes URLs clickable.
- **Dates** for created and last edited are set automatically.
- **Archive**: archived notes are hidden from the list but kept. Notes can only be deleted from the archive, and you have to confirm.
- **Protection against lost text**:
  - The page warns you if you try to leave a note with unsaved changes.
  - If the note was saved on another device after you opened it, you choose between overwriting, saving yours as a copy or loading the other version.
- **Its own URL per note** (`anteckningar.php?id=12`), so you can bookmark it.
- **Export** of all notes as a text file.
- **Ctrl+S / Cmd+S** saves.

## Requirements

- PHP 7.4 or later (8.x recommended) with `mysqli` (mysqlnd) and `mbstring`. Almost every web host has both.
- MySQL 5.7+ or MariaDB 10.3+
- HTTPS is strongly recommended, since the login is otherwise sent unencrypted.

## Installation

1. **Create a database**, or use an existing one. Run `sql/schema.sql` in it, for example in phpMyAdmin under the SQL tab.
2. **Sample data (optional):** run `sql/exempeldata.sql` to get some notes to look at. It puts the data on user ID 1, which is what the built-in login mode uses.
3. **Copy** `config.example.php` to `config.php` and fill in the database details and `LOGIN_USER`.
4. **Upload** the folder to your web server.
5. **Open** `anteckningar.php` in your browser. The first time, a setup guide appears:
   1. Choose a password, and a hash is created for it.
   2. Paste the line into `config.php` as `LOGIN_PASSWORD_HASH` and reload the page.
   3. Log in.

`config.php` is listed in `.gitignore`, so your credentials never end up in git.

## Login modes

The login mode is set with `AUTH_MODE` in `config.php`:

| Mode | Description |
|---|---|
| `login` | Built-in login with one username and password. The default. |
| `demo` | No login. Each visitor gets their own sandbox with sample data, deleted after `DEMO_TTL_HOURS` hours. Requires `sql/demo.sql` instead of `exempeldata.sql`. Good for a public showcase. |
| `ll2` | Shares the session with an existing login system (`ll2.php`). Used in the author's own installation, and you can ignore it. |

## Files

| File | Contents |
|---|---|
| `anteckningar.php` | The whole tool: list, editor, saving and export |
| `common.php` | Shared hub: database, login, CSRF protection, menu and demo mode |
| `config.example.php` | Settings template |
| `demo.php` | Copies sample data into a visitor's sandbox (demo mode only) |
| `minsida.css` | Styling |
| `sql/` | `schema.sql` (tables), `exempeldata.sql` (sample data) and `demo.sql` |

## Security

- All database queries use prepared statements.
- Every change is protected by a CSRF token.
- The Markdown renderer escapes all text and only allows `http(s)` and `mailto` links.
- The password is stored as a `password_hash`, never in plain text. Failed logins are delayed by two seconds.

## License

MIT – see [LICENSE](LICENSE).
