<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */
 ?>

@set($countPage = ceil($total / $limit))
@if ($countPage > 1)
<div class="pagination">
    <!-- Previous Button -->
    @if ($current > 1)
        <span class="prev"><a href="?{{http_build_query(array_merge($_GET, ['page' => ($current-1)]))}}">&lt;&lt;</a></span>
    @else
        <span class="prev disabled" aria-disabled="true">&lt;&lt;</span>
    @endif

    <!-- Page Numbers -->
    @for ($i = 1; $i <= $countPage; $i++)
        @if ($i != $current)
            <span class="page"><a href="?{{http_build_query(array_merge($_GET, ['page' => $i]))}}">{{$i}}</a></span>
        @else
            <span class="page active" aria-current="page">{{$i}}</span>
        @endif
    @endfor

    <!-- Next Button -->
    @if (($current + 1) <= $countPage)
        <span class="next"><a href="?{{http_build_query(array_merge($_GET, ['page' => ($current+1)]))}}">&gt;&gt;</a></span>
    @else
        <span class="next disabled" aria-disabled="true">&gt;&gt;</span>
    @endif
</div>
@endif
