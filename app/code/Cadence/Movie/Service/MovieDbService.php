<?php

namespace Cadence\Movie\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\InvalidArgumentException;

class MovieDbService
{
    /**
     * API request URL
     */
    const API_REQUEST_URI = 'https://api.themoviedb.org/';

    const API_KEY = 'e10ac4b03633e2a756c7e9cb0931a73a';

    private Client $client;

    private array $configurations;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => self::API_REQUEST_URI]);
        $this->configurations = $this->fetchConfigurations();
    }

    /**
     * Retrieves popular movies
     * @param string $lang
     * @param string $page
     * @return mixed
     * @throws GuzzleException
     */
    public function getPopular(string $lang = 'en-US', string $page = '1'): mixed
    {
        $popularMovies = $this->client->request('GET', "3/movie/popular?language={$lang}&page={$page}&api_key=" . self::API_KEY);
        return json_decode($popularMovies->getBody(), true);
    }


    /**
     * Retrieves movie details based on movie ID
     * @param $movieId
     * @param string $lang
     * @return mixed
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getDetails($movieId, string $lang = 'en-US'): mixed
    {
        if (empty($movieId)) {
            throw new InvalidArgumentException(__('Movie ID is not provided, can\'t retrieve details. Please provide movie ID to proceed'));
        }

        $movieDetails = $this->client->request('GET', "3/movie/{$movieId}?language={$lang}&api_key=" . self::API_KEY);
        return json_decode($movieDetails->getBody(), true);
    }

    /**
     * Retrieves credits based on movie ID
     * @param $movieId
     * @param string $lang
     * @return mixed
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getCredits($movieId, string $lang = 'en-US'): mixed
    {
        if (empty($movieId)) {
            throw new InvalidArgumentException(__('Movie ID is not provided, can\'t retrieve credits. Please provide movie ID to proceed'));
        }

        $movieDetails = $this->client->request('GET', "3/movie/{$movieId}/credits?language={$lang}&api_key=" . self::API_KEY);
        return json_decode($movieDetails->getBody(), true);
    }

    /**
     * Call image API and get images for the movie
     * @param $movieId
     * @param string $lang
     * @return mixed
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getImages($movieId, string $lang = 'en-US'): mixed
    {
        if (empty($movieId)) {
            throw new InvalidArgumentException(__('Movie ID is not provided, can\'t retrieve images. Please provide movie ID to proceed'));
        }

        $movieDetails = $this->client->request('GET', "3/movie/{$movieId}/images?api_key=" . self::API_KEY);
        return json_decode($movieDetails->getBody(), true);
    }

    /**
     * Fetch and store configurations in the current instance
     * @return array
     * @throws GuzzleException
     */
    public function fetchConfigurations(): array
    {
        $configurations = $this->client->request('GET', "3/configuration?api_key=" . self::API_KEY);
        return json_decode($configurations->getBody(), true);
    }

    /**
     * Get base URL for images. Comes from configs.
     * @return string
     */
    public function getImageBaseUrl(): string
    {
        return $this->configurations['images']['base_url'];
    }
}
