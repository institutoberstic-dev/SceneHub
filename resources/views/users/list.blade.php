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
    <a href="{{ route('users.create') }}" type="button" class="btn btn-primary">Create User</a>

    <table>
        <th>Nombre</th>
        <th>Email</th>
        <th>Roles</th>
        <tbody>
            @forelse ($users as $user)
            <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            @empty
            <tr>
                <td colspan="3">No users found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>


</body>
</html>
