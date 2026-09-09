# k6 — 1,000 simultaneous Admin logins

Load-tests the Multizone Travels admin login form:

`POST {BASE_URL}/action.php` with `name`, `pass`, `adminlogin`

## 1. Install k6 (Windows)

```powershell
winget install Grafana.k6
```

Or download from: https://grafana.com/docs/k6/latest/set-up/install/windows/

Restart the terminal after install, then:

```powershell
k6 version
```

## 2. Run against local XAMPP (recommended first)

```powershell
cd C:\xampp\htdocs\demo\multizone_travels\multizone_travels

$env:BASE_URL="http://localhost/demo/multizone_travels/multizone_travels/admin"
$env:LOGIN_USER="YOUR_ADMIN_USERNAME"
$env:LOGIN_PASS="YOUR_ADMIN_PASSWORD"
$env:VUS="1000"

k6 run workflow/k6/login-1000.js
```

## 3. Smaller smoke test first

Before 1,000 VUs, verify credentials and URL:

```powershell
$env:VUS="10"
k6 run workflow/k6/login-1000.js
```

## 4. Production / Hostinger warning

Do **not** run 1,000 concurrent logins on live production without approval. It can:

- exhaust PHP-FPM / MySQL connections
- trigger WAF / rate limits
- create many `admin_log_history` rows
- lock the server for real users

Prefer staging or local first.

## What “simultaneous” means here

The script uses k6 `per-vu-iterations` with **1000 VUs × 1 iteration**, so each virtual user performs **one login** in the same spike window (closest practical “1,000 at once” model).

## Success criteria (thresholds)

| Metric | Target |
|--------|--------|
| HTTP failures | &lt; 5% |
| Login fail rate | &lt; 10% |
| p95 response time | &lt; 5s |
| Checks pass rate | &gt; 90% |

Summary JSON is written to `workflow/k6/results/login-1000-summary.json` when the run finishes.
