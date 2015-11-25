# Laravel 5 - YouTube Uploader

If you've ever needed to upload videos to a single YouTube channel from your Laravel 5 application, then hopefully this is the package for you.

## Installation

Add the following to your `composer.json`.

```json
"dawson/youtube": "dev-master"
```

After you've added the above, run `composer update` to pull it in. Once your update has finished, we'll need to add the service provider to your `config/app.php`

```php
'providers' => [
	'Dawson\Youtube\YoutubeServiceProvider',
],
```

Then add the alias...

```php
'aliases' => [
	'Youtube' => 'Dawson\Youtube\YoutubeFacade',
],
```

## Configuration

Run `php artisan vendor:publish` to publish the migrations and config. Then migrate the database with, `php artisan migrate`.

This will create our `youtube_access_tokens` table which will of course, hold our access tokens once we've authenticated with Google.

Next it's time to configure our settings in `config/youtube.php` makes use of environment variables to ensure no secret crentials make way into version control. So add the following variables to your `.env` file.

```
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID
GOOGLE_CLIENT_SECRET=YOUR_SECRET
```

You can find these values on Google's [developer console](https://console.developers.google.com/project) for your application. 

Now set up your applications callback, you can find this on your `config/youtube.php`. By default it's set to `http://yourapp.co.uk/youtube-callback`

```php
'redirect_uri' => 'youtube-callback'
```

*If you're unsure of how to use enviroment variables, Jeffrey Way helps clear the fog over at Laracasts with his [Environments and Configuration](https://laracasts.com/series/laravel-5-fundamentals/episodes/6) lesson with Laravel 5.*

## Authentication

Now our application is configured, we'll go through the inital authentication with Google. By default, the authorization route is `/youtube-auth` but you can change this in `config/youtube.php` should you wish to.

Proceed with hitting the auth route in your application of which you will be sent to Google to authorize your YouTube account/channel. Once authorized, you will be redirected back to your application assuming you correctly configured your callback.

# Upload a Video

Once you have complete the above, your Laravel application will now be authorized to make requests to YouTube. Specifically in this case, uploading a video by passing the **full path to the file you wish to upload.**.

To upload a video, do the following:

```php
$id = Youtube::upload($pathToMovieFile);

return $id;
```

The above will return the ID of the uploaded video to YouTube.

You also have the option to pass a second parameter as an array with the following available keys.

- title `string`
- description `string`
- category_id `integer`
- tags `array`

```php
$params = [
	'title'	=> 'Laravel Screencast',
	'description' => 'My First Laravel Tutorial!',
	'category_id' => 10,
	'tags' => [
		'laravel',
		'eloquent',
		'awesome' // Of course!
	]
];

$id = Youtube::upload($pathToMovieFile, $params);

return $id;
```

It's that simple!

# Deleting a Video

If you would like to delete a video, which of course is uploaded to your authorized channel, you will also have the ability to delete it:

```php
Youtube::delete($id);
```

When deleting a video, it will check if exists before attempting to delete.

# Questions

Should you have any questions, please feel free to submit an issue.
