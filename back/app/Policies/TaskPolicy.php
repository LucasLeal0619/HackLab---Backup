<?php

namespace App\Policies;

class TaskPolicy extends DemandPolicy
{
    protected function prefix(): string
    {
        return 'tasks';
    }
}
