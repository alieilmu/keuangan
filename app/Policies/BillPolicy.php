<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByUser;

class BillPolicy
{
    use OwnedByUser;
}
