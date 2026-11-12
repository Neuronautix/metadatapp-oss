import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const projectDir = path.resolve(scriptDir, '..');
const vitestBin = path.join(projectDir, 'node_modules', 'vitest', 'vitest.mjs');
const forwardedArgs = process.argv.slice(2);

function run(command, args, options = {}) {
  return spawnSync(command, args, {
    cwd: projectDir,
    env: process.env,
    encoding: 'utf8',
    ...options,
  });
}

function runVitest(captureOutput) {
  return run(process.execPath, [vitestBin, 'run', ...forwardedArgs], {
    stdio: captureOutput ? 'pipe' : 'inherit',
  });
}

function runPackageManagerInstall() {
  const packageManagerExec = process.env.npm_execpath;

  if (!packageManagerExec) {
    throw new Error('Cannot repair dependencies because npm_execpath is not set.');
  }

  return run(process.execPath, [packageManagerExec, 'install', '--frozen-lockfile', '--force'], {
    stdio: 'inherit',
  });
}

const firstAttempt = runVitest(true);

if (firstAttempt.stdout) {
  process.stdout.write(firstAttempt.stdout);
}

if (firstAttempt.stderr) {
  process.stderr.write(firstAttempt.stderr);
}

if (firstAttempt.status === 0) {
  process.exit(0);
}

const output = `${firstAttempt.stdout ?? ''}\n${firstAttempt.stderr ?? ''}`;

if (!output.includes('Cannot find module @rollup/rollup-linux-x64-gnu')) {
  process.exit(firstAttempt.status ?? 1);
}

process.stderr.write('\nRollup native package is missing. Reinstalling dependencies with pnpm and retrying Vitest.\n');

const repairAttempt = runPackageManagerInstall();

if (repairAttempt.status !== 0) {
  process.exit(repairAttempt.status ?? 1);
}

const retryAttempt = runVitest(false);

process.exit(retryAttempt.status ?? 1);
