<?php

namespace App\Enums;

enum PoolMemberStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Removed = 'removed';
    case Blocked = 'blocked';
}
