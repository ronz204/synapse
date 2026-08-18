<?php

declare(strict_types=1);

namespace App\Support\Performance;

use InvalidArgumentException;

/**
 * One observed execution of one interaction
 * (specs/002-perceived-performance/data-model.md, entity 3).
 *
 * Two layers produce these and each is blind to what the other sees: the
 * deterministic Pest layer counts queries and serialized rows but never
 * observes paint, and the browser layer observes paint but never sees SQL. The
 * fields the producing layer could not observe stay null — never zero. A zero
 * would assert "measured, and it was none", which is a different and false
 * claim.
 */
final readonly class InteractionMeasurement
{
    public const LAYER_DETERMINISTIC = 'deterministic';

    public const LAYER_BROWSER = 'browser';

    public function __construct(
        public string $module,
        public string $interaction,
        public InteractionClass $class,
        public int $contentReadyMs,
        public string $layer,
        public ?int $firstPaintMs = null,
        public ?int $queryCount = null,
        public ?int $serializedRows = null,
    ) {
        if ($this->firstPaintMs === null && $this->queryCount === null) {
            throw new InvalidArgumentException(
                "Measurement [{$module}/{$interaction}] observed neither paint nor queries — it measured nothing."
            );
        }

        if ($this->firstPaintMs !== null && $this->contentReadyMs < $this->firstPaintMs) {
            throw new InvalidArgumentException(
                "Measurement [{$module}/{$interaction}] reports content ready before first paint — instrumentation error, not a result."
            );
        }

        if (! in_array($this->layer, [self::LAYER_DETERMINISTIC, self::LAYER_BROWSER], true)) {
            throw new InvalidArgumentException("Unknown measurement layer [{$this->layer}].");
        }
    }

    public static function fromBrowser(
        string $module,
        string $interaction,
        InteractionClass $class,
        int $firstPaintMs,
        int $contentReadyMs,
    ): self {
        return new self(
            module: $module,
            interaction: $interaction,
            class: $class,
            contentReadyMs: $contentReadyMs,
            layer: self::LAYER_BROWSER,
            firstPaintMs: $firstPaintMs,
        );
    }

    public static function fromServer(
        string $module,
        string $interaction,
        InteractionClass $class,
        int $renderMs,
        int $queryCount,
        int $serializedRows,
    ): self {
        return new self(
            module: $module,
            interaction: $interaction,
            class: $class,
            contentReadyMs: $renderMs,
            layer: self::LAYER_DETERMINISTIC,
            queryCount: $queryCount,
            serializedRows: $serializedRows,
        );
    }

    /**
     * Stable key pairing this measurement with its counterpart in the baseline.
     * If these strings change, comparison against baseline.json breaks silently
     * — which is why the report contract requires announcing a rename.
     */
    public function key(): string
    {
        return "{$this->module}/{$this->interaction}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'interaction' => $this->interaction,
            'class' => $this->class->value,
            'contentReadyMs' => $this->contentReadyMs,
            'firstPaintMs' => $this->firstPaintMs,
            'queryCount' => $this->queryCount,
            'serializedRows' => $this->serializedRows,
            'layer' => $this->layer,
        ];
    }

    /**
     * The percentile of a set of observations of the same interaction. A single
     * measurement never decides compliance — perceived slowness comes from the
     * occasional bad case, which an average hides and a percentile does not.
     *
     * @param  array<int, int>  $observations
     */
    public static function percentileOf(array $observations, int $percentile): int
    {
        if ($observations === []) {
            throw new InvalidArgumentException('Cannot take a percentile of an empty observation set.');
        }

        sort($observations);

        $index = (int) ceil($percentile / 100 * count($observations)) - 1;

        return $observations[max(0, min($index, count($observations) - 1))];
    }
}
