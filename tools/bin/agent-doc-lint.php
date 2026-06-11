<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$requiredPaths = [
    'AGENTS.md',
    '.github/agents/planner.agent.md',
    '.github/agents/implementer.agent.md',
    '.github/agents/reviewer.agent.md',
    '.github/agents/cloud-pr.agent.md',
    '.github/copilot-instructions.md',
    '.github/instructions/backend.instructions.md',
    '.github/instructions/frontend-osoma.instructions.md',
    '.github/instructions/python.instructions.md',
    '.devcontainer/AGENTS.md',
];

foreach ($requiredPaths as $path) {
    if (!file_exists($root . '/' . $path)) {
        $errors[] = "Missing referenced path: {$path}";
    }
}

$forbiddenPaths = [
    '.agent/workflows/ninja-squad-osoma.md',
];

foreach ($forbiddenPaths as $path) {
    if (file_exists($root . '/' . $path)) {
        $errors[] = "Retired agent file still exists: {$path}";
    }
}

$agentFiles = glob($root . '/.github/agents/*.agent.md') ?: [];
foreach ($agentFiles as $file) {
    $relative = substr($file, strlen($root) + 1);
    $contents = file_get_contents($file);
    if ($contents === false) {
        $errors[] = "Cannot read {$relative}";
        continue;
    }

    foreach (['name:', 'description:', 'tools:'] as $requiredHeader) {
        if (!str_contains($contents, $requiredHeader)) {
            $errors[] = "{$relative} is missing front matter field {$requiredHeader}";
        }
    }

    if (!str_contains($contents, 'AGENTS.md')) {
        $errors[] = "{$relative} must point back to AGENTS.md";
    }
}

$companionFiles = array_merge(
    glob($root . '/.agent/rules/*.md') ?: [],
    glob($root . '/.agent/workflows/*.md') ?: [],
    glob($root . '/.agents/workflows/*.md') ?: [],
    glob($root . '/.github/instructions/*.md') ?: [],
    [
        $root . '/.devcontainer/AGENTS.md',
        $root . '/.deepagents/AGENTS.md',
        $root . '/.github/copilot-instructions.md',
        $root . '/CLAUDE.md',
    ],
);

foreach ($companionFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }

    $relative = substr($file, strlen($root) + 1);
    $contents = file_get_contents($file);
    if ($contents === false) {
        $errors[] = "Cannot read {$relative}";
        continue;
    }

    if (!str_contains($contents, 'AGENTS.md')) {
        $errors[] = "{$relative} must point back to AGENTS.md";
    }
}

$agents = file_get_contents($root . '/AGENTS.md');
if ($agents === false) {
    $errors[] = 'Cannot read AGENTS.md';
} else {
    preg_match_all('/`([^`\n]+)`/', $agents, $matches);
    $mentionedPaths = [];

    foreach ($matches[1] as $codeSpan) {
        $candidate = trim($codeSpan);
        if (
            $candidate === ''
            || str_contains($candidate, ' ')
            || str_contains($candidate, '*')
            || str_contains($candidate, '<')
            || str_contains($candidate, '>')
            || str_contains($candidate, '|')
            || str_starts_with($candidate, '#')
            || str_starts_with($candidate, '@')
        ) {
            continue;
        }

        $candidate = rtrim($candidate, '/');
        if (!str_contains($candidate, '/') && !str_starts_with($candidate, '.')) {
            continue;
        }

        if ($candidate !== '') {
            $mentionedPaths[$candidate] = true;
        }
    }

    foreach (array_keys($mentionedPaths) as $path) {
        if (!file_exists($root . '/' . $path)) {
            $errors[] = "AGENTS.md references missing path: {$path}";
        }
    }

    foreach (['Planner:', 'Implementer:', 'Reviewer:', 'Cloud PR Agent:'] as $promptLabel) {
        if (!str_contains($agents, $promptLabel)) {
            $errors[] = "AGENTS.md is missing task-entry prompt {$promptLabel}";
        }
    }

    if (!str_contains($agents, 'castor qa:agent-docs')) {
        $errors[] = 'AGENTS.md is missing documented command: castor qa:agent-docs';
    }
}

$qaCastor = file_get_contents($root . '/.castor/qa.php');
if ($qaCastor === false) {
    $errors[] = 'Cannot read .castor/qa.php';
} else {
    if (!str_contains($qaCastor, 'function agent_docs')) {
        $errors[] = '.castor/qa.php is missing qa:agent-docs implementation';
    }

    if (!str_contains($qaCastor, "aliases: ['agent-doc-lint']")) {
        $errors[] = '.castor/qa.php is missing agent-doc-lint alias';
    }
}

$ciWorkflow = file_get_contents($root . '/.github/workflows/ci.yml');
if ($ciWorkflow === false) {
    $errors[] = 'Cannot read .github/workflows/ci.yml';
} elseif (!str_contains($ciWorkflow, 'castor qa:agent-docs') && !str_contains($ciWorkflow, 'php tools/bin/agent-doc-lint.php')) {
    $errors[] = '.github/workflows/ci.yml must run the agent doc lint';
}

if ($errors !== []) {
    fwrite(STDERR, "Agent doc lint failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

echo "Agent doc lint passed.\n";
