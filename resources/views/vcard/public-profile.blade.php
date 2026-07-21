<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['name'] }} - Digital Card</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --lrt-orange: #F6821F;
            --lrt-red: #D3242B;
            --lrt-gradient: linear-gradient(135deg, var(--lrt-orange) 0%, var(--lrt-red) 100%);
            --lrt-bg: #f8fafc;
        }

        body { 
            background-color: var(--lrt-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #334155;
        }

        .profile-card {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }

        .header-bg {
            background: var(--lrt-gradient);
            height: 180px;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            position: relative;
        }

        .avatar-container {
            width: 130px;
            height: 130px;
            background: white;
            border-radius: 50%;
            padding: 5px;
            position: absolute;
            bottom: -65px;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--lrt-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .content-area { padding-top: 80px; padding-bottom: 40px; }

        .action-btn {
            background: var(--lrt-gradient);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(211, 36, 43, 0.3); 
            transition: all 0.3s ease;
        }
        
        .action-btn:active { transform: scale(0.95); }
        .action-btn:hover { 
            background: linear-gradient(135deg, var(--lrt-red) 0%, var(--lrt-orange) 100%);
            box-shadow: 0 6px 20px rgba(211, 36, 43, 0.4);
        }

        .info-item {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            /* Perubahan disini: Align items start agar icon tetap di atas jika teks panjang */
            align-items: center; 
            border: 1px solid #edf2f7;
            transition: transform 0.2s;
            text-decoration: none !important;
        }
        
        .info-item:hover {
            transform: translateY(-2px);
            border-color: #ffd8a8;
            background-color: #fffbf7;
        }

        .icon-box {
            width: 44px;
            height: 44px;
            background: rgba(246, 130, 31, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 1.3rem;
            
            /* PENTING: Mencegah icon gepeng/mengecil */
            flex-shrink: 0; 
        }

        .icon-box i {
            background: var(--lrt-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Khusus untuk alamat yang panjang */
        .address-text {
            line-height: 1.4;
            flex-grow: 1; /* Mengisi sisa ruang */
        }

        .text-gradient {
            background: var(--lrt-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .label-text {
            font-size: 10px;
            letter-spacing: 0.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $profileInitials = collect(explode(' ', trim($data['name'] ?? 'Guest')))
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('') ?: 'G';
    @endphp

    <div class="profile-card">
        {{-- Header Image --}}
        <div class="header-bg text-center pt-4">
            <img src="{{ asset('assets/images/logo-lrtj.png') }}" alt="Logo" style="height: 40px; opacity: 0.95;">
            <div class="avatar-container">
                <div class="avatar-img">{{ $profileInitials }}</div>
            </div>
        </div>

        <div class="container content-area px-4">
            {{-- Nama & Jabatan --}}
            <div class="text-center mb-4">
                <h3 class="fw-bold text-dark mb-1">{{ $data['name'] }}</h3>
                <p class="text-gradient mb-0" style="font-size: 1.1rem;">{{ $data['position'] }}</p>
                <small class="text-muted fw-medium">{{ $data['company'] }}</small>
            </div>

            {{-- Tombol Save Contact --}}
            <div class="text-center mb-4">
                <a href="{{ route('vcard.download', $user->id) }}" class="btn btn-primary action-btn text-white w-100">
                    <i class="bi bi-person-plus-fill me-2"></i> Save to Contacts
                </a>
            </div>

            {{-- Detail Informasi --}}
            <div class="info-list">
                
                {{-- Phone --}}
                <a href="tel:{{ $data['phone'] }}" class="info-item text-dark">
                    <div class="icon-box"><i class="bi bi-telephone-fill"></i></div>
                    <div>
                        <small class="d-block label-text text-uppercase fw-bold">Mobile Phone</small>
                        <span class="fw-semibold">{{ $data['phone'] }}</span>
                    </div>
                </a>

                {{-- Email --}}
                <a href="mailto:{{ $data['email'] }}" class="info-item text-dark">
                    <div class="icon-box"><i class="bi bi-envelope-fill"></i></div>
                    <div>
                        <small class="d-block label-text text-uppercase fw-bold">Email Address</small>
                        <span class="fw-semibold text-break">{{ $data['email'] }}</span>
                    </div>
                </a>

                {{-- Website --}}
                <a href="{{ $data['website'] }}" target="_blank" class="info-item text-dark">
                    <div class="icon-box"><i class="bi bi-globe"></i></div>
                    <div>
                        <small class="d-block label-text text-uppercase fw-bold">Website</small>
                        <span class="fw-semibold">www.lrtjakarta.co.id</span>
                    </div>
                </a>

                {{-- Office / Maps (FIXED) --}}
                {{-- Menggunakan Link ke Google Maps --}}
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($data['address']) }}" target="_blank" class="info-item text-dark">
                    <div class="icon-box"><i class="bi bi-geo-alt-fill"></i></div>
                    <div class="address-text">
                        <small class="d-block label-text text-uppercase fw-bold">Office Location</small>
                        <span class="fw-semibold small d-block pt-1">{{ $data['address'] }}</span>
                    </div>
                    {{-- Chevron Icon untuk indikasi bisa diklik --}}
                    <div class="ms-2 text-muted opacity-25">
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>

            </div>
            
            <div class="text-center mt-5 mb-3 text-muted" style="font-size: 11px; font-weight: 500;">
                &copy; {{ date('Y') }} SatSet System LRT Jakarta
            </div>
        </div>
    </div>

</body>
</html>
