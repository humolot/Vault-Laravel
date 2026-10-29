# GBX Vault

Self-hosted, multi-user password manager with groups, built on **Laravel 12 + SQLite**. No MySQL, no Node build step: Bootstrap 5 (CDN) + jQuery, dark/light theme, available in **English, Spanish and Portuguese (BR)**.

> Português: [README.pt-BR.md](README.pt-BR.md)

## Features

- **Three-layer sign-in**: password, then security phrase, then **mandatory TOTP 2FA**
- **Optional passkeys** (WebAuthn: Windows Hello, Touch ID, Android, YubiKey) as a fourth layer the user can make mandatory
- **Encrypted vault**: names, labels, usernames, passwords, secrets, phone numbers and attachments are all encrypted at rest
- **Item types**: Login, 2FA recovery codes, Recovery/seed phrase, Phone/SMS (carrier + services that use the number), Server/SSH, API key, Wi-Fi, Secure note, Other
- **Encrypted attachments** (PDF, images, TXT, DOCX…) up to 10 MB, with inline preview
- **Sharing** with users or groups (read-only or edit), each group with its own key pair
- **Rotation suggestions only**: password rotation after X days and shared-access review. Revoking access or removing a group member flags the item for a password change. Nothing changes automatically.
- Password generator, strength meter, last 5 password history, and auto-clearing clipboard
- Trash with restore, full **audit log** (sign-ins, views, copies, downloads, shares)
- **Invite links**: the admin never knows anyone's credentials
- Brute-force lockout, per-IP rate limiting, auto-lock after inactivity, strict CSP and security headers

## Requirements

- PHP 8.2+ with `sodium`, `pdo_sqlite`, `openssl`, `mbstring`, `fileinfo`
- Composer
- Apache (or Nginx) with HTTPS. Passkeys require HTTPS or `localhost`.

## Installation

```bash
git clone <repo-url> gbx-vault && cd gbx-vault
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache database
```

Open the site. The first visit shows the **Setup** screen, where you create the administrator account.

### Document root

- **Recommended:** point the web server to `public/`.
- **Shared hosting / aaPanel style:** the document root can be the project root. The root `.htaccess` forwards everything to `public/` and blocks access to `.env`, `database/`, `storage/`, `vendor/`, and so on.

### Testing without HTTPS

Set these in `.env`:

```
SESSION_SECURE_COOKIE=false
FORCE_HTTPS=false
```

## Encryption model

- Each user has an **X25519 key pair**. The private key is encrypted with a key derived via **Argon2id** from *password + security phrase*. The server never stores it in plain text.
- Each item has its own random key (**XSalsa20-Poly1305**). That key is *sealed* for the owner, for every user and for every group that has access.
- Each **group has its own key pair**. The group's private key is sealed for every member. Members added before activating their account get the key automatically on the next sign-in of any member who has it.
- During a session, the user's private key is kept in the server session encrypted with a key that lives **only in an httpOnly browser cookie**. A database or session dump alone cannot open the vault.
- Only the item type and timestamps are stored unencrypted.
- `APP_KEY` encrypts cookies, sessions and TOTP secrets.

### Recovery key

A recovery key is shown **once** when an account is activated.

- If a user forgets both password and phrase, the recovery key plus a 2FA code lets them set new credentials.
- Without it, the admin can **reset the account**. Items only that user could access become unreadable. Items shared with others become available again after reactivation.

## Backups

Back up these three things together:

- `.env` (contains `APP_KEY`)
- `database/database.sqlite`
- `storage/app/private/vault/` (encrypted attachments)

## Configuration (`.env`)

| Variable | Default | Description |
|---|---|---|
| `VAULT_IDLE_MINUTES` | 15 | Auto-lock after inactivity |
| `VAULT_MAX_ATTEMPTS` | 5 | Failed attempts before lockout |
| `VAULT_LOCK_MINUTES` | 15 | Lockout duration |
| `VAULT_INVITE_HOURS` | 72 | Invite link validity |
| `VAULT_PHRASE_MIN` | 12 | Minimum security phrase length |
| `VAULT_PHRASE_MIN_WORDS` | 1 | Minimum words in the phrase |
| `VAULT_ATTACHMENT_MAX_KB` | 10240 | Max attachment size |
| `VAULT_CLIPBOARD_SECONDS` | 30 | Clear clipboard after copying |
| `APP_TIMEZONE` | UTC | Display timezone |

## Emergency commands

```bash
php artisan vault:unlock user@example.com      # clear brute-force lockout
php artisan vault:reset-2fa user@example.com   # set up 2FA again at next sign-in
php artisan vault:make-admin user@example.com  # promote to administrator
```

## Tech stack

- Laravel 12, SQLite
- libsodium (built into PHP)
- [lbuchs/webauthn](https://github.com/lbuchs/WebAuthn)
- Bootstrap 5.3, Bootstrap Icons, jQuery 3.7
- [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator)

## License

MIT
