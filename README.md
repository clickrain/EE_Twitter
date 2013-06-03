#ExpressionEngine Twitter

ExpressionEngine Twitter is nearly an identical port of EllisLabs Twitter Timeline plugin, via Bryant Hughes's [TGL_Twitter](https://github.com/bryantAXS/TGL_Twitter). However ExpressionEngine Twitter has a CP Backend, allowing a user to authenticate with Twitter using oAuth.

ExpressionEngine Twitter uses the Twitter 1.1 API, and provides tags that make it possible to conform to Twitter's [Display Requirements](https://dev.twitter.com/terms/display-requirements).

##Requirements

* EE 2.0
* Twitter Account
* PHP 5 >= 5.3

##Installation
1. Add-ons -> Modules -> Twitter -> Install
2. Login into http://dev.twitter.com Note: This does not have to be the account you are going to authorize with, or the account you are going to be displaying tweets for.  It is just the account that "owns" the application (your site).
3. Click "Create a new application" button.
4. Fillout the <i>Create an Application</i> form.  You do not need to enter a Callback URL.
5. Copy the Consumer Key and Consumer Secret, under the oAuth settings, and paste them into the Form in the Module CP.  Click "Update".
6. Click "Generate new Request Token".
7. Follow on-page instructions to get PIN number, and update form with PIN.

##Template Tags

### User

    {exp:twitter:user screen_name="biz" limit="5"}

    {/exp:twitter:user}

####Parameters

`screen_name`

The screen name of the person whose tweets you want to retrieve. This should not include the leading @ sign. Required.

`limit`

The maximum number of tweets to display. Defaults to whatever 20.

`count`

The number of tweets to retrieve. Defaults to 15

`twitter_refresh`

The time, in minutes, between calls to Twitter. Between these times, the tag will use the cached value. Defaults to 45.

`retweets`

Include retweets in retrieved tweets. Options `yes`, `no`. Defaults to `yes`

`replies`

Include @ replies in retrieved tweets. Options `yes`, `no`. Defaults to `yes`

`target`

Set the target for all links inside the tweets.

`prefix`

Set a prefix to use for the tags. This turns `{id}` into `{yourprefix:id}`. Defaults to no prefix.

`userprefix`

Set a prefix to use for the user tags (`{name}`, `{screen_name}`, `{location}`, `{description}`, `{profile_image_url}`). Defaults to what `prefix` is set to.

`images_only`

Only return tweets that contain images. Options `yes`, `no`. Defaults to `no`

####Single Variable Tags

**Dates**: `{relative_date}`, `{iso_date}`, `{created_at}`

`{relative_date}` returns a string that conforms to Twitter Display Requirements. This will a relative date when the tweet occurred less than a day ago (eg "13h" or "29m"), or the date when the tweet occurred more than a day ago (eg "21 Apr 12").

Beware: `{exp:channel:entries}` also has a `{relative_date}`, so if you get a long relative string, it's likely it's being pulled from the channel entries loop. If this is the case, use the `prefix=` parameter to prefix the tags.

Note: `{relative_date}` requires PHP 5.3 or above. On PHP 5.2 and before, you will always get a full date string, like '4 Apr 85'.

`{iso_date}` returns the date in ISO8601 format. `{created_at}` returns a date that can be formatted with ExpressionEngine's standard `format=` parameter for dates.

**URLs**: `{permalink}`, `{reply_intent}`, `{retweet_intent}`, `{favorite_intent}`

`{permalink}` returns the permanent Twitter URL. `{reply_intent}` includes a URL formatted for the Reply Intent. `{retweet_intent}` includes a URL formatted for the Retweet Intent. `{favorite_intent}` includes a URL formatted for the Reply Intent. See Twitter's [Display Requirements](https://dev.twitter.com/terms/display-requirements) and [Web Intents](https://dev.twitter.com/docs/intents) documentation.

`{id}`

The tweet's ID.

`{text}`

The text of the tweet, including HTML markup for mentions, links, and hashtags.

`{name}`

The name of the person who created the tweet.

`{screen_name}`

The screen name of the person who created the tweet. This does not include the leading "@".

`{location}`

The location of the person who created the tweet, according to their profile.

`{description}`

The description of the person who created the tweet, according to their profile.

`{profile_image_url}`

The profile image of the person who created the tweet, using the HTTP protocol.

`{profile_image_url_https}`, `{image}`

The profile image of the person who created the tweet, using the HTTPS protocol.

**Retweets**: `{if retweeted}`, `{retweeter}`

If the tweet is a retweet, the _original_ tweet will be shown. Use `{if retweet}` to determine if the tweet being shown is a retweet. `{retweeter}` will then include the name of the person that retweeted the tweet.

Also, `{retweeter:*}` exists for all user fields for the retweeter, so for example `{retweeter:profile_image_url_https}` will be the profile image of the retweeter.

### Variable Pairs

**Images**: `{images}` Contains all images from the tweet.

`{image}`

The url to the image. This is essentially the same as `{medium}`, though the URL is slightly different.

`{[size]}`

The url to the image at `[size]`. Twitter crops images at four different sizes: _large_, _medium_, _small_, and _thumb_. See [Tweet Entities | Twitter Developers](https://dev.twitter.com/docs/tweet-entities) for more information. Example: `{large}`.

`{[size]_https}`

The url to the image at `[size]`, using HTTPS. Example: `{large_https}`.

`{[size]_w}`

The width of the image at `[size]`. Example: `{large_w}`.

`{[size]_h}`

The height of the image at `[size]`. Example: `{large_h}`.

`{[size]_resize}`

How Twitter resized the media to the particular `[size]`. Value can be either "crop" or "fit". Example: `{large_resize}`.

### Search

    {exp:twitter:search query="#twitter" limit="5"}

    {/exp:twitter:search}

#### Parameters

`query`

The query to use to search Twitter. Required.

All other parameters are the same as `{exp:twitter:user}`

#### Single Variable Tags

All variables are the same as `{exp:twitter:user}`

### Script

    {exp:twitter:script}

Outputs the standard Twitter platform script:

    <script type="text/javascript" src="//platform.twitter.com/widgets.js"></script>

## Known Issues

Please take note that on some API calls, such as user timeline,  if the API does not detect a valid oAuth token it will just default to the unauthenticated API conditions.  An example of this is if you install the module, then add in your template tags without authenticating first, data will still be shown.  However, this data will be rate limited to 150 and is returned based on unauthenticated API conditions.

