<?php

namespace Core\Http;

class Request
{
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function get(?string $key = null, mixed $default = null) : mixed
    {
        if ($key === null) return $_GET;
        return $_GET[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null) : mixed
    {
        if ($key === null) return $_POST;
        return $_POST[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null) : mixed
    {
        // Merge GET and POST, POST takes precedence
        $data = array_merge($_GET, $_POST);
        if ($key === null) return $data;
        return $data[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null) : mixed
    {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$headerKey] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function json(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }
} 