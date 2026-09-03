<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    @forelse ($meetings['meetings'] as $meeting)
        <p>{{ $meeting['title'] }}</p>
        <p>{{ $meeting['topic'] }}</p>
        <!-- Formato de fecha: 02/09/2026 -->
        <p>{{ \Carbon\Carbon::parse($meeting['start_datetime'])->format('d/m/Y') }}</p>
        <!-- Formato de hora: 02:30 pm -->
        <p>{{ \Carbon\Carbon::parse($meeting['start_datetime'])->format('h:i a') }}</p>
        <p><a href="https://bersticlive.org/api/meetings/{{ $meeting['id'] }}" target="_blank">Ver detalles</a></p>
    @empty
        <p>No meetings found.</p>
    @endforelse
</body>
</html>
