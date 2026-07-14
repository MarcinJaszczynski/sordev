#!/usr/bin/env node
/**
 * Console audit runner — visits Filament URLs and collects browser console errors.
 *
 * Prerequisites:
 *   npm install
 *   npx playwright install chromium
 *   composer dev (or php artisan serve) running at APP_URL
 *
 * Usage:
 *   php artisan app:console-audit-urls
 *   node scripts/console-audit/run.mjs
 *   node scripts/console-audit/run.mjs --group=events
 */

import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '../..');

const args = process.argv.slice(2);
const groupArg = args.find((a) => a.startsWith('--group='));
const groupFilter = groupArg ? groupArg.split('=')[1] : null;

const baseUrl = (process.env.APP_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const urlsFile = process.env.CONSOLE_AUDIT_URLS || join(projectRoot, 'storage/console-audit/urls.json');
const reportFile = process.env.CONSOLE_AUDIT_REPORT || join(projectRoot, 'storage/console-audit/report.json');

const adminEmail = process.env.CONSOLE_AUDIT_ADMIN_EMAIL || 'console-audit@local';
const adminPassword = process.env.CONSOLE_AUDIT_ADMIN_PASSWORD || 'console-audit';
const pilotEmail = process.env.CONSOLE_AUDIT_PILOT_EMAIL || 'pilot@test.local';
const pilotPassword = process.env.CONSOLE_AUDIT_PILOT_PASSWORD || 'pilot123';

const allowlistPath = join(__dirname, 'allowlist.json');
const allowlist = JSON.parse(readFileSync(allowlistPath, 'utf8'));

function loadUrls() {
  if (!existsSync(urlsFile)) {
    console.error(`Brak pliku URL: ${urlsFile}\nUruchom: php artisan app:console-audit-urls`);
    process.exit(1);
  }

  const payload = JSON.parse(readFileSync(urlsFile, 'utf8'));
  let urls = payload.urls || [];

  if (groupFilter) {
    urls = urls.filter(
      (entry) =>
        entry.group === groupFilter ||
        (groupFilter === 'pilot' && entry.panel === 'pilot'),
    );
  }

  return urls;
}

function isNoise(text) {
  if (!text || typeof text !== 'string') {
    return true;
  }

  return allowlist.some((fragment) => text.includes(fragment));
}

function normalizeConsoleMessage(msg) {
  const parts = [msg.text()];
  for (const arg of msg.args()) {
    // args are handles; text() on message is usually enough
  }
  return parts.filter(Boolean).join(' ');
}

async function fillLivewireField(locator, value) {
  await locator.waitFor({ state: 'visible', timeout: 20000 });
  await locator.click();
  await locator.fill('');
  await locator.pressSequentially(value, { delay: 20 });
  // Filament login uses wire:model.live.debounce.500 — wait before submit.
  await locator.page().waitForTimeout(700);
}

async function login(page, panel) {
  const loginPath = panel === 'pilot' ? '/pilot/login' : '/admin/login';
  const email = panel === 'pilot' ? pilotEmail : adminEmail;
  const password = panel === 'pilot' ? pilotPassword : adminPassword;

  await page.goto(`${baseUrl}${loginPath}`, { waitUntil: 'networkidle', timeout: 45000 });

  const emailField = page.locator(
    '[id="data.email"], input[type="email"], input[name="email"], input[autocomplete="username"]',
  ).first();
  const passwordField = page.locator(
    '[id="data.password"], input[type="password"], input[name="password"], input[autocomplete="current-password"]',
  ).first();

  await fillLivewireField(emailField, email);
  await fillLivewireField(passwordField, password);

  const submitButton = page.locator(
    'button[type="submit"], form button.fi-btn, button:has-text("Zaloguj"), button:has-text("Sign in")',
  ).first();

  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 60000 }),
    submitButton.click(),
  ]);

  await page.waitForSelector('.fi-sidebar, .fi-main, .fi-simple-main', { timeout: 20000 });
}

async function visitUrl(context, entry, credentials) {
  const page = await context.newPage();
  const path = entry.path;
  const fullUrl = `${baseUrl}${path}`;
  const collected = [];

  page.on('console', (msg) => {
    const type = msg.type();
    if (type !== 'error' && type !== 'warning') {
      return;
    }

    const text = normalizeConsoleMessage(msg);
    if (isNoise(text)) {
      return;
    }

    collected.push({
      type,
      text,
      location: msg.location(),
    });
  });

  page.on('pageerror', (error) => {
    const text = error?.message || String(error);
    if (isNoise(text)) {
      return;
    }
    collected.push({
      type: 'pageerror',
      text,
      location: null,
    });
  });

  let status = 'ok';
  let httpStatus = null;
  let error = null;

  try {
    const response = await page.goto(fullUrl, {
      waitUntil: 'domcontentloaded',
      timeout: 45000,
    });
    httpStatus = response?.status() ?? null;

    if (httpStatus && httpStatus >= 400) {
      status = 'http_error';
    }

    await page.waitForSelector('.fi-main, .fi-simple-main, body', { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(800);
  } catch (e) {
    status = 'navigation_error';
    error = e.message;
  }

  await page.close();

  return {
    ...entry,
    url: fullUrl,
    status,
    httpStatus,
    navigationError: error,
    issues: collected,
  };
}

async function main() {
  const urls = loadUrls();

  if (urls.length === 0) {
    console.error('Brak URL do audytu.');
    process.exit(1);
  }

  console.log(`=== Console audit ===`);
  console.log(`Base: ${baseUrl}`);
  console.log(`URLs: ${urls.length}`);
  if (groupFilter) {
    console.log(`Group filter: ${groupFilter}`);
  }
  console.log('');

  const browser = await chromium.launch({ headless: true });
  const results = [];
  const panelsNeeded = new Set(urls.map((u) => u.auth));

  const contexts = {};
  for (const panel of panelsNeeded) {
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
      await login(page, panel);
      console.log(`✓ Zalogowano: ${panel}`);
    } catch (e) {
      console.error(`✗ Logowanie ${panel} nieudane: ${e.message}`);
      await browser.close();
      process.exit(1);
    }
    await page.close();
    contexts[panel] = context;
  }

  for (const entry of urls) {
    const context = contexts[entry.auth] || contexts.admin;
    process.stdout.write(`→ ${entry.path} ... `);
    const result = await visitUrl(context, entry, {});
    results.push(result);

    const issueCount = result.issues.length + (result.status !== 'ok' ? 1 : 0);
    if (issueCount === 0) {
      console.log('OK');
    } else {
      console.log(`${issueCount} problem(ów)`);
    }
  }

  await browser.close();

  const failures = results.filter(
    (r) => r.status !== 'ok' || r.issues.some((i) => i.type === 'error' || i.type === 'pageerror'),
  );

  const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl,
    group_filter: groupFilter,
    total: results.length,
    failed: failures.length,
    results,
  };

  mkdirSync(dirname(reportFile), { recursive: true });
  writeFileSync(reportFile, JSON.stringify(report, null, 2) + '\n');

  console.log('');
  console.log(`Raport: ${reportFile}`);
  console.log(`Podsumowanie: ${results.length - failures.length} OK, ${failures.length} z błędami`);

  if (failures.length > 0) {
    console.log('');
    console.log('--- Błędy ---');
    for (const fail of failures) {
      console.log(`\n${fail.path} (${fail.label})`);
      if (fail.status !== 'ok') {
        console.log(`  [${fail.status}] HTTP ${fail.httpStatus ?? '—'} ${fail.navigationError ?? ''}`);
      }
      for (const issue of fail.issues) {
        console.log(`  [${issue.type}] ${issue.text.slice(0, 300)}`);
      }
    }
    process.exit(1);
  }

  process.exit(0);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
