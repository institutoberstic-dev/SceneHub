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
    <a href="{{ route('roles.create') }}" type="button" class="btn btn-primary">Crear Rol</a>

    <table>
        Roles
            @forelse ($roles as $role)
            <div class="card" style="width: 18rem;">
                <div class="card-body">
                    <h5 class="card-title">Card title</h5>
                    <a href="#" class="btn btn-primary">Actualizar Rol</a>
                </div>
            </div>
            @empty
            <div class="card" style="width: 18rem;">
                <div class="card-body">
                    <h5 class="card-title">Sin roles disponibles</h5>
                </div>
            </div>
            @endforelse
        </tbody>
    </table>


</body>
</html>
