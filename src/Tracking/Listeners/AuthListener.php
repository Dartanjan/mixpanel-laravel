<?php

namespace Duct\Tracking\Listeners;

use Duct\Tracking\Mixpanel;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Routing\Events\RouteMatched;
use Tracker;

class AuthListener
{
	/**
	 * Fired each time a user logs in.
	 * 
	 * @param  event $event 
	 * @return void        
	 */
	public function onLogin($event)
	{
		Tracker::people()->set('Last Login', Carbon::now()->format('Y-m-d\Th:i:s'));
		Tracker::people()->increment($event->user->id, 'Number of logins', 1);
		Tracker::track('Session', ['Action' => 'Log In']);
	}

	/**
	 * Fired each time a user logs out.
	 * 
	 * @param  event $event 
	 * @return void        
	 */
	public function onLogout($event)
	{
		Tracker::track('Session', ['Action' => 'Log Out']);
	}

	/**
	 * Only subscribe to these events if they are set in config
	 * 
	 * @param  event $events 
	 * @return void         
	 */
	public function subscribe($events)
	{
		$config = config('services.mixpanel.auto_track.user');
		if ($config && in_array('login', $config)) {
			$events->listen(Login::class, 'Duct\Tracking\Listeners\AuthListener@onLogin');
		}

		if ($config && in_array('logout', $config)) {
			$events->listen(Logout::class, 'Duct\Tracking\Listeners\AuthListener@onLogout');
		}
	}
}