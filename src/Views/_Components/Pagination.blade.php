<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */
 ?>

<div>
    @set($countPage = ($total/$limit))
    @if ($current > 1) <a href="?{{http_build_query(array_merge($_GET, array('page' => ($current-1))))}}">&lt;&lt;</a> @endif
    @for ($i = 1; $i < $countPage + 1; $i++)
      @if ($i!=$current) <a href="?{{http_build_query(array_merge($_GET, array('page' => $i)))}}">{{$i}}</a> 
      @else <span>[ {{$i}} ]</span>
      <a href="?{{http_build_query(array_merge($_GET, array('page' => $i)))}}">{{$i}}</a> 
      @endif
    @endfor
    @if (($current+1) <= $countPage) <a href="?{{http_build_query(array_merge($_GET, array('page' => ($current+1))))}}">&gt;&gt;</a> @endif
</div>
