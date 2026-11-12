<?php

namespace App\Enums;

enum Category: string
{
    case Billing = 'billing';
    case Sales = 'sales';
    case Support = 'support';
    case Unknown = 'unknown';
}
