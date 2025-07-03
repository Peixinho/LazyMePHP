<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
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
  <label for="{{$name}}">{{ \Core\Helpers\Helper::e($fieldname ?? $name) }}</label>
  <input 
    type="checkbox"
    id="{{$id ?? $name}}" 
    name="{{$name}}"
    value="{{ \Core\Helpers\Helper::e($value ?? '1') }}"
    class="form-control"
    placeholder="{{ \Core\Helpers\Helper::e($placeholder ?? ' ') }}"
    @if (isset($validation)) validation="{{ \Core\Helpers\Helper::e($validation ?? '') }}" @endif
    @if (isset($validationfail)) validation-fail="{{ \Core\Helpers\Helper::e($validationfail ?? '') }}" @endif
    @if (isset($checked) && $checked) checked @endif
    @if (isset($validation)) aria-describedby="validation{{$id ?? $name}}Feedback" @endif
   />
</div>

