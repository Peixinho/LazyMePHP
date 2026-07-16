<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * $name (string)
 * $fieldname (string)
 * $options (array) — list of ['value' => mixed, 'label' => string]
 * $selected (mixed) opcional
 * $defaultValueEmpty (bool) opcional
 * $id (string) opcional
 * $validation (string) opcional
 * $validationfail (string) opcional
 */
?>

<div>
  <label for="{{$id or $name}}">{{ \Core\Helpers\Helper::e($fieldname) }}</label>
  <select
    id="{{$id or $name}}"
    name="{{$name}}"
    class="form-control validate"
    @if (isset($validation)) validation="{{ \Core\Helpers\Helper::e($validation) }}" @endif
    @if (isset($validationfail)) validation-fail="{{ \Core\Helpers\Helper::e($validationfail) }}" @endif
  >
    @if ($defaultValueEmpty ?? false)
      <option value="">-</option>
    @endif
    @foreach($options as $o)
      <option value="{{ \Core\Helpers\Helper::e($o['value']) }}" @if (isset($selected) && (string)$selected === (string)$o['value']) selected @endif>{{ \Core\Helpers\Helper::e($o['label']) }}</option>
    @endforeach
  </select>
</div>
