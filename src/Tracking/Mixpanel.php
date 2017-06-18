<?php

namespace Duct\Tracking;

use Duct\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Device;
use Sinergi\BrowserDetector\Os;
use Auth;
use Log;

class Mixpanel extends \Mixpanel
{
	protected $cookieName = 'mp_distinct_id';

    /**
     * Event properties to be sent with all events
     * 
     * @var array
     */
    protected $superProperties = [];

    /**
     * Store distinct_id for the current session.
     * 
     * @var integer
     */
    protected $distinctId;

    /**
     * If in debug mode, output logs to Log::info()
     * 
     * @var boolean
     */
    protected $debug = false;
	
	/**
	 * Initiate Mixpanel object
	 * 
	 * @param Request $request 
	 */
	function __construct(Request $request)
	{
        $this->request = $request;

        parent::__construct(config('services.mixpanel.token'), config('services.mixpanel.connection'));

		$this->createDistinctId();

        $this->log("Distinct ID: {$this->distinctId()}");
	}

    /**
     * Output log if debug is on
     * 
     * @param  string $log 
     * @return void      
     */
    public function log($log)
    {
        if ($this->debug) {
            Log::info($log);
        }
    }

    /**
     * Return People consumer.
     *
     * Needed for facades, e.g. Tracker::people()->set(...)
     * 
     * @return Producers_MixpanelPeople 
     */
    public function people()
    {
        return $this->people;
    }

	/**
	 * Shortcut to track page views
	 * 
	 * @param  array  $options Optional event properties. Default []
	 * @return void          
	 */
	public function trackPageView($options=[])
	{
		$this->track('Page Viewed', $options);
	}

    /**
     * @param string $event
     * @param array  $props
     *
     * @internal param array $data
     */
    public function track($event, $props = [])
    {
		$this->identity();

        $props = array_merge($this->getSuperProps(), array_filter($props));

        \Log::info("Sending $event with props: ", $props);

        parent::track($event, $props);

        return $this;
    }

    /**
     * Return an array of properties describing this session
     * 
     * @return array 
     */
    public function getSessionProps()
    {
        $browserInfo     = new Browser();
        $osInfo          = new Os();
        $deviceInfo      = new Device();
        $browserVersion  = trim(str_replace('unknown', '', $browserInfo->getName() . ' ' . $browserInfo->getVersion()));
        $osVersion       = trim(str_replace('unknown', '', $osInfo->getName() . ' ' . $osInfo->getVersion()));
        $hardwareVersion = trim(str_replace('unknown', '', $deviceInfo->getName()));
        $data = array_filter([
            'Url' => $this->request->getUri(),
            'Operating System' => $osVersion,
            'Hardware' => $hardwareVersion,
            '$browser' => $browserVersion,
            'Referrer' => $this->request->header('referer'),
            '$referring_domain' => ($this->request->header('referer')
                ? parse_url($this->request->header('referer'))['host']
                : null),
            'ip' => $this->request->ip(),
        ]);

        if ((! array_key_exists('$browser', $data)) && $browserInfo->isRobot()) {
            $data['$browser'] = 'Robot';
        }

        return $data;
    }

    /**
     * Fetch event properties from this model to be sent along with other properties of every event.
     *
     * Property names are prefixed with class name.
     * Example: 'User' model gets 'name' property transformed to 'User Name'
     * 
     * @param  Object $model Any object implementing 'getEventProperties()'
     * @return array        Array of new props
     */
    public function getModelProperties($model)
    {
        if (! method_exists($model, 'getEventProperties')) {
            return [];
        }

        $data = $model->getEventProperties();
        $modelName = (new \ReflectionClass($model))->getShortName();
        $newProps = [];

        foreach ($data as $prop => $value) {
            $newPropName = "$modelName " . ucwords($prop);
            $newProps[$newPropName] = $value;
        }

        return $newProps;
    }

    /**
     * Adds this model's parameters to super properties
     * 
     * @param Object $models An object implementing 'getEventProperties()' method
     */
    public function addContext(...$models)
    {
        foreach ($models as $model) {
            $this->addSuperProps($this->getModelProperties($model));
        }

        return $this;
    }

    /**
     * Adds properties to super props, to be sent with all events
     * 
     * @param array $props 
     */
    public function addSuperProps(array $props)
    {
        $this->superProperties = array_merge($this->superProperties, $props);
        
        return $this;
    }

    /**
     * Returns currently set super properties
     * 
     * @return array 
     */
    public function getSuperProps()
    {
        return array_merge($this->getSessionProps(), $this->superProperties);
    }

    /**
     * Identify this user either as a logged in, or anonymous user.
     * 
     * @return void 
     */
	public function identity()
	{
		$user = Auth::user();
        if ($user) {
            $this->identify($user->id);
	        $this->people()->set($user->id, [], request()->ip());
        } else {
        	$this->identify($this->createDistinctId());
	        $this->people()->set($this->distinctId(), [], request()->ip());
        }
	}

    public function trackRoute($request)
    {
        $route = '';
        $this->request = $request;

        $currentRoute = $request->route()->uri();
        $exceptions = config('services.mixpanel.auto_track.ignore');

        if (config('services.mixpanel.auto_track.only_get') && ! in_array('GET', $request->route()->getMethods())) {
            \Log::info("Not tracking this route because it's not GET: " . $currentRoute);
            return;
        }

        if ($exceptions && is_array($exceptions) && in_array($currentRoute, $exceptions)) {
            \Log::info("Not tracking this route because it matched exceptions setting: " . $currentRoute);
            return;
        }

        $this->trackPageView();
    }

	/**
	 * Alias the user {userId} with the anonymous user from the cookie
	 * 
	 * @param  integer $userId User id from the database
	 * @return void         
	 */
    public function alias($userId)
    {
    	$cookieId = $this->createDistinctId();
    	$this->createAlias($cookieId, $userId);
    }

    /**
     * Get the distinct id from the cookie
     * 
     * @return integer
     */
    public function distinctId()
    {
        if ($this->distinctId) {
            return $this->distinctId;
        }

        if (Cookie::has($this->cookieName)) {
            $this->distinctId = Cookie::get($this->cookieName);
        }

    	return $this->distinctId;
    }

    /**
     * Return or create a new random distinct id for this user
     * 
     * @return integer New id value
     */
    public function createDistinctId()
    {
        $did = $this->distinctId();

    	if (! $this->distinctId = $this->distinctId()) {
    		$this->distinctId = random_int(1000000000, 9999999999);
    		Cookie::queue(Cookie::forever($this->cookieName, $this->distinctId));
    	}

    	return $this->distinctId;
    }
}
