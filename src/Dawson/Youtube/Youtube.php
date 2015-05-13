<?php namespace Dawson\Youtube;

use DB;
use Storage;
use Carbon\Carbon;

class Youtube {

	protected $client;
	protected $youtube;

	/**
	 * Constructor accepts the Google Client object, whilst setting the configuration options.
	 * @param \Google_Client $client
	 */
	public function __construct($development = false)
	{
		$this->client = new \Google_Client;
		$this->client->setApplicationName(config('youtube.application_name'));
		$this->client->setClientId(config('youtube.client_id'));
		$this->client->setClientSecret(config('youtube.client_secret'));
		$this->client->setScopes(config('youtube.scopes'));
		$this->client->setClassConfig('Google_Http_Request', 'disable_gzip', true);

		$redirect_uri = config('youtube.route_base_uri') ?
			config('youtube.route_base_uri') . '/' . config('youtube.redirect_uri') : 
			config('youtube.redirect_uri');
		$this->client->setRedirectUri(url($redirect_uri));

		$this->youtube = new \Google_Service_YouTube($this->client);

		$accessToken = $this->getLatestAccessTokenFromDB();

		if ($accessToken) {
			$this->client->setAccessToken($accessToken);
		}
	}

	/**
	 * Saves the access token to the database.
	 * @param $accessToken
	 */
	public function saveAccessTokenToDB($accessToken)
	{
		$data = [
			'access_token' => $accessToken,
			'created_at' => Carbon::now(),
		];

		DB::table('youtube_access_tokens')->insert($data);
	}
	/**
	 * Returns the last saved access token, if there is one, or null
	 * @return mixed
	 */
	public function getLatestAccessTokenFromDB()
	{
		$latest = DB::table('youtube_access_tokens')
				->orderBy('created_at', 'desc')
				->first();

		if ($latest) { return $latest->access_token; }

		return null;
	}

	/**
	 * Upload the video to YouTube
	 * @param  string 	$path    	The path to the file you wish to upload.
	 * @param  array 	$snippet 	An array of data.
	 * @param  string 	$status  	The status of the uploaded video, set to 'public' by default.
	 * @return mixed
	 */
	public function upload($path, array $data, $privacyStatus = 'public')
	{
		/* ------------------------------------
		#. Get Access Token
		------------------------------------ */
		$accessToken = $this->client->getAccessToken();

		/* ------------------------------------
		#. Authenticate if no Access Token
		------------------------------------ */
		if(is_null($accessToken))
		{
			throw new \Exception('An access token is required to attempt an upload.');
		}

		/* ------------------------------------
		#. Refresh Access token if needed
		------------------------------------ */
		if($this->client->isAccessTokenExpired())
		{
			$accessToken = json_decode($accessToken);
			$refreshToken = $accessToken->refresh_token;
			$this->client->refreshToken($refreshToken);
			$newAccessToken = $this->client->getAccessToken();
			$this->saveAccessTokenToDB($newAccessToken);
		}

		/* ------------------------------------
		#. Setup the Snippet
		------------------------------------ */
		$snippet = new \Google_Service_YouTube_VideoSnippet();
		$snippet->setTitle($data['title']);
		$snippet->setDescription($data['description']);

		/* ------------------------------------
		#. Set the Privacy Status
		------------------------------------ */
		$status = new \Google_Service_YouTube_VideoStatus();
		$status->privacyStatus = $privacyStatus;

		/* ------------------------------------
		#. Set the Snippet & Status
		------------------------------------ */
		$video = new \Google_Service_YouTube_Video();
		$video->setSnippet($snippet);
		$video->setStatus($status);

		/* ------------------------------------
		#. Set the Chunk Size
		------------------------------------ */
		$chunkSize = 1 * 1024 * 1024;

		/* ------------------------------------
		#. Set the defer to true
		------------------------------------ */
		$this->client->setDefer(true);

		/* ------------------------------------
		#. Build the request
		------------------------------------ */
		$insert = $this->youtube->videos->insert('status,snippet', $video);

		/* ------------------------------------
		#. Upload
		------------------------------------ */
		$media = new \Google_Http_MediaFileUpload(
			$this->client,
			$insert,
			'video/*',
			null,
			true,
			$chunkSize
		);

		/* ------------------------------------
		#. Set the Filesize
		------------------------------------ */
		$media->setFileSize(Storage::size($path));

		/* ------------------------------------
		#. Read the file and upload in chunks
		------------------------------------ */
		$status = false;
		$handle = fopen(storage_path('app/' . $path), "rb");

		while (!$status && !feof($handle)) {
			$chunk = fread($handle, $chunkSize);
			$status = $media->nextChunk($chunk);
		}

		fclose($handle);

		/* ------------------------------------
		#. Set the defer to false again
		------------------------------------ */
		$this->client->setDefer(true);

		/* ------------------------------------
		#. Return the Uploaded Video ID
		------------------------------------ */
		return $status['id'];
	}

	/**
	 * Delete a YouTube video by it's ID.
	 * @param  integer $youtube_id
	 * @return Mixed
	 */
	public function delete($youtube_id)
	{
		/* ------------------------------------
		#. Get Access Token
		------------------------------------ */
		$accessToken = $this->client->getAccessToken();

		/* ------------------------------------
		#. Authenticate if no Access Token
		------------------------------------ */
		if(is_null($accessToken))
		{
			throw new \Exception('An access token is required to delete a video.');
		}

		/* ------------------------------------
		#. Refresh Access token if needed
		------------------------------------ */
		if($this->client->isAccessTokenExpired())
		{
			$accessToken = json_decode($accessToken);
			$refreshToken = $accessToken->refresh_token;
			$this->client->refreshToken($refreshToken);
			$newAccessToken = $this->client->getAccessToken();
			$this->saveAccessTokenToDB($newAccessToken);
		}

		$result = $this->youtube->videos->delete($youtube_id);

		if (!$result)
		{
			throw new \Exception("Unable to delete video matching id " . $youtube_id);
		}

		return true;
	}
	
	/**
	 * Pass method calls to the Google Client.
	 * @param  $method
	 * @param  $args
	 * @return mixed
	 */
	public function __call($method, $args)
	{
		return call_user_func_array([$this->client, $method], $args);
	}

}