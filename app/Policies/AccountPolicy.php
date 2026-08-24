<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByUser;

class AccountPolicy
{
    use OwnedByUser;
}
