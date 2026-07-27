# 🚀 Deploy to Render + Neon (Free)

## Architecture

```
┌──────────────────┐         ┌──────────────────┐
│  Render (Free)   │ ──────► │   Neon (Free)    │
│  Laravel API     │  HTTPS  │   PostgreSQL     │
│  shop-backend    │  port   │   neondb         │
│  Frankfurt, EU   │  5432   │   Ohio, US       │
└──────────────────┘         └──────────────────┘
```

## Step 1 — Push to GitHub

```bash
cd shop-backend
git init
git add .
git commit -m "feat: ready for Render + Neon deploy"
git remote add origin https://github.com/devDadvar013/shop-backend.git
git push -u origin main
```

## Step 2 — Get your APP_KEY

```bash
php artisan key:generate --show
```

Copy the output (starts with `base64:`).

## Step 3 — Create Render Web Service

1. Go to https://dashboard.render.com → **New +** → **Web Service**
2. Connect GitHub → select `shop-backend` repo
3. Fill the form:

| Field | Value |
|-------|-------|
| Name | `shop-backend` |
| Language | `Docker` |
| Branch | `main` |
| Region | `Frankfurt` |
| Instance Type | `Free` |

4. Click **Advanced** → set **Health Check Path** to `/api/health`

5. Click **Add Environment Variable** for each of these:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:paste_your_key_here
APP_URL=https://shop-backend.onrender.com
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
DB_URL=postgresql://neondb_owner:npg_N9slr2hOIYKQ@ep-raspy-heart-axxo4vd6-pooler.c-4.us-east-2.aws.neon.tech/neondb?sslmode=require&channel_binding=require
SESSION_DRIVER=array
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

6. Click **Create Web Service**

## Step 4 — Wait & test

Render will build the Docker image (3-5 min first time), then deploy.

Test:
```bash
curl https://shop-backend.onrender.com/api/health
```

## ⚠️ Free tier notes

- Render sleeps after 15 min idle → first request takes 30-50s
- Neon also sleeps after ~5 min idle → first DB query takes 1-2s
- Combined cold start: ~30-60s on first request
- Use https://cron-job.org to ping `/api/health` every 14 min to keep awake

## 🔧 What's in this repo

- `Dockerfile` — PHP 8.3 + Apache + Laravel (with pgsql support)
- `docker-entrypoint.sh` — handles Render's dynamic `$PORT`
- `.dockerignore` — keeps Docker build small
- `render.yaml` — Render Blueprint
- `.env.production` — env vars for Render (DB_URL already filled)
- `bootstrap/app.php` — CSRF fix (statefulApi disabled)
- `config/database.php` — pgsql connection added
- `config/cors.php` — token API friendly (no credentials)

## 🐛 Troubleshooting

**"could not find driver" on pgsql?**
- The Dockerfile installs `pdo_pgsql` — should work.

**"SQLSTATE[08006]" connection error?**
- Make sure `DB_URL` is correctly pasted (no extra spaces, no missing parts).

**Migrations didn't run?**
- They run during Docker build with `|| true` (silent failure).
- Check Render build log for "migrate" output.

**"CSRF token mismatch"?**
- The fix is already applied (`statefulApi()` disabled).
- Make sure you deployed the latest version with the fix.

## 📝 Region note

Your Neon project is in **US East (Ohio)**. Render is in **Frankfurt (EU)**. Latency is fine (~100ms) for a portfolio project. If you want them in the same region, recreate the Neon project in EU Central (Frankfurt) and update the connection string.
