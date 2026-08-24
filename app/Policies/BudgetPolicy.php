<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByUser;

class BudgetPolicy
{
    use OwnedByUser;
}
