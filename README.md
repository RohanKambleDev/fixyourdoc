# FixYourDoc

Upload a Word manuscript (`.docx`), describe the formatting rules a journal
wants, and get back a cleanly formatted copy. The rules are a JSON spec edited
through a form, so the same document can be reformatted for different journals.

Built with Laravel 13, Jetstream (Livewire) and a Python formatter
(`python-docx`) that does the actual document editing.

## What it can format

| Area | Settings |
| --- | --- |
| Page | A4 or Letter, four margins (`in`, `cm`, `mm`) |
| Font | Family and size, applied to Word styles (and optionally every run) |
| Paragraphs | Line spacing, space before and after, alignment |
| Headings | Font, size per heading level, bold |
| Page numbers | Header or footer, alignment, start number |
| Images | Shrink to the usable page width, centre |
| Tables | Width, alignment, font and size |
| References | Hanging indent, numbering style, spacing, stops at the next heading |
| Clean-up | Remove empty paragraphs, collapse repeated blank lines |

Settings that are left blank use the formatter's own defaults. The Advanced
(JSON) tab accepts only settings the formatter understands; anything else is
rejected with the name of the setting, so a rule is never silently ignored.

## Requirements

- PHP 8.4 or newer (the site's PHP-FPM pool must match; check with `php -v`)
- Composer, Node and npm
- Python 3 with `venv`
- A database supported by Laravel (MariaDB in `.env.example`)

## Setup

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
# edit .env: database settings, APP_URL

php artisan migrate

# Python environment for the formatter
python3 -m venv scripts/venv
scripts/venv/bin/pip install -r scripts/requirements.txt
```

`DOCX_FORMATTER_PYTHON` in `.env` overrides the interpreter. It defaults to
`scripts/venv/bin/python`. Use an absolute path if you point it elsewhere,
because queue workers do not share your shell's `PATH`.

### Run the queue worker

Formatting runs in a queued job. Nothing happens to an uploaded document until
a worker is running:

```bash
php artisan queue:work --queue=formatting,default
```

Formatting jobs use the `formatting` queue. Listing `default` as well keeps
any older waiting jobs moving.

In production, keep it running with a process manager such as Supervisor, and
run it as the same system user as PHP-FPM (usually `www-data`). Uploads are
saved privately by that user, so a worker running as anyone else cannot read
them. Run two worker processes (`numprocs=2` in Supervisor) so one user's long
job does not hold up everyone else. Restart the workers after each deploy, for
example with `supervisorctl restart fixyourdoc-queue:*`.

### Web server notes

- **nginx:** if you have a rule that serves static extensions (`.js`, `.css`
  and so on), give it a fallback to Laravel, otherwise Livewire's script at
  `/livewire/livewire.js` returns 404:

  ```nginx
  location ~* \.(js|css|png|jpg|jpeg|gif|ico)$ {
      try_files $uri /index.php?$query_string;
      expires max;
  }
  ```

- **Upload size:** the app accepts files up to 10 MB by default
  (`DOCX_FORMATTER_MAX_UPLOAD_KB`). PHP's `upload_max_filesize` and
  `post_max_size`, and nginx's `client_max_body_size`, must be at least that
  large. Livewire's own temporary upload limit is 12 MB.

## Configuration

All optional, set in `.env`:

| Variable | Default | Purpose |
| --- | --- | --- |
| `DOCX_FORMATTER_PYTHON` | `scripts/venv/bin/python` | Interpreter with `python-docx` installed |
| `DOCX_FORMATTER_TIMEOUT` | `120` | Seconds before a formatting run is stopped |
| `DOCX_FORMATTER_MAX_UPLOAD_KB` | `10240` | Largest accepted upload |
| `DOCX_FORMATTER_UPLOADS_PER_HOUR` | `10` | Documents one account may format per hour (placeholder until per-plan limits exist) |
| `DOCX_FORMATTER_MAX_UNCOMPRESSED_MB` | `100` | Largest accepted size once unpacked (guards against zip bombs) |
| `DOCX_FORMATTER_DISK` | `local` | Storage disk for uploads and results |

## How it works

1. **Upload** (`app/Livewire/FormatDocument.php`): the file is checked
   (`app/Rules/ValidDocx.php`), the spec is normalised and validated
   (`app/Support/FormatSpec.php`), the file is stored privately and a
   `FormatJob` is created.
2. **Job** (`app/Jobs/FormatDocumentJob.php`): runs
   `app/Services/DocxFormatter.php`, which calls `scripts/docx_formatter.py`
   with the spec written to a temporary file. Arguments are passed as an array,
   never through a shell.
3. **History** (`app/Livewire/FormatHistory.php`): shows each job's status and
   offers the original and formatted files. Downloads are limited to the owner
   (`app/Policies/FormatJobPolicy.php`).

Files live under `storage/app/private/format/{user id}/{job id}/`. Deleting a
job from the history page, or deleting a user, removes its files.

## Sign-up protection

The register form carries two bot traps: a hidden "website" field that people
never fill in, and an encrypted timestamp. A sign-up is rejected if the hidden
field is filled, or if the form was submitted in under 3 seconds or more than
2 hours after it was loaded (`app/Actions/Fortify/CreateNewUser.php`). This
stops simple scripts only. Email verification is not enabled: it needs real
outgoing mail first (`MAIL_MAILER` is `log` by default, which writes emails to
the log instead of sending them).

## Running the formatter by hand

```bash
scripts/venv/bin/python scripts/docx_formatter.py \
  --input manuscript.docx --spec spec.json --output formatted.docx
```

Add `--force-runs` to remove manual font and size changes inside the text, so
the text follows its style instead (headings keep their heading sizes and
tables keep the table settings). Fonts set by hand, such as pasted text in
another font, are otherwise left as they are. In the app this is the
"Remove manual font changes" option.

## Tests

```bash
php artisan test
```

The tests check the formatter's output by reading the resulting XML. They
cannot show how a document looks in Word, so check a real manuscript before
relying on a new spec.

## Known limitations

- Journal checklist items such as word counts, abstract structure and keyword
  limits are not checked or enforced. Only formatting is applied.
- Page numbers are Word fields, so Word updates them when the file is opened.
- Table and image rules apply to the first section's usable page width.

## License

The Laravel framework is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
