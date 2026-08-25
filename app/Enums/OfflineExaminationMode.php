<?php

namespace App\Enums;

enum OfflineExaminationMode: string
{
    case Disabled = 'disabled';
    case Allowed = 'allowed';
    case RequiredPreparation = 'required_preparation';

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Disabled',
            self::Allowed => 'Allowed',
            self::RequiredPreparation => 'Required Preparation',
        };
    }

    public function supportsOffline(): bool
    {
        return $this !== self::Disabled;
    }
}
