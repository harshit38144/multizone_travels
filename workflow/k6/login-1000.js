/**
 * k6 — 1,000 simultaneous Admin logins
 *
 * Target: Multizone Travels admin panel
 * Form: POST admin/action.php  (name, pass, adminlogin)
 *
 * Usage (PowerShell):
 *   $env:BASE_URL="http://localhost/demo/multizone_travels/multizone_travels/admin"
 *   $env:LOGIN_USER="your_admin_username"
 *   $env:LOGIN_PASS="your_admin_password"
 *   k6 run workflow/k6/login-1000.js
 *
 * Optional:
 *   $env:VUS="1000"           # concurrent virtual users (default 1000)
 *   $env:THINK_MS="0"         # delay before each login (ms)
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost/demo/multizone_travels/multizone_travels/admin').replace(/\/$/, '');
const LOGIN_USER = __ENV.LOGIN_USER || '';
const LOGIN_PASS = __ENV.LOGIN_PASS || '';
const VUS = Number(__ENV.VUS || 1000);
const THINK_MS = Number(__ENV.THINK_MS || 0) / 1000;

const loginFailRate = new Rate('login_fail_rate');
const loginSuccess = new Counter('login_success');
const loginFailed = new Counter('login_failed');
const loginDuration = new Trend('login_duration', true);

if (!LOGIN_USER || !LOGIN_PASS) {
  throw new Error('Set LOGIN_USER and LOGIN_PASS env vars before running.');
}

export const options = {
  scenarios: {
    // Closest to "1,000 people hit Login at once"
    simultaneous_logins: {
      executor: 'per-vu-iterations',
      vus: VUS,
      iterations: 1,
      maxDuration: '3m',
      gracefulStop: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],       // <5% transport/HTTP failures
    login_fail_rate: ['rate<0.10'],       // <10% auth/redirect failures
    http_req_duration: ['p(95)<5000'],    // 95% of requests under 5s
    checks: ['rate>0.90'],
  },
  // Avoid runaway if server collapses
  discardResponseBodies: false,
};

function formBody(user, pass) {
  return {
    name: user,
    pass: pass,
    adminlogin: '1',
  };
}

export default function () {
  if (THINK_MS > 0) {
    sleep(THINK_MS);
  }

  const url = `${BASE_URL}/action.php`;
  const payload = formBody(LOGIN_USER, LOGIN_PASS);

  const res = http.post(url, payload, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'User-Agent': 'k6-login-loadtest/1.0',
    },
    redirects: 0, // inspect Location ourselves
    tags: { endpoint: 'admin_login' },
  });

  loginDuration.add(res.timings.duration);

  const location = String(res.headers.Location || res.headers.location || '');
  const redirectedToApp =
    res.status === 302 || res.status === 301 || res.status === 303
      ? /app\.php/i.test(location)
      : false;

  // Some PHP setups may follow differently; also accept 200 with session cookie after success page
  const hasSessionCookie = String(res.headers['Set-Cookie'] || res.headers['set-cookie'] || '').length > 0;
  const looksFailed =
    /index\.php/i.test(location) ||
    (res.status >= 400);

  const ok = check(res, {
    'status is 2xx/3xx': (r) => r.status >= 200 && r.status < 400,
    'login redirected to app.php OR session set': () => redirectedToApp || (hasSessionCookie && !looksFailed),
    'not redirected back to index.php (invalid creds)': () => !/index\.php/i.test(location) || redirectedToApp,
  });

  if (ok && (redirectedToApp || (hasSessionCookie && !looksFailed))) {
    loginSuccess.add(1);
    loginFailRate.add(0);
  } else {
    loginFailed.add(1);
    loginFailRate.add(1);
  }
}

export function handleSummary(data) {
  const path = 'workflow/k6/results/login-1000-summary.json';
  return {
    stdout: textSummary(data),
    [path]: JSON.stringify(data, null, 2),
  };
}

function textSummary(data) {
  const m = data.metrics || {};
  const get = (name, key) => (m[name] && m[name].values && m[name].values[key] != null ? m[name].values[key] : 'n/a');

  return `
============================================================
 k6 Login Spike — ${VUS} simultaneous logins
 Target: ${BASE_URL}/action.php
============================================================
 http_reqs           : ${get('http_reqs', 'count')}
 login_success       : ${get('login_success', 'count')}
 login_failed        : ${get('login_failed', 'count')}
 login_fail_rate     : ${get('login_fail_rate', 'rate')}
 http_req_duration   : avg=${get('http_req_duration', 'avg')}  p95=${get('http_req_duration', 'p(95)')}  max=${get('http_req_duration', 'max')}
 http_req_failed     : ${get('http_req_failed', 'rate')}
 checks              : ${get('checks', 'rate')}
============================================================
`;
}
