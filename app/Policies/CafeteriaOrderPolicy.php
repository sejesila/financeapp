<?php

namespace App\Policies;

use App\Models\CafeteriaOrder;
use App\Models\User;

class CafeteriaOrderPolicy
{
    public function view(User $user, CafeteriaOrder $order): bool
    {
        return $user->id === $order->user_id;
    }

    public function update(User $user, CafeteriaOrder $order): bool
    {
        return $user->id === $order->user_id;
    }

    public function delete(User $user, CafeteriaOrder $order): bool
    {
        return $user->id === $order->user_id;
    }
}
