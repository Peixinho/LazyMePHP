<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * $name (string)
 * $fieldname (string)
 * $defaulValueEmpty (bool)
 * $options (array)
 * $id (string) opcional
 * $validation (string) opcional
 * $validationfail (string) opcional
 */
?>

<div>
  <label>{{ \Core\Helpers\Helper::e($fieldname) }}</label>
  <select
    id="{{$id or $name}}" 
    name="{{$name}}"
    class="validate"
    validation="{{ \Core\Helpers\Helper::e($validation ?? '') }}"
    validation-fail="{{ \Core\Helpers\Helper::e($validationfail ?? '') }}"
  >
    @if ($defaultValueEmpty) 
      <option value="">-</option>
    @endif
    @foreach($options as $o)
      <option value="{{ \Core\Helpers\Helper::e($o->GetPrimaryKey()) }}" @if (isset($selected) && $selected==$o->GetPrimaryKey()) selected @endif>{{ \Core\Helpers\Helper::e($o->Getdescriptor()) }}</option> 
    @endforeach
  </select>
</div>
