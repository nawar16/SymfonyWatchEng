<?php

namespace App\Monitoring\Domain\ValueObject;

enum SubscriptionLimit: int 
{
    case MAX_MONITORS = 5;
}
