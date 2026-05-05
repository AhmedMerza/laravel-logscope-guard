<?php

declare(strict_types=1);

namespace Watchtower\Enums;

enum BlockSource: string
{
    case Manual = 'manual';
    case Auto   = 'auto';
    case Sync   = 'sync';
}
