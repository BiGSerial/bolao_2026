<?php

namespace App\Enums;

enum PoolMemberRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';
}
