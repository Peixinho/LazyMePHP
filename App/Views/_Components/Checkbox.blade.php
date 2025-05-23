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

<p>
  <label>
    <span>{{$fieldname or $name}}</span>
    <input 
      @switch($type)
        @case('radio')
          type="radio"
        @break
        @default
        @case('checkbox')
          type="checkbox"
        @break
      @endswitch
      id="{{$id or $name}}" 
      name="{{$name}}"
      value="{{$value}}"
      @if (isset($checked)) checked @endif
    />
  </label>
</p>

