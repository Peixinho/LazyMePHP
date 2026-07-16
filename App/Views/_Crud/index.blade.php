@include('_Crud.template')

@component('_Components.Anchor', ['href' => '/' . $table . '/new', 'link' => 'Add New'])
@endcomponent
<br/>

<table>
    <tr>
        @if($pk)
            <th><b>edit</b></th>
            <th><b>delete</b></th>
        @endif
        @foreach($schema as $col => $meta)
            <th><b>{{ ucfirst(str_replace('_', ' ', $col)) }}</b></th>
        @endforeach
    </tr>

    @foreach($records as $row)
    <tr>
        @if($pk)
        <td>
            <a href="/{{ $table }}/{{ $row->getPrimaryKey() }}/edit">
                @component('_Components.Button', ['type' => 'button', 'name' => 'edit'])
                @endcomponent
            </a>
        </td>
        <td>
            <form method="POST" action="/{{ $table }}/{{ $row->getPrimaryKey() }}/delete" style="display:inline;">
                @component('_Components.CSRF') @endcomponent
                @component('_Components.Button', ['type' => 'submit', 'name' => 'delete', 'onclick' => 'return confirm(\'Are you sure?\')'])
                @endcomponent
            </form>
        </td>
        @endif
        @foreach($schema as $col => $meta)
            <td>{{ $row->$col }}</td>
        @endforeach
    </tr>
    @endforeach
</table>

@component('_Components.Pagination', ['total' => $length, 'current' => $current, 'limit' => $limit])
@endcomponent
