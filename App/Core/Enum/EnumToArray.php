<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Core\Enum;

trait EnumToArray
{
  public static function names(): array
  {
    return array_column(self::cases(), 'name');
  }

  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  public static function descriptions(): array
  {
    $r = array();
    foreach(self::cases() as $c) {
      $r[$c->value] = $c->getDescription();
    }
    return $r;
  }

  public static function array(): array
  {
    return array_combine(self::values(), self::names());
  }

  public static function arrayDescription():array
  {
    return array_combine(self::values(), self::descriptions());
  }
}
