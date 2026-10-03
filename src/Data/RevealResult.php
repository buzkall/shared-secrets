<?php

namespace Arzcode\SharedSecrets\Data;

use Arzcode\SharedSecrets\Enums\RevealOutcome;
use SensitiveParameter;

final readonly class RevealResult
{
    public function __construct(
        public RevealOutcome $outcome,
        #[SensitiveParameter]
        public ?string $content = null,
        public ?int $remainingViews = null,
    ) {}

    public function isRevealed(): bool
    {
        return $this->outcome === RevealOutcome::Revealed;
    }
}
