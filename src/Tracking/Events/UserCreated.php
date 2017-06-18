<?php

namespace Duct\Tracking\Events;

use Duct\Models\User;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use SerializesModels;

    public $user;

    /**
     * Create a new event instance.
     *
     * @param  Order  $user
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }
}