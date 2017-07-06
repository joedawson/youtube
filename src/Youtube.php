<?php

namespace Dawson\Youtube;

use Exception;
use Carbon\Carbon;
use Google_Client;
use Google_Service_YouTube;
use Illuminate\Support\Facades\DB;

class Youtube
{
    /**
     * Application Container
     * 
     * @var Application
     */
    private $app;

    /**
     * Google Client
     * 
     * @var \Google_Client
     */
    protected $client;

    /**
     * Google YouTube Service
     * 
     * @var \Google_Service_YouTube
     */
    protected $youtube;

    /**
     * Video ID
     * 
     * @var string
     */
    private $videoId;

    /**
     * Thumbnail URL
     * 
     * @var string
     */
    private $thumbnailUrl;

    /**
     * Constructor
     * 
     * @param \Google_Client $client
     */
    public function __construct($app, Google_Client $client)
    {
        $this->app = $app;

        $this->client = $this->setup($client);

        $this->youtube = new \Google_Service_YouTube($this->client);

        if ($accessToken = $this->getLatestAccessTokenFromDB()) {
            $this->client->setAccessToken($accessToken);
        }
    }

    /**
     * Upload the video to YouTube
     *
     * @param  string   $path           The path to the file you wish to upload.
     * @param  array    $data           An array of data.
     * @param  string   $privacyStatus  The status of the uploaded video, set to 'public' by default.
     *
     * @return self
     */
    
    /**
     * Upload the video to YouTube
     * 
     * @param  string $path
     * @param  array  $data
     * @param  string $privacyStatus
     * @return string
     */
    public function upload($path, array $data, $privacyStatus = 'public')
    {
        /* -------------------------------------
        #. Does the Video exist?
        ------------------------------------- */
        if(!file_exists($path)) {
            throw new Exception('Video file does not exist at path: "'. $path .'". Provide a full path to the file before attempting to upload.');
        }

        /* -------------------------------------
        #. Handle Access Token
        ------------------------------------- */
        $this->handleAccessToken();

        /* ------------------------------------
        #. Setup the Snippet
        ------------------------------------ */
        $snippet = new \Google_Service_YouTube_VideoSnippet();

        if (array_key_exists('title', $data))       $snippet->setTitle($data['title']);
        if (array_key_exists('description', $data)) $snippet->setDescription($data['description']);
        if (array_key_exists('tags', $data))        $snippet->setTags($data['tags']);
        if (array_key_exists('category_id', $data)) $snippet->setCategoryId($data['category_id']);

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

        $this->client->setDefer(false);

        /* ------------------------------------
        #. Set the Uploaded Video ID
        ------------------------------------ */
        $this->videoId = $status['id'];

        return $this;
    }

    /**
     * Set a Custom Thumbnail for the Upload
     *
     * @param  string  $imagePath
     *
     * @return self
     */
    public function withThumbnail($imagePath)
    {
        try {
            $videoId = $this->getVideoId();

            // Specify the size of each chunk of data, in bytes. Set a higher value for
            // reliable connection as fewer chunks lead to faster uploads. Set a lower
            // value for better recovery on less reliable connections.
            $chunkSizeBytes = 1 * 1024 * 1024;

            // Setting the defer flag to true tells the client to return a request which can be called
            // with ->execute(); instead of making the API call immediately.
            $this->client->setDefer(true);

            // Create a request for the API's thumbnails.set method to upload the image and associate
            // it with the appropriate video.
            $setRequest = $this->youtube->thumbnails->set($videoId);

            // Create a MediaFileUpload object for resumable uploads.
            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $setRequest,
                'image/png',
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($imagePath));

            // Read the media file and upload it chunk by chunk.
            $status = false;
            $handle = fopen($imagePath, "rb");
            while (!$status && !feof($handle)) {
                $chunk  = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }
            fclose($handle);

            // If you want to make other calls after the file upload, set setDefer back to false
            $this->client->setDefer(false);
            $this->thumbnailUrl = $status['items'][0]['default']['url'];

        } catch (\Google_Service_Exception $e) {
            die($e->getMessage());
        } catch (\Google_Exception $e) {
            die($e->getMessage());
        }

        return $this;
    }

    /**
     * Delete a YouTube video by it's ID.
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function delete($id)
    {
        $this->handleAccessToken();

        if (!$this->exists($id)) {
            throw new Exception('A video matching id "'. $id .'" could not be found.');
        }

        return $this->youtube->videos->delete($id);
    }

    /**
     * Check if a YouTube video exists by it's ID.
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function exists($id)
    {
        $this->handleAccessToken();

        $response = $this->youtube->videos->listVideos('status', ['id' => $id]);

        if (empty($response->items)) return false;

        return true;
    }

    /**
     * Return the Video ID
     *
     * @return string
     */
    public function getVideoId()
    {
        return $this->videoId;
    }

    /**
     * Return the URL for the Custom Thumbnail
     *
     * @return string
     */
    public function getThumbnailUrl()
    {
        return $this->thumbnailUrl;
    }

    /**
     * Setup the Google Client
     *
     * @param \Google_Client $client
     * @return \Google_Client $client
     */
    private function setup(Google_Client $client)
    {
        if(
            !$this->app->config->get('youtube.client_id') ||
            !$this->app->config->get('youtube.client_secret')
        ) {
            throw new Exception('A Google "client_id" and "client_secret" must be configured.');
        }

        $client->setApplicationName(config('youtube.application_name'));
        $client->setClientId(config('youtube.client_id'));
        $client->setClientSecret(config('youtube.client_secret'));
        $client->setScopes(config('youtube.scopes'));
        $client->setAccessType(config('youtube.access_type'));
        $client->setApprovalPrompt(config('youtube.approval_prompt'));
        $client->setClassConfig('Google_Http_Request', 'disable_gzip', true);
        $client->setRedirectUri(url(
            config('youtube.routes.prefix') . '/' . config('youtube.routes.redirect_uri')
        ));

        return $this->client = $client;
    }

    /**
     * Saves the access token to the database.
     *
     * @param  string  $accessToken
     */
    public function saveAccessTokenToDB($accessToken)
    {
        return DB::table('youtube_access_tokens')->insert([
            'access_token' => $accessToken,
            'created_at'   => Carbon::now()
        ]);
    }

    /**
     * Get the latest access token from the database.
     * 
     * @return string
     */
    public function getLatestAccessTokenFromDB()
    {
        $latest = DB::table('youtube_access_tokens')
                    ->latest('created_at')
                    ->first();

        return $latest ? (is_array($latest) ? $latest['access_token'] : $latest->access_token ) : null;
    }

    /**
     * Handle the Access Token
     * 
     * @return void
     */
    public function handleAccessToken()
    {
        $accessToken = $this->client->getAccessToken();

        if (is_null($accessToken)) {
            throw new \Exception('An access token is required.');
        }

        if ($this->client->isAccessTokenExpired()) {
            $accessToken = json_decode($accessToken);
            $refreshToken = $accessToken->refresh_token;
            $this->client->refreshToken($refreshToken);
            $newAccessToken = $this->client->getAccessToken();
            $this->saveAccessTokenToDB($newAccessToken);
        }
    }

    /**
     * Pass method calls to the Google Client.
     *
     * @param  string  $method
     * @param  array   $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->client, $method], $args);
    }
}
