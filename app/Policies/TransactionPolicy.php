<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByUser;

class TransactionPolicy
{
    use OwnedByUser;
}
