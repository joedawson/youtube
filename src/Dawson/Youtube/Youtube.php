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
		$this->client->setAccessType(config('youtube.access_type'));
		$this->client->setApprovalPrompt(config('youtube.approval_prompt'));
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
		$this->handleAccessToken();

		/* ------------------------------------
		#. Setup the Snippet
		------------------------------------ */
		$snippet = new \Google_Service_YouTube_VideoSnippet();
		if (array_key_exists('title', $data))
		{
			$snippet->setTitle($data['title']);
		}
		if (array_key_exists('description', $data))
		{
			$snippet->setDescription($data['description']);
		}
		if (array_key_exists('tags', $data))
		{
			$snippet->setTags($data['tags']);
		}
		if (array_key_exists('category_id', $data))
		{
			$snippet->setCategoryId($data['category_id']);
		}


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
		$media->setFileSize(filesize($path));

		/* ------------------------------------
		#. Read the file and upload in chunks
		------------------------------------ */
		$status = false;
		$handle = fopen($path, "rb");

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
	 * 
	 * @param  integer $youtube_id
	 * @return Mixed
	 */
	public function delete($id)
	{
		$this->handleAccessToken();

		if($this->exists($id))
		{
			return $this->youtube->videos->delete($id);
		}

		return true;
	}

	/**
	 * Check if a YouTube video exists by it's ID.
	 * @param  integer $id
	 * @return boolean
	 */
	public function exists($id)
	{
		$this->handleAccessToken();

		$response = $this->youtube->videos->listVideos('status', ['id' => $id]);

		if(empty($response->items))
		{
			return false;
		}

		return true;
	}

	/**
	 * Handle the Access token
	 * 
	 * @return mixed
	 */
	private function handleAccessToken()
	{
		$accessToken = $this->client->getAccessToken();

		if(is_null($accessToken))
		{
			throw new \Exception('An access token is required to delete a video.');
		}

		if($this->client->isAccessTokenExpired())
		{
			$accessToken = json_decode($accessToken);
			$refreshToken = $accessToken->refresh_token;
			$this->client->refreshToken($refreshToken);
			$newAccessToken = $this->client->getAccessToken();
			$this->saveAccessTokenToDB($newAccessToken);
		}
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