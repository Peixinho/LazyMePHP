<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */
 ?>

<div>
  @set($countPage = ($total/$limit))
  @if ($current > 1) <span><a href="?{{http_build_query(array_merge($_GET, array('page' => ($current-1))))}}">&lt;&lt;</i></a></span>
  @else <span>&lt;&lt;</span>
  @endif
  @for ($i = 1; $i < $countPage + 1; $i++)
    @if ($i!=$current) <span><a href="?{{http_build_query(array_merge($_GET, array('page' => $i)))}}">{{$i}}</a></span>
    @else <span>{{$i}}</span>
    @endif
  @endfor
  @if (($current+1) <= $countPage) <a href="?{{http_build_query(array_merge($_GET, array('page' => ($current+1))))}}">&gt;&gt;</a></span>
  @else <span>&gt;&gt;</span>
  @endif
</div>
