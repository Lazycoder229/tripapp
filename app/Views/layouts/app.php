<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>@yield('title', 'Trip')</title>

    @stack('meta')
    @stack('styles')

</head>

<body>


    @yield('content')

    @stack('scripts')

</body>
</html>