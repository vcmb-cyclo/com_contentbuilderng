import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const exception = 'https://github.com/advisories/GHSA-vfj7-8cjw-p6xm';
const blockingSeverities = new Set(['moderate', 'high', 'critical']);

function checkAudit(report, lock) {
  if (report.error || report.auditReportVersion !== 2 || !report.vulnerabilities) {
    throw new Error('Unable to obtain a valid npm audit report.');
  }
  const blocked = [];
  const ignored = [];
  function onlyApprovedAdvisory(name, visited = new Set()) {
    if (visited.has(name)) return false;
    const entry = report.vulnerabilities[name];
    if (!entry || !entry.via?.length || !entry.nodes?.length) return false;
    if (!entry.nodes.every(node => lock.packages?.[node]?.dev === true)) return false;
    const next = new Set(visited).add(name);
    return entry.via.every(via => typeof via === 'string'
      ? onlyApprovedAdvisory(via, next)
      : name === 'braces' && via.name === 'braces' && via.url === exception);
  }
  for (const [name, entry] of Object.entries(report.vulnerabilities)) {
    if (!blockingSeverities.has(entry.severity)) continue;
    (onlyApprovedAdvisory(name) ? ignored : blocked).push(name);
  }
  return { blocked, ignored };
}

if (process.argv.includes('--self-test')) {
  const advisory = { name: 'braces', url: exception };
  const leaf = { severity: 'high', via: [advisory], nodes: ['node_modules/braces'] };
  const lock = { packages: { 'node_modules/braces': { dev: true }, 'node_modules/micromatch': { dev: true } } };
  const report = vulnerabilities => ({ auditReportVersion: 2, vulnerabilities });
  assert.deepEqual(checkAudit(report({ braces: leaf }), lock).blocked, []);
  assert.deepEqual(checkAudit(report({ braces: leaf, micromatch: { severity: 'high', via: ['braces'], nodes: ['node_modules/micromatch'] } }), lock).blocked, []);
  assert.deepEqual(checkAudit(report({ braces: { ...leaf, via: [advisory, { name: 'braces', url: 'https://github.com/advisories/OTHER' }] } }), lock).blocked, ['braces']);
  assert.deepEqual(checkAudit(report({ braces: leaf }), { packages: { 'node_modules/braces': { dev: false } } }).blocked, ['braces']);
  assert.deepEqual(checkAudit(report({ braces: leaf }), { packages: {} }).blocked, ['braces']);
  assert.throws(() => checkAudit({ error: {} }, lock));
  console.log('Audit exception self-tests passed (6 cases).');
} else {
  try {
    const result = spawnSync('npm', ['audit', '--json', '--audit-level=moderate'], { encoding: 'utf8' });
    if (result.error || ![0, 1].includes(result.status)) throw new Error('npm audit could not complete.');
    const report = JSON.parse(result.stdout);
    const lock = JSON.parse(readFileSync(new URL('../package-lock.json', import.meta.url), 'utf8'));
    const { blocked, ignored } = checkAudit(report, lock);
    if (ignored.length) console.log(`Approved development-only exception ${exception}: ${ignored.join(', ')}`);
    if (blocked.length) {
      console.error(`Blocking npm vulnerabilities: ${blocked.join(', ')}`);
      console.error(JSON.stringify(report, null, 2));
      process.exitCode = 1;
    } else console.log('No unapproved moderate, high or critical npm vulnerabilities.');
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
