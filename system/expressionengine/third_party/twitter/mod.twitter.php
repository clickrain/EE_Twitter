<?php if( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD.'twitter/classes/twitteroauth.php';

/**
 * Twitter
 *
 * This is an improvement on TGL_Twitter (https://github.com/bryantAXS/TGL_Twitter) which was an improvement on Ellislabs Twitter Timeline
 * (http://expressionengine.com/downloads/details/twitter_timeline/). Twitter supports Twitter's API version 1.1 using oAuth.
 *
 * @package default
 * @author Derek Jones
 * @author Bryant Hughes
 * @author Bryan Burgers
 */

class Twitter
{

	var $return_data	= '';
	var $cache_name		= 'twitter';
	var $cache_path;
	var $cache_expired	= FALSE;
	var $rate_limit_hit = FALSE;
	var $refresh		= 45;		// Period between cache refreshes, in minutes (purposely high default to prevent hitting twitter's rate limit on shared IPs - be careful)
	var $limit			= 20;
	var $parameters		= array();
	var $months			= array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	var $use_stale;


	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function Twitter() {
		// Adding an old-style constructor allows use on older installs of EE2.
		Twitter::__construct();
	}

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function __construct()
	{
		$this->EE =& get_instance();

		if ($this->EE->config->item('twitter_cache_path')) {
			$this->cache_path = $this->EE->config->item('twitter_cache_path');
			if (substr_compare($this->cache_path,  '/', -1, 1) !== 0) {
				$this->cache_path = $this->cache_path . '/';
			}
		}
		else {
			$this->cache_path = APPPATH . 'cache/' . $this->cache_name . '/';
		}
	}

	public function user()
	{
		// Fetch parameters
		$this->refresh		= $this->EE->TMPL->fetch_param('twitter_refresh', $this->refresh);
		$this->limit		= $this->EE->TMPL->fetch_param('limit', $this->limit);
		$count              = $this->EE->TMPL->fetch_param('count');
		$this->use_stale	= $this->EE->TMPL->fetch_param('use_stale_cache', 'yes');
		$this->target		= $this->EE->TMPL->fetch_param('target', '');
		$screen_name		= $this->EE->TMPL->fetch_param('screen_name');
		$prefix				= $this->EE->TMPL->fetch_param('prefix', '');
		$userprefix			= $this->EE->TMPL->fetch_param('userprefix', NULL);
		$include_rts		= ($this->EE->TMPL->fetch_param('retweets', 'yes') == 'yes') ? TRUE : FALSE;
		$exclude_replies	= ($this->EE->TMPL->fetch_param('replies', 'yes') == 'yes') ? FALSE : TRUE;
		$images_only     = ($this->EE->TMPL->fetch_param('images_only', "no") == 'yes') ? TRUE : FALSE;

		if (!$screen_name)
		{
			$this->EE->TMPL->log_item("Parameter screen_name was not provided");
			return;
		}

		// timeline type
		$timeline	= 'user';
		$log_extra	= "For User {$screen_name}";

		$this->EE->TMPL->log_item("Using '{$timeline}' Twitter Timeline {$log_extra}");

		// retrieve statuses
		$url = 'statuses/user_timeline';
		$params = array('screen_name' => $screen_name, 'include_rts' => $include_rts, 'exclude_replies' => $exclude_replies,  'count' => $count);

		$statuses = $this->_fetch_data($url, $params);

		if ( ! $statuses)
		{
			return;
		}

		$return_data = $this->render_tweets($statuses, $prefix, $userprefix, $images_only);
		return $return_data;
	}

	public function search()
	{
		// Fetch parameters
		$this->refresh		= $this->EE->TMPL->fetch_param('twitter_refresh', $this->refresh);
		$this->limit		= $this->EE->TMPL->fetch_param('limit', $this->limit);
		$this->use_stale	= $this->EE->TMPL->fetch_param('use_stale_cache', 'yes');
		$this->target		= $this->EE->TMPL->fetch_param('target', '');
		$query = $this->EE->TMPL->fetch_param('query');
		$prefix = $this->EE->TMPL->fetch_param('prefix', '');
		$userprefix = $this->EE->TMPL->fetch_param('userprefix', NULL);

		if (!$query)
		{
			$this->EE->TMPL->log_item("Parameter query was not provided");
			return;
		}

		// timeline type
		$timeline	= 'search';
		$log_extra	= "For search {$query}";

		$this->EE->TMPL->log_item("Using '{$timeline}' Twitter Timeline {$log_extra}");

		$url = "search/tweets";
		$params = array('q' => $query, 'include_rts' =>'true');

		// retrieve statuses
		$data = $this->_fetch_data($url, $params);
		$statuses = $data['statuses'];

		return $this->render_tweets($statuses, $prefix, $userprefix);
	}

	public function script() {
		return "<script type=\"text/javascript\" src=\"//platform.twitter.com/widgets.js\"></script>";
	}

	private function render_tweets($statuses, $prefix, $userprefix, $images_only = FALSE) {


		if ( ! $statuses)
		{
			return;
		}

		if ($prefix != '') {
			$prefix = $prefix . ':';
		}
		if (is_null($userprefix)) {
			$userprefix = $prefix;
		}
		else if ($userprefix != '') {
			$userprefix = $userprefix . ':';
		}

		$count = 0;

		$loopvars = array();

		// Loop through all statuses and do our template replacements
		foreach ($statuses as $key => $val)
		{
			//Check if tweet contains an image
			//If no image is present and user has requested images only, skip this tweet
			if (! isset($val['entities']['media']) && $images_only == TRUE)
			{
				continue;
			}

			$variables = array();

			$count++;

			if ($count > $this->limit)
			{
				break;
			}

			// If this is a retweet, let's use that data instead
			$retweeted = FALSE;
			if (isset($val['retweeted_status'])) {
				$retweeted = TRUE;
				$retweeter = $val['user'];
				$val = $val['retweeted_status'];
				$val['retweeter'] = $retweeter;
			}

			$tagdata = $this->EE->TMPL->tagdata;

			$images = array();

			// Link up anything that needs to be linked up
			if (isset($val['entities']) && is_array($val['entities']))
			{
				$find = array();
				$replace = array();

				$target = '';
				if (isset($this->target) && $this->target !== '') {
					$target = " target='".$this->target."'";
				}

				foreach ($val['entities'] as $type => $found)
				{
					foreach ($found as $info)
					{
						switch($type)
						{
							case 'user_mentions':
								$find[] = '@'.$info['screen_name'];
								$replace[] = "<a{$target} title='{$info['name']}' href='http://twitter.com/{$info['screen_name']}'>@{$info['screen_name']}</a>";
								break;
							case 'hashtags':
								$find[] = '#'.$info['text'];
								// Because EE's xss_clean replaces %23 with #, we need to use %2523; EE changes %25 into %, so we get %23.
								$replace[] = "<a{$target} title='Search for {$info['text']}' href='http://twitter.com/search?q=%2523{$info['text']}'>#{$info['text']}</a>";
								break;
							case 'urls':
								$find[] = $info['url'];
								$displayurl = $info['url'];
								if (isset($info['display_url'])) {
									$displayurl = $info['display_url'];
								}
								$replace[] = "<a{$target} title='{$info['expanded_url']}' href='{$info['url']}'>{$displayurl}</a>";
								break;
							case 'media':
								$find[] = $info['url'];
								$displayurl = $info['url'];
								if (isset($info['display_url'])) { $displayurl = $info['display_url']; }
								if($images_only == FALSE)
								{
									$replace[]  = "<a{$target} title='{$info['expanded_url']}' href='{$info['url']}'>{$displayurl}</a>";
								}
								else {
									// If we only want images, drop the <a>
									// tag that represents the image, because
									// almost certainly the person using
									// images_only="yes" will display the
									// image directly.
									$replace[]  = '';
								}
								if(isset($info['type']) && $info['type'] == 'photo')
								{
									$image = array(
										'image' => $info['media_url']
										);
									foreach ($info['sizes'] as $size => $sizeval) {
										$image = array_merge($image,
											array(
												'image' => $info['media_url'],
												'display_url' => $info['expanded_url'],
												$size => $info['media_url'] . ':' . $size,
												$size . '_https' => $info['media_url_https'] . ':' . $size,
												$size . '_w' => $sizeval['w'],
												$size . '_h' => $sizeval['h'],
												$size . '_resize' => $sizeval['resize']
												)
											);
									}
									$images[] = $image;
								}
								break;
						}
					}
				}

				$val['text'] = str_ireplace($find, $replace, $val['text']);

				unset($find, $replace);
			}

			$val['id'] = $val['id_str'];

			// Add count

			$val['count'] = $count;


			// Clean the tweet

			$val['text'] = $this->EE->security->xss_clean($val['text']);
			$val['text'] = $this->EE->functions->encode_ee_tags($val['text'], TRUE);

			// Prep conditionals

			$cond	 = $val;
			$tagdata = $this->EE->functions->prep_conditionals($tagdata, $cond['user']);

			unset($cond['user']);
			$cond['retweeted'] = $retweeted;
			$tagdata = $this->EE->functions->prep_conditionals($tagdata, $cond);


			$variables[$prefix . 'permalink'] = $this->_build_permalink($val);
			$variables[$prefix . 'reply_intent'] = $this->_build_reply_intent($val);
			$variables[$prefix . 'retweet_intent'] = $this->_build_retweet_intent($val);
			$variables[$prefix . 'favorite_intent'] = $this->_build_favorite_intent($val);
			$variables[$prefix . 'relative_date'] = $this->_build_twitter_relative_date($val);
			$variables[$prefix . 'twitter_relative_date'] = $this->_build_twitter_relative_date($val);
			$variables[$prefix . 'better_relative_date'] = $this->_build_better_relative_date($val);
			$variables[$prefix . 'iso_date'] = $this->_build_iso_date($val);
			$variables[$prefix . 'created_at'] = strtotime($val['created_at']);
			$variables[$prefix . 'id'] = $val['id'];
			$variables[$prefix . 'text'] = $val['text'];
			$variables[$prefix . 'images'] = $images;

			$variables[$userprefix . 'name'] = $val['user']['name'];
			$variables[$userprefix . 'screen_name'] = $val['user']['screen_name'];
			$variables[$userprefix . 'location'] = $val['user']['location'];
			$variables[$userprefix . 'description'] = $val['user']['description'];
			$variables[$userprefix . 'profile_image_url'] = $val['user']['profile_image_url'];
			$variables[$userprefix . 'profile_image_url_https'] = $val['user']['profile_image_url_https'];
			$variables[$userprefix . 'image'] = $val['user']['profile_image_url_https'];

			$variables[$prefix . 'retweeted'] = $retweeted;
			if ($retweeted) {
				$variables[$prefix . 'retweeter'] = $retweeter['name'];
				$variables[$prefix . 'retweeter:name'] = $retweeter['name'];
				$variables[$prefix . 'retweeter:screen_name'] = $retweeter['screen_name'];
				$variables[$prefix . 'retweeter:location'] = $retweeter['location'];
				$variables[$prefix . 'retweeter:description'] = $retweeter['description'];
				$variables[$prefix . 'retweeter:profile_image_url'] = $retweeter['profile_image_url'];
				$variables[$prefix . 'retweeter:profile_image_url_https'] = $retweeter['profile_image_url_https'];
				$variables[$prefix . 'retweeter:image'] = $retweeter['profile_image_url_https'];
			}
			else {
				$variables[$prefix . 'retweeter'] = '';
			}

			$loopvars[] = $variables;
		}

		$output = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $loopvars);
		return $output;
	}

	/**
	 * Create a unique hash for the url and parameters.
	 *
	 * @param string $url The URL that will be passed to OAuth::get
	 * @param array $params The parameters that will be passed to OAuth::get
	 * @return string A unique, predictable hash
	 */
	private function hash_request($url, $params) {
		$res = $url;
		$separator = '?';
		foreach ($params as $key => $val) {
			$res .= $separator . urlencode($key) . '=' . urlencode($val);
			$separator = '&';
		}
		return sha1($res);
	}

	/**
	 * Fetch data
	 *
	 * Grabs and parses the Twitter status messages
	 *
	 * @access	public
	 * @return	array
	 */
	function _fetch_data($url, $params)
	{
		$uniqueid = $this->hash_request($url, $params);
		$rawjson			= '';
		$cached_json		= $this->_check_cache($uniqueid);

		if ($this->cache_expired OR ! $cached_json)
		{
			$this->EE->TMPL->log_item("Fetching Twitter timeline remotely");

			if ( function_exists('curl_init'))
			{
				$rawjson = $this->_curl_fetch($url, $params);
			}
			else {
				// We only support CURL, because that's what oauth uses.
				return;
			}
		}

		// Attempt to parse the data we have
		$json_obj = $this->_check_json($rawjson);

		if ( ! $json_obj)
		{
			// Did we try to grab new data? Tell them that it failed.
			if ( ! $cached_json OR $this->cache_expired)
			{
				$this->EE->TMPL->log_item("Twitter Timeline Error: Unable to retrieve statuses from Twitter.com");

				// Rate limit hit and no cached data?
				// We definitely need to write a cache file so we don't continue
				// to ask Twitter for data on every request.

				if ( ! $cached_json && $this->rate_limit_hit)
				{
					$this->_write_cache($rawjson, $uniqueid);
				}

				// Try to parse cache? Is it worth it?
				if ($this->use_stale != 'yes' OR ! $cached_json)
				{
					return FALSE;
				}

				$this->EE->TMPL->log_item("Twitter Timeline Using Stale Cache: ".$uniqueid);
			}
			else
			{
				$this->EE->TMPL->log_item("Twitter Timeline Retrieved From Cache.");
			}


			// Check the cache
			$json_obj = $this->_check_json($cached_json);


			// If we're hitting twitter's rate limit,
			// refresh the cache timestamp, even if the cache file
			// is the rate limiting message. We need to stop asking for data for a while.

			if ($this->rate_limit_hit && $this->cache_expired)
			{
				$this->_write_cache($cached_json, $uniqueid);
			}

			if ( ! $json_obj)
			{
				$this->EE->TMPL->log_item("Twitter Timeline Error: Invalid Cache File");
				return FALSE;
			}
		}
		else
		{
			// We have (valid) new data - cache it
			$this->_write_cache($rawjson, $uniqueid);
		}

		if ( ! is_array($json_obj) OR count($json_obj) == 0)
		{
			return FALSE;
		}

		return $json_obj;
	}

	// --------------------------------------------------------------------

	/**
	 * Check XML
	 *
	 * Checks the XML for validity and also looks for errors in the data.
	 *
	 * @access	public
	 * @param	object
	 * @return	array
	 */
	function _check_json($rawjson)
	{
		if ($rawjson == '')
		{
			return FALSE;
		}

		$json_obj = json_decode($rawjson, TRUE);

		if ($json_obj === NULL)
		{
			return FALSE;
		}

		// Check for error response
		if (isset($json_obj['errors']))
		{
			$error = $json_obj['errors'][0];
		}

		if (isset($error))
		{
			if ($error['code'] === 88) {
				$this->rate_limit_hit = TRUE;
			}
			$this->EE->TMPL->log_item("Twitter Timeline error: " . $error['message']);
			return FALSE;
		}

		return $json_obj;
	}

	// --------------------------------------------------------------------

	/**
	 * Check Cache
	 *
	 * Check for cached data
	 *
	 * @access	public
	 * @param	string
	 * @param	bool	Allow pulling of stale cache file
	 * @return	mixed - string if pulling from cache, FALSE if not
	 */
	function _check_cache($hash)
	{
		if (version_compare(APP_VER, '2.8', '>=')) {
			$cache = $this->EE->cache->get("/twitter/{$hash}/content");

			if (!$cache) {
				return FALSE;
			}

			$timestamp = $this->EE->cache->get("/twitter/{$hash}/timestamp");

			if (time() > ($timestamp + ($this->refresh * 60))) {
				$this->cache_expired = TRUE;
			}

			return $cache;
		}
		else {
			// Check for cache directory

			$dir = $this->cache_path;

			if ( ! @is_dir($dir))
			{
				return FALSE;
			}

			// Check for cache file

	        $file = $dir . $hash;

			if ( ! file_exists($file) OR ! ($fp = @fopen($file, 'rb')))
			{
				return FALSE;
			}

			flock($fp, LOCK_SH);

			$cache = @fread($fp, filesize($file));

			flock($fp, LOCK_UN);

			fclose($fp);

			// Get when the cache file was last modified
			$timestamp = filemtime($file);

			if ( time() > ($timestamp + ($this->refresh * 60)) )
			{
				$this->cache_expired = TRUE;
			}

	        return $cache;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Write Cache
	 *
	 * Write the cached data
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function _write_cache($data, $hash)
	{
		if (version_compare(APP_VER, '2.8', '>=')) {
			$this->EE->cache->save("/twitter/{$hash}/content", $data);
			$this->EE->cache->save("/twitter/{$hash}/timestamp", time());
		}
		else {
			// Check for cache directory

			$dir = $this->cache_path;

			if ( ! @is_dir($dir))
			{
				if ( ! @mkdir($dir, 0777))
				{
					return FALSE;
				}

				@chmod($dir, 0777);
			}

			// Write the cached data
			$file = $dir . $hash;

			if ( ! $fp = @fopen($file, 'wb'))
			{
				return FALSE;
			}

			flock($fp, LOCK_EX);
			fwrite($fp, $data);
			flock($fp, LOCK_UN);
			fclose($fp);

			@chmod($file, 0777);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * curl Fetch
	 *
	 * Fetch Twitter statuses using cURL
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function _curl_fetch($url, $params)
	{
		$data = '';

		//this is where we have modified the plugin to fetch our data via oauth

		$this->EE->load->model('twitter_model');
		$settings = $this->EE->twitter_model->get_settings();

		// Read in our saved access token/secret
		$access_token = $settings['access_token'];
		$access_token_secret = $settings['access_token_secret'];

		// Create our twitter API object
		$oauth = new TwitterEETwitter_OAuth($settings['consumer_key'], $settings['consumer_secret'], $access_token, $access_token_secret);
		$oauth->decode_json = FALSE;

		$data = $oauth->get($url, $params);

		return $data;
	}

	// --------------------------------------------------------------------

	/**
	 * Parse Twitter Date
	 *
	 * Reformats Twitter's dates to a standard human time notation
	 * Twitter's dates are in the format: Fri Apr 13 15:34:45 +0000 2007
	 * Returns in YYYY-MM-DD HH:MM:SS
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function _parse_twitter_date($str)
	{
		$parts = explode(' ', $str);
		$month = array_keys($this->months, $parts[1]);
		$mm = sprintf("%02s", $month[0] + 1);

		return "{$parts[5]}-{$mm}-{$parts[2]} {$parts[3]}";

	}

	function _build_permalink($status) {
		return 'https://twitter.com/' . $status['user']['screen_name'] . '/statuses/' . $status['id_str'];
	}

	function _build_reply_intent($status) {
		return 'https://twitter.com/intent/tweet?in_reply_to=' . $status['id_str'];
	}

	function _build_retweet_intent($status) {
		return 'https://twitter.com/intent/retweet?tweet_id=' . $status['id_str'];
	}

	function _build_favorite_intent($status) {
		return 'https://twitter.com/intent/favorite?tweet_id=' . $status['id_str'];
	}

	function _build_twitter_relative_date($status, $now = NULL) {
		try {
			$dt = new DateTime($status['created_at']);
			if (is_null($now)) {
				$now = new DateTime();
			}

			if (!method_exists($dt, 'diff')) {
				throw new Exception("diff method doesn't exist");
			}
			$diff = $dt->diff($now);

			$output = '';

			if ($diff->d < 1) {
				if ($diff->h > 0) {
					$output = $diff->h . "h";
				}
				else if ($diff->i > 0) {
					$output = $diff->i . "m";
				}
				else {
					$output = $diff->s . "s";
				}
			}
			else {
				if ($dt->format('Y') == $now->format('Y')) {
					$output = $dt->format("j M");
				}
				else {
					$output = $dt->format("j M y");
				}
			}

			return $output;
		}
		catch (Exception $e) {
			// If diff method doesn't exist, let's just give back the date.
			// Maybe somebody who uses an old version of PHP can build out the relative
			// date using something other than DateTime::diff
			$dt = new DateTime($status['created_at']);
			return $dt->format("j M y");
		}
	}

	function _build_better_relative_date($status, $now = NULL) {
		$dt = new DateTime($status['created_at']);
		if (is_null($now)) {
			$now = new DateTime();
		}

		$_second = 1;
		$_minute = 60 * $_second;
		$_hour   = 60 * $_minute;
		$_day    = 24 * $_hour;
		$_month  = 30 * $_day;

		$delta = abs($dt->format('U') - $now->format('U'));

		if ($delta < 1 * $_minute)
		{
			return $delta == 1 ? "one second ago" : $delta . " seconds ago";
		}
		if ($delta < 2 * $_minute)
		{
			return "a minute ago";
		}
		if ($delta < 45 * $_minute)
		{
			return floor($delta / $_minute) . " minutes ago";
		}
		if ($delta < 90 * $_minute)
		{
			return "an hour ago";
		}
		if ($delta < 24 * $_hour)
		{
			return floor($delta / $_hour) . " hours ago";
		}
		if ($delta < 48 * $_hour)
		{
			return "yesterday";
		}
		if ($delta < 30 * $_day)
		{
			return floor($delta / $_day) . " days ago";
		}
		if ($delta < 12 * $_month)
		{
			$months = floor($delta / $_day / 30);
			return $months <= 1 ? "one month ago" : $months . " months ago";
		}
		else
		{
			$years = floor($delta / $_day / 365);
			return $years <= 1 ? "one year ago" : $years . " years ago";
		}

		return $status['created_at'];
	}

	function _build_iso_date($status) {
		$dt = new DateTime($status['created_at']);
		return $dt->format(DateTime::ISO8601);
	}

}

/* End of File: mod.module.php */
