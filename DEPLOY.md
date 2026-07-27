# Deploy notes

## Cache driver — `file` (not `database`)

Production runs with **`CACHE_STORE=file`** across all three env templates
(`.env`, `.env.example`, `.env.production`) and the Render service
(`render.yaml`).

### Why
The `cache` table was never migrated on the Neon Postgres database, which
caused every request hitting the `throttle:login` or `throttle:api`
middleware to fail with:

```
SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "cache" does not exist
```

The `file` driver needs no DB table and is fine for the only thing this
app uses cache for (rate limiting).

### Trade-off
On Render's free tier the filesystem is ephemeral — cache (and rate-limit
counters) is wiped on every restart/deploy. For this project that's
acceptable: users just get a fresh `60/min` allowance on the next boot.

### Switching back to `database`
Only do this **after** the table actually exists on the target DB. Steps:

1. Temporarily set `CACHE_STORE=file` in `render.yaml` (already the case).
2. Deploy once so the entrypoint can run all migrations, including
   `2024_01_01_000001_create_cache_table.php`.
3. Verify with `psql ... -c "\dt cache"` that the table exists.
4. Then change `CACHE_STORE` to `database` in `render.yaml` and redeploy.

---

## Migrations on Render

### The bug we fixed
`Dockerfile` used to run `php artisan migrate --force --no-interaction || true`
**during the Docker build**. Render only injects env vars (`DB_URL`, etc.) at
container start, not at build time, so:

- The build had no DB credentials.
- The `|| true` swallowed the error.
- The container shipped to Render with no migrations applied to Neon.
- Every cache-related request then 500'd.

### The fix
1. Removed the build-time migration from `Dockerfile`.
2. Moved it into `docker-entrypoint.sh`, which runs at container start
   when env vars are resolved.
3. The entrypoint logs migration failures loudly but does NOT crash the
   container — `/api/health` keeps responding so the service stays up
   while you investigate DB issues.

### Sanity-check after deploy
```bash
# Hit health
curl https://<your-service>.onrender.com/api/health

# Then trigger a login and watch the logs — should NOT 500.
```

---

## ⚠️ Security: hardcoded database credentials

`render.yaml` and `.env.production` both contain a real Neon password
in plain text. This file is safe to commit to a **private** repo, but
do NOT push it to a public GitHub repo.

**Recommended:**
- In `render.yaml`, change the `DB_URL` line to `sync: false` and set
  the value in the Render dashboard under Environment → Secret Files
  (or Environment Variables → marked as "secret").
- Keep `render.yaml` committed but with the value redacted.

```yaml
# Safer alternative for render.yaml
- key: DB_URL
  sync: false   # set the real value in Render dashboard
```

Also rotate the Neon password in the Neon console if this repo has ever
been public.
