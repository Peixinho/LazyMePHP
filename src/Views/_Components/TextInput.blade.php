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
      value="{{$value or ''}}" 
    @break
    @case("date")
      type="date"
      value="{{$value or ''}}" 
    @break
    @case("text")
      type="text"
      value="{{$value or ''}}" 
    @break
    @case("password")
      type="password"
    @break
    @endswitch
    id="{{$id or $name}}" 
    name="{{$name}}"
    @if ($type!="password") @endif
    class="form-control"
    placeholder="{{$placeholder or ' '}}"
    @if (isset($validation)) validation="{{$validation or ''}}" @endif
    @if (isset($maxlength)) maxlength="{{$maxlength}}" @endif
    @if (isset($validation)) aria-describedby="validation{{$id or $name}}Feedback" @endif
   />
  <label for="{{$name}}">{{$fieldname or $name}}</label>
</div>
