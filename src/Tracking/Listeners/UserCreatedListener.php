<?php

namespace Duct\Tracking\Listeners;

use Duct\Tracking\Events\UserCreated;
use Tracker;

class UserCreatedListener
{
    /**
     * Handle the event.
     *
     * @param  UserCreated  $event
     * @return void
     */
    public function handle(UserCreated $event)
    {
        $user = $event->user;

        $firstName = $user->first_name;
        $lastName = $user->last_name;

        if ($user->name) {
            $nameParts = explode(' ', $user->name);
            array_filter($nameParts);
            $lastName = array_pop($nameParts);
            $firstName = implode(' ', $nameParts);
        }

        $data = [
            '$first_name' => $firstName,
            '$last_name' => $lastName,
            '$name' => $user->name,
            '$email' => $user->email,
            '$created' => ($user->created_at
                ? $user->created_at->format('Y-m-d\Th:i:s')
                : null),
        ];
        array_filter($data);

        Tracker::alias($user->id);

        if (count($data)) {
            Tracker::people()->set($user->id, $data, request()->ip());
        }

        Tracker::track('User', ['Status' => 'Registered']);
    }
}