<!doctype html>
<html lang="id">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#004AAD">
    <title>E-Presensi | Login</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/logopresensi.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/img/logopresensi.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>

<body class="bg-white">

    <div id="loader">
        <div class="spinner-border text-primary" role="status"></div>
    </div>
    <div id="appCapsule" class="p-4 d-flex flex-column justify-content-center align-items-center vh-100">

        <div class="login-card shadow-lg p-5 bg-light rounded-3 w-100" style="max-width: 400px;">
            <div class="text-center mb-4">
                <img src="{{ asset('assets/img/logopresensi.png') }}" alt="logo" class="mb-3" style="width: 180px; height: auto;">
                <h1 class="h2 fw-bold text-primary">E-Presensi</h1>
                <p class="text-muted mt-2">Silakan Login untuk melanjutkan</p>
            </div>

            @php
                $messagewarning = Session::get('warning');
            @endphp
            @if (Session::get('warning'))
                <div class="alert alert-warning border-0 text-center" role="alert">
                    {{ $messagewarning }}
                </div>
            @endif

            <form action="/proseslogin" method="POST">
                @csrf
                <div class="mb-3 position-relative">
                    <label for="nik" class="form-label visually-hidden">NIP</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start">
                            <ion-icon name="person-outline"></ion-icon>
                        </span>
                        <input type="text" name="nik" class="form-control border-start-0 rounded-end" id="nik" placeholder="NIP" required>
                    </div>
                </div>

                <div class="mb-4 position-relative">
                    <label for="password" class="form-label visually-hidden">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start">
                            <ion-icon name="lock-closed-outline"></ion-icon>
                        </span>
                        <input type="password" class="form-control border-start-0 rounded-end" id="password" name="password" placeholder="Password" required>
                        <span class="input-group-text bg-white border-start-0" id="show_hide_password" style="cursor: pointer;">
                            <ion-icon name="eye-off-outline" id="eye-icon"></ion-icon>
                        </span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg rounded-3">Login</button>
                    <a href="page-forgot-password.html" class="text-center text-muted mt-2">Lupa Kata Sandi?</a>
                </div>
            </form>
        </div>
    </div>
    <script src="{{ asset('assets/js/lib/jquery-3.4.1.min.js') }}"></script>
    <script src="{{ asset('assets/js/lib/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/base.js') }}"></script>

    <script>
        $(function() {
            $("#show_hide_password").click(function(e) {
                e.preventDefault();
                const passwordInput = $("#password");
                const eyeIcon = $("#eye-icon");
                if (passwordInput.attr("type") == "text") {
                    passwordInput.attr("type", "password");
                    eyeIcon.attr("name", "eye-off-outline");
                } else {
                    passwordInput.attr("type", "text");
                    eyeIcon.attr("name", "eye-outline");
                }
            });
        });
    </script>

</body>
</html>