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
  <label for="{{$name}}">{{$fieldname or $name}}</label>
  <input 
    @switch($type)
    @case("number")
      type="number"
      value="{{ \Core\Helpers\Helper::e($value ?? '') }}" 
    @break
    @case("date")
      type="date"
      value="{{ \Core\Helpers\Helper::e($value ?? '') }}" 
    @break
    @case("text")
      type="text"
      value="{{ \Core\Helpers\Helper::e($value ?? '') }}" 
    @break
    @case("password")
      type="password"
    @break
    @endswitch
    id="{{$id or $name}}" 
    name="{{$name}}"
    @if ($type!="password") @endif
    class="form-control"
    placeholder="{{ \Core\Helpers\Helper::e($placeholder ?? ' ') }}"
    @if (isset($validation)) validation="{{ \Core\Helpers\Helper::e($validation ?? '') }}" @endif
    @if (isset($validationfail)) validation-fail="{{ \Core\Helpers\Helper::e($validationfail ?? '') }}" @endif
    @if (isset($maxlength)) maxlength="{{ \Core\Helpers\Helper::e($maxlength) }}" @endif
    @if (isset($validation)) aria-describedby="validation{{$id or $name}}Feedback" @endif
   />
</div>
