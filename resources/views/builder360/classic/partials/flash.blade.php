@if (session('status'))
    <x-ui.alert tone="success" dismissible>{{ session('status') }}</x-ui.alert>
@endif

@if (session('error'))
    <x-ui.alert tone="danger" dismissible>{{ session('error') }}</x-ui.alert>
@endif

@if ($errors->any())
    <x-ui.alert tone="danger" title="Please correct the highlighted fields.">
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
