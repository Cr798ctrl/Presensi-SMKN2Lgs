<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Dashboard Admin | E-Presensi</title>
    <link href="{{ asset('tabler/dist/css/tabler.min.css?1674944402') }}" rel="stylesheet" />
    <link href="{{ asset('tabler/dist/css/tabler-vendors.min.css?1674944402') }}" rel="stylesheet" />
    <style>
        @import url('https://rsms.me/inter/inter.css');
        :root {
            --tblr-font-sans-serif: 'Inter Var', -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif;
        }
        body {
            font-feature-settings: "cv03", "cv04", "cv11";
        }
        .page-center {
            background-color: #f8fafc; /* Latar belakang abu-abu muda */
        }
        .login-card {b
            border: none;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.08); /* Shadow yang lebih lembut */
            border-radius: 1.5rem; /* Sudut lebih melengkung */
        }
        .form-label {
            font-weight: 600; /* Label lebih tebal */
            color: #495057;
        }
        .form-control {
            border-radius: 0.75rem; /* Sudut input field lebih melengkung */
            padding: 0.75rem 1rem;
        }
    </style>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body class=" d-flex flex-column">
    <script src="{{ asset('tabler/dist/js/demo-theme.min.js?1674944402') }}"></script>
    <div class="page page-center">
        <div class="container container-normal py-4">
            <div class="row align-items-center g-4">
                <div class="col-lg d-none d-lg-block">
                    <img src="{{ asset('assets/img/logopresensi.png') }}" height="300" class="d-block mx-auto" alt="Login Illustration">
                </div>
                <div class="col-lg">
                    <div class="container-tight">
                        <div class="text-center mb-5">
                            <a href="." class="navbar-brand navbar-brand-autodark"><img src="./static/logo.svg" height="36" alt=""></a>
                            <h2 class="h2 mt-4 text-center">Login Admin E-Presensi</h2>
                            <p class="text-muted">Masukkan kredensial Anda untuk masuk ke dashboard</p>
                        </div>
                        <div class="card login-card">
                            <div class="card-body">
                                @if (Session::get('warning'))
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <p class="mb-0">{{ Session::get('warning') }}</p>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                @endif
                                <form action="/prosesloginadmin" method="post" autocomplete="off" novalidate>
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">Alamat Email</label>
                                        <div class="input-group input-group-flat">
                                            <span class="input-group-text">
                                                <ion-icon name="mail-outline"></ion-icon>
                                            </span>
                                            <input type="email" name="email" class="form-control" placeholder="your@email.com" autocomplete="off" required>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">
                                            Password
                                        </label>
                                        <div class="input-group input-group-flat">
                                            <span class="input-group-text">
                                                <ion-icon name="lock-closed-outline"></ion-icon>
                                            </span>
                                            <input type="password" name="password" class="form-control" id="password_field" placeholder="Password Anda" autocomplete="off" required>
                                            <span class="input-group-text" id="show-password-toggle" style="cursor: pointer;">
                                                <ion-icon name="eye-outline" id="eye-icon"></ion-icon>
                                            </span>
                                        </div>
                                        <span class="form-label-description mt-2 text-end d-block">
                                            <a href="./forgot-password.html">Lupa password?</a>
                                        </span>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-check">
                                            <input type="checkbox" class="form-check-input" />
                                            <span class="form-check-label">Ingat saya</span>
                                        </label>
                                    </div>
                                    <div class="form-footer">
                                        <button type="submit" class="btn btn-primary w-100">Masuk</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('tabler/dist/js/tabler.min.js?1674944402') }}" defer></script>
    <script src="{{ asset('tabler/dist/js/demo.min.js?