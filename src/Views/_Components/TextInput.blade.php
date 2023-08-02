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
  >
  <label for="{{$name}}">{{$fieldname or $name}}</label>
</div>
