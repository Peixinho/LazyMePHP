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
 */
?>

<div>
  <select
    id="{{$id or $name}}" 
    name="{{$name}}"
    class="validate"
  >
    @if ($defaultValueEmpty) 
      <option value="">-</option>
    @endif
    @foreach($options as $o)
      <option value="{{$o->Getid()}}" @if (isset($selected) && $selected==$o->Getid()) selected @endif>{{$o->Getdescriptor()}}</option> 
    @endforeach
  </select>
  <label>{{$fieldname}}</label>
</div>
