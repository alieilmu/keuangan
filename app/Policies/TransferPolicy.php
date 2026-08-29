<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByUser;

class TransferPolicy
{
    use OwnedByUser;
}
