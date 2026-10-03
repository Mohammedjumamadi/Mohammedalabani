@extends('layouts.public')

@section('title', __('pages.'.$page.'.title'))

@section('content')
<div class="container section" style="max-width:52rem">
    <h1>{{ __('pages.'.$page.'.title') }}</h1>
    <p class="lead text-muted">{{ __('pages.'.$page.'.intro') }}</p>

    @foreach (__('pages.'.$page.'.sections') as $section)
        <section class="mt-4">
            <h2 class="h4">{{ $section['h'] }}</h2>
            @foreach ($section['p'] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </section>
    @endforeach
</div>
@endsection
