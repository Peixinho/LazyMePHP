<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * $type (string)
 * $name (string)
 * $fieldname (string)
 * $placeholder (string) opcional
 * $id (string) opcional
 * $value (string) opcional
 * $validation (string) opcional
 * $validationfail (string) opcional
 */
?>

<div>
  <input 
    @switch($type)
    @case("number")
      type="number"
    @break
    @case("date")
      type="date"
    @break
    @case("text")
      type="text"
    @break
    @endswitch
    id="{{$id or $name}}" 
    name="{{$name}}"
    value="{{$value or ''}}"
    @if (isset($validation)) validation="{{$validation or ''}}" @endif
    @if (isset($validationfail)) validation-fail="{{$validationfail or ''}}" @endif
  >
  <label for="{{$name}}">{{$fieldname or $name}}</label>
</div>
