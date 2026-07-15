<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Simple CLI progress bar.
 *
 *   $bar = new ProgressBar(100, 'Importing');
 *   for ($i = 0; $i < 100; $i++) {
 *       doWork();
 *       $bar->advance();
 *   }
 *   $bar->finish();
 *
 * Output while running:
 *   Importing  [=========>          ]  45/100  45%
 */
class ProgressBar
{
    private int $current  = 0;
    private int $width    = 30;
    private bool $started = false;

    public function __construct(
        private readonly int $total,
        private readonly string $label = '',
    ) {}

    public function setWidth(int $width): static
    {
        $this->width = max(10, $width);
        return $this;
    }

    public function advance(int $step = 1): void
    {
        $this->current = min($this->total, $this->current + $step);
        $this->render();
    }

    public function setProgress(int $current): void
    {
        $this->current = min($this->total, max(0, $current));
        $this->render();
    }

    public function finish(): void
    {
        $this->current = $this->total;
        $this->render();
        echo "\n";
    }

    private function render(): void
    {
        $pct      = $this->total > 0 ? $this->current / $this->total : 1.0;
        $filled   = (int)round($pct * $this->width);
        $empty    = $this->width - $filled;
        $bar      = str_repeat('=', max(0, $filled - 1)) . ($filled > 0 ? '>' : '') . str_repeat(' ', $empty);
        $pctLabel = str_pad((string)(int)round($pct * 100), 3, ' ', STR_PAD_LEFT) . '%';
        $counter  = str_pad((string)$this->current, strlen((string)$this->total), ' ', STR_PAD_LEFT)
                  . '/' . $this->total;

        $label = $this->label !== '' ? $this->label . '  ' : '';
        echo "\r{$label}[{$bar}]  {$counter}  {$pctLabel}";

        if (!$this->started) $this->started = true;
    }
}
