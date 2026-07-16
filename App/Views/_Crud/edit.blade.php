@include('_Crud.template')

@component('_Components.Anchor', ['href' => '/' . $table, 'link' => 'Back to list'])
@endcomponent
<br/><br/>

<form id="crudForm" method="POST" action="/{{ $table }}/{{ $record->getPrimaryKey() ?? '' }}">
    @component('_Components.CSRF') @endcomponent

    @foreach($schema as $col => $meta)
        @if($meta['pk']) @continue @endif

        @php
            $label = ucfirst(str_replace('_', ' ', $col));
            $value = $record->$col;
            $required = !$meta['nullable'];
            $validation = $required ? 'notnull' : '';
            $validationFail = $required ? "$label cannot be empty" : '';
            $fk = $foreignKeys[$col] ?? null;
            $type = match(true) {
                $fk !== null => 'select',
                str_contains($meta['type'], 'bool') || str_contains($meta['type'], 'bit') => 'checkbox',
                str_contains($meta['type'], 'int')  => 'number',
                str_contains($meta['type'], 'real') || str_contains($meta['type'], 'float')
                    || str_contains($meta['type'], 'decimal') => 'number',
                str_contains($meta['type'], 'date') => 'date',
                str_contains(strtolower($col), 'email') => 'email',
                default => 'text',
            };
        @endphp

        @if($type === 'select')
            @component('_Components.Select', [
                'name'              => $col,
                'fieldname'         => $label,
                'options'           => $fk['options'],
                'selected'          => $value,
                'defaultValueEmpty' => !$required,
                'validation'        => $validation,
                'validationfail'    => $validationFail,
            ])
            @endcomponent
        @elseif($type === 'checkbox')
            @component('_Components.Checkbox', [
                'name'      => $col,
                'fieldname' => $label,
                'value'     => '1',
                'checked'   => $value ? 'checked' : '',
            ])
            @endcomponent
        @else
            @component('_Components.TextInput', [
                'name'           => $col,
                'fieldname'      => $label,
                'placeholder'    => $label,
                'type'           => $type,
                'value'          => $value,
                'validation'     => $validation,
                'validationfail' => $validationFail,
            ])
            @endcomponent
        @endif
    @endforeach

    <br/>
    @component('_Components.Button', ['type' => 'submit', 'name' => 'Save'])
    @endcomponent
    @component('_Components.Button', ['type' => 'button', 'name' => 'Cancel', 'onclick' => 'window.open("/' . $table . '","_self");'])
    @endcomponent
</form>
