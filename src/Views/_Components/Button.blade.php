<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * $name (string)
 * $type (string)
 * $fieldname (string)
 * $id (string) opcional
 * $value (string)
 */
?>

<button 
  @switch($type)
    @case('submit')
      type="submit"
    @break
    @default
    @case('button')
      type="button"
    @break
  @endswitch
  name="{{$name}}"
  id="{{$id or $name}}"
  @if (isset($onclick)) onclick="{{$onclick}}" @endif
>
  {{$fieldname or $name}}
  </button>
