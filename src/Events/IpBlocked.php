<?php

declare(strict_types=1);

namespace LogScopeGuard\Events;

use LogScopeGuard\Models\BlacklistedIp;

class IpBlocked
{
    public function __construct(public readonly BlacklistedIp $record) {}
}
