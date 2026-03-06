<!DOCTYPE html>
<html lang="en" class="js">

<head>
    <meta charset="utf-8">
    <meta name="author" content="Softnio">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description"
        content="A powerful and conceptual apps base dashboard template that especially build for developers and programmers.">
    <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
    <title>Register | Markor</title>
    <link rel="stylesheet" href="{{ asset('assets/css/dashlite.css') }}">
    <link id="skin-default" rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
</head>

<body class="nk-body bg-white npc-general pg-auth">
    <div class="nk-app-root">
        <div class="nk-main">
            <div class="nk-wrap nk-wrap-nosidebar">
                <div class="nk-content">
                    <div class="nk-block nk-block-middle nk-auth-body wide-xs">
                        <div class="brand-logo pb-4 text-center">
                            <a href="{{ url('/') }}" class="logo-link">
                                <img class="logo-light logo-img logo-img-lg" src="{{ asset('images/brand/markor-logo.svg') }}"
                                    srcset="{{ asset('images/brand/markor-logo.svg') }} 2x" alt="logo">
                                <img class="logo-dark logo-img logo-img-lg" src="{{ asset('images/brand/markor-logo.svg') }}"
                                    srcset="{{ asset('images/brand/markor-logo.svg') }} 2x" alt="logo-dark">
                            </a>
                        </div>
                        <div class="card card-bordered">
                            <div class="card-inner card-inner-lg">
                                <div class="nk-block-head">
                                    <div class="nk-block-head-content">
                                        <h4 class="nk-block-title">Register</h4>
                                        <div class="nk-block-des">
                                            <p>Create your Markor account</p>
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('register') }}">
                                    @csrf

                                    <div class="form-group">
                                        <label class="form-label" for="name">Name</label>
                                        <div class="form-control-wrap">
                                            <input id="name" type="text"
                                                class="form-control form-control-lg @error('name') is-invalid @enderror"
                                                name="name" value="{{ old('name') }}" required autocomplete="name"
                                                autofocus placeholder="Enter your name">
                                        </div>
                                        @error('name')
                                            <span class="invalid-feedback d-block" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="email">Email</label>
                                        <div class="form-control-wrap">
                                            <input id="email" type="email"
                                                class="form-control form-control-lg @error('email') is-invalid @enderror"
                                                name="email" value="{{ old('email') }}" required autocomplete="email"
                                                placeholder="Enter your email address">
                                        </div>
                                        @error('email')
                                            <span class="invalid-feedback d-block" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="password">Password</label>
                                        <div class="form-control-wrap">
                                            <a href="#" class="form-icon form-icon-right passcode-switch lg"
                                                data-target="password">
                                                <em class="passcode-icon icon-show icon ni ni-eye"></em>
                                                <em class="passcode-icon icon-hide icon ni ni-eye-off"></em>
                                            </a>
                                            <input id="password" type="password"
                                                class="form-control form-control-lg @error('password') is-invalid @enderror"
                                                name="password" required autocomplete="new-password"
                                                placeholder="Enter your password">
                                        </div>
                                        @error('password')
                                            <span class="invalid-feedback d-block" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="password-confirm">Confirm Password</label>
                                        <div class="form-control-wrap">
                                            <input id="password-confirm" type="password" class="form-control form-control-lg"
                                                name="password_confirmation" required autocomplete="new-password"
                                                placeholder="Confirm your password">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <button class="btn btn-lg btn-primary btn-block" type="submit">Register</button>
                                    </div>
                                </form>

                                @if (Route::has('login'))
                                    <div class="form-note-s2 text-center pt-4">
                                        Already have an account?
                                        <a href="{{ route('login') }}"><strong>Sign in instead</strong></a>
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
