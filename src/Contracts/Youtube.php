<?php namespace Dawson\Youtube\Contracts;

interface Youtube
{
    /**
     * Saves the access token to the database.
     *
     * @param  string  $accessToken
     */
    public function saveAccessTokenToDB($accessToken);

    /**
     * Returns the last saved access token, if there is one, or null
     *
     * @return mixed
     */
    public function getLatestAccessTokenFromDB();

    /**
     * Upload the video to YouTube
     *
     * @param  string   $path           The path to the file you wish to upload.
     * @param  array    $data           An array of data.
     * @param  string   $privacyStatus  The status of the uploaded video, set to 'public' by default.
     *
     * @return self
     */
    public function upload($path, array $data, $privacyStatus = 'public');

    /**
     * Set a Custom Thumbnail for the Upload
     *
     * @param  string  $imagePath
     *
     * @return self
     */
    public function withThumbnail($imagePath);

    /**
     * Return the Video ID
     *
     * @return string
     */
    public function getVideoId();

    /**
     * Return the URL for the Custom Thumbnail
     *
     * @return string
     */
    public function getThumbnailUrl();

    /**
     * Delete a YouTube video by it's ID.
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function delete($id);

    /**
     * Check if a YouTube video exists by it's ID.
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function exists($id);
}