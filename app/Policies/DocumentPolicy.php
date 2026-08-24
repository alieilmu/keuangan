<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByUser;

class DocumentPolicy
{
    use OwnedByUser;
}
