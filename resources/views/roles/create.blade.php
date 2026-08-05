<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <title>Document</title>
</head>
<body>

    <form action="{{ route('roles.create') }}" method="post">
        @method('POST')
        @csrf
        <input type="text" name="name" placeholder="Rol">
        <input type="text" name="guard_name" placeholder="Guard Name">
        <select name="permissions[]" multiple>
            @forelse ($permisos as $permiso)
                <option value="{{ $permiso->id }}">{{ $permiso->name }}</option>
            @empty
            @endforelse
        <button type="submit">Submit</button>
    </form>

</body>
</html>
