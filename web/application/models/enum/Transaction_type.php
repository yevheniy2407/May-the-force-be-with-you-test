<?php
namespace Model\Enum;
use System\Emerald\Emerald_enum;

class Transaction_type extends Emerald_enum
{
    const REFILL = 'refill';
    const BUY = 'buy';
    const SPEND_LIKES = 'spend_likes';
    const WON_LIKES = 'won_likes';
}
