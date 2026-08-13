<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
    </script>

    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">

    <style>
    @font-face{font-family:"Font Awesome 7 Brands";font-weight:400;font-style:normal;font-display:block;src:url('/build/assets/webfonts/fa-brands-400.woff2') format('woff2');}
    @font-face{font-family:"Font Awesome 7 Free";font-weight:400;font-style:normal;font-display:block;src:url('/build/assets/webfonts/fa-regular-400.woff2') format('woff2');}
    @font-face{font-family:"Font Awesome 7 Free";font-weight:900;font-style:normal;font-display:block;src:url('/build/assets/webfonts/fa-solid-900.woff2') format('woff2');}
    @font-face{font-family:"FontAwesome";font-weight:400;font-style:normal;font-display:block;src:url('/build/assets/webfonts/fa-v4compatibility.woff2') format('woff2');}
    </style>

    <title>Document</title>
</head>

<body>

    <!-- Button trigger modal -->
    <button type="button" class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#exampleModal">
        <i class="fa-solid fa-plus"></i> Nuevo Escenario
    </button>

    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalLabel">Nuevo Escenario</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('escenarios.store') }}" method="POST">
                        @method('POST')
                        @csrf
                        <input type="file" name="data[]" placeholder="Enter data" multiple>
                        <div class="modal-footer">
                            <button type="submit">Submit</button>
                            {{-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary">Save changes</button> --}}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('fontawesome/js/all.min.js') }}" defer></script>
</body>

</html>
