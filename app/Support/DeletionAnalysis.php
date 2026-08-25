<?php

namespace App\Support;

class DeletionAnalysis
{
    /**
     * @param  array<int, array{label: string, count: int}>  $blockers
     */
    public function __construct(
        public bool $canDelete,
        public string $recordType,
        public string $recordName,
        public ?string $recordDetail = null,
        public string $warningMessage = '',
        public array $blockers = [],
        public string $confirmLabel = 'Delete',
    ) {
    }

    public function blockedMessage(): string
    {
        if ($this->blockers === []) {
            return 'This record cannot be deleted because it has related data.';
        }

        $lines = collect($this->blockers)
            ->map(fn (array $blocker) => "{$blocker['count']} {$blocker['label']}")
            ->implode("\n- ");

        return "This {$this->recordType} cannot be deleted because it is currently associated with:\n\n- {$lines}\n\nPlease reassign or remove the related records before deleting it.";
    }
}
