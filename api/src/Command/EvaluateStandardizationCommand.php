<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Eval\StandardizationEvalResult;
use App\AI\Eval\StandardizationEvaluator;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTask;
use App\ConnectedApps\Reference\Standardization\ReferenceStandardizationService;
use App\ConnectedApps\Reference\Standardization\StandardizationResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Live, metered evaluation harness for the AI "Standardize References" feature
 * (follow-up #5). Runs the real {@see ReferenceStandardizationService} against a
 * golden fixture's `items` — hitting whatever model provider is configured — then
 * scores the produced clusters with {@see StandardizationEvaluator} and reports
 * token spend pulled from {@see StandardizationResult::$usage}/provenance.
 *
 * This is the ONE place that may make a live provider call, and only when invoked
 * by a human. It is gated: it refuses to run unless the assistant is enabled AND a
 * budget cap (`--max-tokens`) is provided. Automated tests never exercise this path.
 */
#[AsCommand(
    name: 'app:ai:eval-standardization',
    description: 'Run a live, metered evaluation of the AI Standardize References feature against a golden set.',
)]
final class EvaluateStandardizationCommand extends Command
{
    private const string DEFAULT_FIXTURE = 'tests/Unit/ConnectedApps/Reference/Standardization/fixtures/standardization_golden.json';

    public function __construct(
        private readonly ReferenceStandardizationService $standardizer,
        private readonly StandardizationEvaluator $evaluator,
        private readonly AiFeatureFlags $featureFlags,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'fixture',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the golden fixture JSON (relative to the api/ project dir, or absolute).',
                self::DEFAULT_FIXTURE,
            )
            ->addOption(
                'max-tokens',
                null,
                InputOption::VALUE_REQUIRED,
                'Budget cap: required to run. Aborts after the call if reported token usage exceeds this.',
            )
            ->addOption(
                'json',
                null,
                InputOption::VALUE_NONE,
                'Emit the metrics as JSON on stdout (in addition to the human-readable table).',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('AI Standardize References — live evaluation');

        // --- Governance gate: AI must be enabled AND the task allowed. -----------
        if (!$this->featureFlags->isAssistantEnabled()
            || !$this->featureFlags->allowsTask(AssistantTask::STANDARDIZE_REFERENCES)
        ) {
            $io->error(
                'The AI assistant (or the standardize_references task) is disabled. '
                . 'This live eval is off by default; enable the assistant + task via AiFeatureFlags to run it.',
            );

            return Command::FAILURE;
        }

        // --- Budget cap is mandatory: no cap, no spend. --------------------------
        $maxTokensRaw = $input->getOption('max-tokens');
        if (null === $maxTokensRaw || !is_numeric($maxTokensRaw) || (int) $maxTokensRaw <= 0) {
            $io->error('A positive --max-tokens budget cap is required to run a live eval (it meters real provider spend).');

            return Command::FAILURE;
        }
        $maxTokens = (int) $maxTokensRaw;

        // --- Load the golden fixture. -------------------------------------------
        $fixturePath = $this->resolveFixturePath((string) $input->getOption('fixture'));
        $golden = $this->loadGolden($fixturePath);
        if (null === $golden) {
            $io->error(\sprintf('Could not load a valid golden fixture from "%s".', $fixturePath));

            return Command::FAILURE;
        }

        $io->writeln(\sprintf('<info>Fixture:</info> %s', $fixturePath));
        $io->writeln(\sprintf('<info>Items:</info> %d   <info>Budget cap:</info> %d tokens', \count($golden['items']), $maxTokens));
        $io->newLine();

        // --- Run the real standardization (live provider call). ------------------
        $result = $this->standardizer->standardize($golden['items']);

        if (!$result->aiAvailable) {
            $io->warning([
                'The standardization service fell back to the deterministic heuristic (aiAvailable=false).',
                'No live model produced these clusters, so the scores below reflect the fallback, not the provider.',
                ...$result->warnings,
            ]);
        }

        // --- Score the produced clusters against the golden expectations. --------
        $scores = $this->evaluator->evaluate(['clusters' => $golden['expected']['clusters']], $result);

        $this->renderScores($io, $scores);

        // --- Meter token spend + enforce the cap. --------------------------------
        $tokens = $this->reportUsage($io, $result, $maxTokens, $fixturePath, $scores);

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'fixture' => $fixturePath,
                'aiAvailable' => $result->aiAvailable,
                'tokens' => $tokens,
                'maxTokens' => $maxTokens,
                'metrics' => $scores->toArray(),
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        }

        if (null !== $tokens && $tokens > $maxTokens) {
            $io->error(\sprintf('Token usage (%d) exceeded the budget cap (%d).', $tokens, $maxTokens));

            return Command::FAILURE;
        }

        $io->success('Evaluation complete.');

        return Command::SUCCESS;
    }

    private function renderScores(SymfonyStyle $io, StandardizationEvalResult $scores): void
    {
        $io->section('Clustering metrics');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Pairwise precision', $this->pct($scores->precision)],
                ['Pairwise recall', $this->pct($scores->recall)],
                ['Pairwise F1', $this->pct($scores->f1())],
                ['Canonical-term accuracy', $this->pct($scores->termAccuracy)],
                ['Expected clusters', (string) $scores->expectedClusterCount],
                ['Produced clusters', (string) $scores->producedClusterCount],
            ],
        );

        if ([] !== $scores->perConcept) {
            $io->section('Per-concept breakdown');
            $rows = [];
            foreach ($scores->perConcept as $concept) {
                $rows[] = [
                    $concept->expectedIri,
                    \sprintf('%d / %d', $concept->overlap, $concept->expectedMembers),
                    $concept->producedIri ?? '(none)',
                    $concept->termMatched ? 'yes' : 'no',
                ];
            }
            $io->table(['Expected IRI', 'Members matched', 'Produced IRI', 'Term OK?'], $rows);
        }
    }

    /**
     * Meter the run's token spend to the structured logger and the console.
     *
     * @return int|null total tokens reported by the provider, or null if unknown
     */
    private function reportUsage(
        SymfonyStyle $io,
        StandardizationResult $result,
        int $maxTokens,
        string $fixturePath,
        StandardizationEvalResult $scores,
    ): ?int {
        $usage = $result->usage;
        $totalTokens = $this->extractTotalTokens($usage, $result->provenance);

        $io->section('Token usage');
        $io->table(
            ['Field', 'Value'],
            [
                ['Provider', (string) ($usage['provider'] ?? 'unknown')],
                ['Model', (string) ($usage['model'] ?? 'unknown')],
                ['Prompt tokens', $this->tokenField($usage, ['prompt_tokens', 'input_tokens'])],
                ['Completion tokens', $this->tokenField($usage, ['completion_tokens', 'output_tokens'])],
                ['Total tokens', null !== $totalTokens ? (string) $totalTokens : 'unknown'],
                ['Budget cap', (string) $maxTokens],
            ],
        );

        // Structured metering record: this is what an SRE/cost dashboard consumes.
        $this->logger->info('ai.eval.standardization.spend', [
            'fixture' => $fixturePath,
            'provider' => $usage['provider'] ?? null,
            'model' => $usage['model'] ?? null,
            'tokens_total' => $totalTokens,
            'max_tokens' => $maxTokens,
            'ai_available' => $result->aiAvailable,
            'precision' => $scores->precision,
            'recall' => $scores->recall,
            'term_accuracy' => $scores->termAccuracy,
        ]);

        // --- METRICS EXPORT HOOK -------------------------------------------------
        // Intentionally left as a commented hook: when the project gains an OTEL /
        // metrics exporter, emit a counter (e.g. ai_eval_tokens_total) and gauges
        // (ai_eval_precision/recall/term_accuracy) here, tagged by provider+model+
        // fixture. Do NOT add a new OTEL dependency just for this command; wire it
        // to whatever MeterInterface the app already exposes.
        //
        //   $this->meter->counter('ai_eval_tokens_total')->add($totalTokens ?? 0, [
        //       'provider' => $usage['provider'] ?? 'unknown',
        //       'model'    => $usage['model'] ?? 'unknown',
        //       'fixture'  => basename($fixturePath),
        //   ]);
        // -------------------------------------------------------------------------

        return $totalTokens;
    }

    /**
     * @param array<string, mixed>       $usage
     * @param list<array<string, mixed>> $provenance
     */
    private function extractTotalTokens(array $usage, array $provenance): ?int
    {
        foreach (['total_tokens', 'totalTokens', 'tokens'] as $key) {
            if (isset($usage[$key]) && is_numeric($usage[$key])) {
                return (int) $usage[$key];
            }
        }

        $prompt = $this->numericField($usage, ['prompt_tokens', 'input_tokens']);
        $completion = $this->numericField($usage, ['completion_tokens', 'output_tokens']);
        if (null !== $prompt || null !== $completion) {
            return (int) (($prompt ?? 0) + ($completion ?? 0));
        }

        // Fall back to any usage block carried in provenance entries.
        foreach ($provenance as $entry) {
            $entryUsage = \is_array($entry['usage'] ?? null) ? $entry['usage'] : null;
            if (null !== $entryUsage) {
                $total = $this->extractTotalTokens($entryUsage, []);
                if (null !== $total) {
                    return $total;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $usage
     * @param list<string>         $keys
     */
    private function tokenField(array $usage, array $keys): string
    {
        $value = $this->numericField($usage, $keys);

        return null !== $value ? (string) $value : 'unknown';
    }

    /**
     * @param array<string, mixed> $usage
     * @param list<string>         $keys
     */
    private function numericField(array $usage, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($usage[$key]) && is_numeric($usage[$key])) {
                return (float) $usage[$key];
            }
        }

        return null;
    }

    private function resolveFixturePath(string $fixture): string
    {
        if (str_starts_with($fixture, '/')) {
            return $fixture;
        }

        return rtrim($this->projectDir, '/') . '/' . ltrim($fixture, '/');
    }

    /**
     * @return array{items: list<array<string, mixed>>, expected: array{clusters: array<string, list<string>>}}|null
     */
    private function loadGolden(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if (false === $json) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $items = $data['items'] ?? null;
        $expected = $data['expected'] ?? null;
        if (!\is_array($items) || !\is_array($expected) || !\is_array($expected['clusters'] ?? null)) {
            return null;
        }

        /** @var array{items: list<array<string, mixed>>, expected: array{clusters: array<string, list<string>>}} $data */
        return $data;
    }

    private function pct(float $value): string
    {
        return \sprintf('%.3f (%.1f%%)', $value, $value * 100);
    }
}
