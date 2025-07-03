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

<button 
  @switch($type)
    @case('submit')
      type="submit"
    @break
    @default
    @case('button')
      type="button"
    @break
  @endswitch
  name="{{ \Core\Helpers\Helper::e($name) }}"
  id="{{ \Core\Helpers\Helper::e($id ?? $name) }}"
  @if (isset($onclick)) onclick="{{ \Core\Helpers\Helper::e($onclick) }}" @endif
>
  {{ \Core\Helpers\Helper::e($fieldname ?? $name) }}
  </button>
