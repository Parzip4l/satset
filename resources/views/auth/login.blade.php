@extends('partials.layouts.master_auth')

@section('title', 'Sign In | SatSet Admin & Dashboard')

@section('content')

    <div>
        <img src="assets/images/auth/login_bg.jpg" alt="Auth Background"
            class="auth-bg light w-full h-full opacity-60 position-absolute top-0">
        <img src="assets/images/auth/auth_bg_dark.jpg" alt="Auth Background" class="auth-bg d-none dark">
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100 py-10">
                <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                    <div class="card mx-xxl-8">
                        <div class="card-body py-12 px-8">
                            <img src="{{ asset('assets/images/logo-lrtj.png') }}" alt="Logo Dark" height="50"
                                class="mb-4 mx-auto d-block">
                            <h6 class="mb-3 mb-8 fw-medium text-center">Get on SATSET: Solve Issues, Zero Hassle.</h6>

                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if (config('services.microsoft_sso.enabled'))
                                <a href="{{ route('auth.microsoft.redirect') }}"
                                    class="btn btn-light border w-full mb-5 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                    <span class="d-inline-grid" style="grid-template-columns: repeat(2, 10px); gap: 2px;">
                                        <span style="width: 10px; height: 10px; background: #f25022;"></span>
                                        <span style="width: 10px; height: 10px; background: #7fba00;"></span>
                                        <span style="width: 10px; height: 10px; background: #00a4ef;"></span>
                                        <span style="width: 10px; height: 10px; background: #ffb900;"></span>
                                    </span>
                                    Sign in with Microsoft
                                </a>

                                <div class="d-flex align-items-center gap-3 mb-5">
                                    <span class="border-top flex-grow-1"></span>
                                    <span class="text-muted fs-12">or use LDAP</span>
                                    <span class="border-top flex-grow-1"></span>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login.attempt') }}" class="authentication-form">
                                @csrf
                                <div class="row g-4">
                                    <div class="col-12">
                                        <label for="email" class="form-label">Email <span
                                                class="text-danger">*</span></label>
                                        <input type="email" name="email" id="email" 
                                            class="form-control @error('email') is-invalid @enderror" 
                                            required value="{{ old('email') }}" placeholder="Enter your email">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12">
                                        <label for="password" class="form-label">Password <span
                                                class="text-danger">*</span></label>
                                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" 
                                            id="password" placeholder="Enter your password" required>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="form-check">
                                                <input type="checkbox" name="remember" class="form-check-input" id="rememberMe">
                                                <label class="form-check-label" for="rememberMe">Remember me</label>
                                            </div>
                                            <div class="form-text">
                                                <a href="auth-create-password"
                                                    class="link link-primary text-muted text-decoration-underline">Forgot
                                                    password?</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-8">
                                        <button type="submit" class="btn btn-primary w-full mb-4">Sign In<i
                                                class="bi bi-box-arrow-in-right ms-1 fs-16"></i></button>
                                    </div>
                                </div>
                            </form>
                            <div class="text-center">
                            </div>
                        </div>
                    </div>
                    <p class="position-relative text-center fs-12 mb-0">© 2025 SatSet. Crafted with ❤️ by IT LRTJ Division</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
@endsection
