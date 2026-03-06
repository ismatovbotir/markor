<!DOCTYPE html>
<html lang="en" class="js">

<head>
    <meta charset="utf-8">
    <meta name="author" content="Softnio">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description"
        content="A powerful and conceptual apps base dashboard template that especially build for developers and programmers.">
    <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
    <title>{{ $pageTitle ?? 'Success' }} | Markor</title>
    <link rel="stylesheet" href="{{ asset('assets/css/dashlite.css') }}">
    <link id="skin-default" rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
</head>

<body class="nk-body bg-white npc-general pg-auth">
    <div class="nk-app-root">
        <div class="nk-main">
            <div class="nk-wrap nk-wrap-nosidebar">
                <div class="nk-content">
                    <div class="nk-block nk-block-middle nk-auth-body">
                        <div class="brand-logo pb-5">
                            <a href="{{ url('/') }}" class="logo-link">
                                <img class="logo-light logo-img logo-img-lg" src="{{ asset('images/brand/markor-logo.svg') }}"
                                    srcset="{{ asset('images/brand/markor-logo.svg') }} 2x" alt="logo">
                                <img class="logo-dark logo-img logo-img-lg" src="{{ asset('images/brand/markor-logo.svg') }}"
                                    srcset="{{ asset('images/brand/markor-logo.svg') }} 2x" alt="logo-dark">
                            </a>
                        </div>
                        <div class="nk-block-head">
                            <div class="nk-block-head-content">
                                <h4 class="nk-block-title">{{ $title ?? 'Success' }}</h4>
                                <div class="nk-block-des text-success">
                                    <p>{{ $message ?? 'Your request completed successfully.' }}</p>
                                </div>
                                @if (Route::has('login'))
                                    <div class="pt-3">
                                        <a class="btn btn-primary" href="{{ route('login') }}">Go to Login</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="nk-footer nk-auth-footer-full">
                        <div class="container wide-lg">
                            <div class="row g-3">
                                <div class="col-lg-6 order-lg-last">
                                    <ul class="nav nav-sm justify-content-center justify-content-lg-end">
                                        <li class="nav-item"><a class="nav-link" href="#">Terms & Condition</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#">Privacy Policy</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#">Help</a></li>
                                    </ul>
                                </div>
                                <div class="col-lg-6">
                                    <div class="nk-block-content text-center text-lg-left">
                                        <p class="text-soft">&copy; {{ date('Y') }} Markor. All Rights Reserved.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/js/bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.js') }}"></script>
</body>

</html>
