<?php 

namespace Duct\Tracking;

use Duct\Tracking\Events\UserCreated;
use Illuminate\Database\Eloquent\Model;

class TrackingUserObserver
{
	/**
	 * @param Model $user
	 */
	public function created(Model $user)
	{
		event(new UserCreated($user));
	}
}
