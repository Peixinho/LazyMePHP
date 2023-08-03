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
  <select
    id="{{$id or $name}}" 
    name="{{$name}}"
    class="validate"
    validation="{{$validation or ''}}"
    validation-fail="{{$validationfail or ''}}"
  >
    @if ($defaultValueEmpty) 
      <option value="">-</option>
    @endif
    @foreach($options as $o)
      <option value="{{$o->GetPrimaryKey()}}" @if (isset($selected) && $selected==$o->GetPrimaryKey()) selected @endif>{{$o->Getdescriptor()}}</option> 
    @endforeach
  </select>
  <label>{{$fieldname}}</label>
</div>
