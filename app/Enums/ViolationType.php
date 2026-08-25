<?php

namespace App\Enums;

enum ViolationType: string
{
    case TabOrWindowSwitch = 'TAB_OR_WINDOW_SWITCH';
    case PageLeave = 'PAGE_LEAVE';
    case CopyAttempt = 'COPY_ATTEMPT';
    case CutAttempt = 'CUT_ATTEMPT';
    case ContextMenu = 'CONTEXT_MENU';
    case FullscreenExit = 'FULLSCREEN_EXIT';

    public function label(): string
    {
        return match ($this) {
            self::TabOrWindowSwitch => 'Tab Switch',
            self::PageLeave => 'Page Leave',
            self::CopyAttempt => 'Copy Attempt',
            self::CutAttempt => 'Cut Attempt',
            self::ContextMenu => 'Context Menu',
            self::FullscreenExit => 'Fullscreen Exit',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::TabOrWindowSwitch => 'You switched away from the examination.',
            self::PageLeave => 'You attempted to leave the examination page.',
            self::CopyAttempt => 'Copying examination content is prohibited.',
            self::CutAttempt => 'Cutting examination content is prohibited.',
            self::ContextMenu => 'The context menu is disabled during the examination.',
            self::FullscreenExit => 'You exited fullscreen mode during the examination.',
        };
    }

    /**
     * @return list<ViolationType>
     */
    public static function focusLossTypes(): array
    {
        return [
            self::TabOrWindowSwitch,
            self::PageLeave,
            self::FullscreenExit,
        ];
    }
}
