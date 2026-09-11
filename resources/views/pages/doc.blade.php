@extends('vellum::layouts.docs')

@section('title', $document->title.' · '.($name ?? config('vellum.name')))

@section('content')
    <article>
        <h1>{{ $document->title }}</h1>
        {!! $document->html !!}
    </article>
@endsection
