<?php

declare(strict_types=1);

namespace Watchtower\Events;

use Watchtower\Models\BlacklistedIp;

class IpBlocked
{
    public function __construct(public readonly BlacklistedIp $record) {}
}
