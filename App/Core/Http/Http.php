<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Static HTTP facade. Each method creates a fresh HttpClient.
 *
 *   Http::get('https://api.example.com/users');
 *   Http::post('https://api.example.com/users', ['name' => 'Alice']);
 *
 *   Http::withToken($jwt)->get('https://api.example.com/me');
 *   Http::withHeaders(['X-Api-Key' => $key])->timeout(5)->post($url, $payload);
 *   Http::withBasicAuth('user', 'pass')->get($url);
 */
class Http
{
    public static function new(): HttpClient
    {
        return new HttpClient();
    }

    public static function withHeaders(array $headers): HttpClient
    {
        return (new HttpClient())->withHeaders($headers);
    }

    public static function withToken(string $token, string $type = 'Bearer'): HttpClient
    {
        return (new HttpClient())->withToken($token, $type);
    }

    public static function withBasicAuth(string $user, string $password): HttpClient
    {
        return (new HttpClient())->withBasicAuth($user, $password);
    }

    public static function baseUrl(string $url): HttpClient
    {
        return (new HttpClient())->baseUrl($url);
    }

    public static function timeout(int $seconds): HttpClient
    {
        return (new HttpClient())->timeout($seconds);
    }

    public static function withoutVerifying(): HttpClient
    {
        return (new HttpClient())->withoutVerifying();
    }

    public static function get(string $url, array $query = []): HttpResponse
    {
        return (new HttpClient())->get($url, $query);
    }

    public static function post(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->post($url, $body);
    }

    public static function put(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->put($url, $body);
    }

    public static function patch(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->patch($url, $body);
    }

    public static function delete(string $url, array|string $body = []): HttpResponse
    {
        return (new HttpClient())->delete($url, $body);
    }
}
