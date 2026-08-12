@extends('layouts.app')

@section('title')
    Home
@endsection

@push('meta')

<meta
    name="description"
    content="Home page"
/>

<meta
    name="author"
    content="Trip Framework"
/>

@endpush

@section('content')

<h1>Users</h1>

<ul>
@foreach ($users as $user)
    <li>
        {{ $user['id'] }} -
        {{ $user['name'] }} -
        {{ $user['email'] }}
    </li>
@endforeach
</ul>

@endsection

@push('scripts')

<script>
    console.log('Home page loaded');
</script>

@endpush