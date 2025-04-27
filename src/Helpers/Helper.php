<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace LazyMePHP\Helper;

function TransformDateDDMMYYYtoYYYMMDD($date)
{
  return substr($date,6,4)."/".substr($date,3,2)."/".substr($date,0,2);
}
function TransformDateYYYMMDDtoDDMMYYY($date)
{
  return substr($date,8,2)."/".substr($date,5,2)."/".substr($date,0,4);
}

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\Http\Url;
use Pecee\Http\Response;
use Pecee\Http\Request;

/**
 * Get url for a route by using either name/alias, class or method name.
 *
 * The name parameter supports the following values:
 * - Route name
 * - Controller/resource name (with or without method)
 * - Controller class name
 *
 * When searching for controller/resource by name, you can use this syntax "route.name@method".
 * You can also use the same syntax when searching for a specific controller-class "MyController@home".
 * If no arguments is specified, it will return the url for the current loaded route.
 *
 * @param string|null $name
 * @param string|array|null $parameters
 * @param array|null $getParams
 * @return \Pecee\Http\Url
 * @throws \InvalidArgumentException
 */
function url(?string $name = null, $parameters = null, ?array $getParams = null): Url
{
  return Router::getUrl($name, $parameters, $getParams);
}

/**
 * @return \Pecee\Http\Response
 */
function response(): Response
{
  return Router::response();
}

/**
 * @return \Pecee\Http\Request
 */
function request(): Request
{
  return Router::request();
}

/**
 * Get input class
 * @param string|null $index Parameter index name
 * @param string|mixed|null $defaultValue Default return value
 * @param array ...$methods Default methods
 * @return \Pecee\Http\Input\InputHandler|array|string|null
 */
function input($index = null, $defaultValue = null, ...$methods)
{
  if ($index !== null) {
    return request()->getInputHandler()->value($index, $defaultValue, ...$methods);
  }

  return request()->getInputHandler();
}

/**
 * @param string $url
 * @param int|null $code
 */
function redirect(string $url, ?int $code = null): void
{
  // Save activity before redirect
  \LazyMePHP\Config\Internal\APP::LOG_ACTIVITY();

  if ($code !== null) {
    response()->httpCode($code);
  }

  response()->redirect($url);
}

/**
 * Get current csrf-token
 * @return string|null
 */
function csrf_token(): ?string
{
  $baseVerifier = Router::router()->getCsrfVerifier();
  if ($baseVerifier !== null) {
    return $baseVerifier->getTokenProvider()->getToken();
  }

  return null;
}
?>
