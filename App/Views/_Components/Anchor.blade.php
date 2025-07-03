<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * $href (string)
 * $target (string)
 * $link (string)
 */
?>

<a 
  href="{{ \Core\Helpers\Helper::e($href) }}" 
  @if (isset($target)) target="{{$target}}" @endif
>{{ \Core\Helpers\Helper::e($link) }}</a>
