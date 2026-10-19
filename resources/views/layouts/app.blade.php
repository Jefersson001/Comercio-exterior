@extends('layouts.dashboard')

@section('title', $title ?? 'Mi cuenta')
@section('page-heading', $title ?? 'Mi cuenta')

@section('content')
    <div class="mx-auto max-w-6xl">{{ $slot }}</div>
@endsection
