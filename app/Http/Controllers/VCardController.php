<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VCardController extends Controller
{
    // 1. Menampilkan Halaman Profil Publik (Saat QR Discan)
    public function show($id)
    {
        // Decrypt ID jika ingin lebih aman, atau pakai ID biasa
        $user = User::findOrFail($id);
        
        // Cek data tambahan (bisa disesuaikan dengan kolom di tabel users/employees)
        // Disini saya asumsikan tabel user/employee punya kolom ini, atau kita mock data
        $data = [
            'name' => $user->name,
            'position' => $user->position ?? 'Staff', // Contoh kolom
            'company' => 'PT LRT Jakarta',
            'email' => $user->email,
            'phone' => $user->phone ?? '+62 812-3456-7890', // Contoh kolom
            'address' => 'Gedung MCC, Jl. Raya Kelapa Nias, Pegangsaan Dua, Kelapa Gading, Jakarta Utara',
            'website' => 'https://lrtjakarta.co.id',
            'photo' => $user->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=dc2626&color=fff',
        ];

        return view('vcard.public-profile', compact('user', 'data'));
    }

    // 2. Download File .vcf (Add to Contact)
    public function download($id)
    {
        $user = User::findOrFail($id);
        
        // Data Mapping
        $name = $user->name;
        $firstName = explode(' ', $name)[0];
        $lastName = isset(explode(' ', $name)[1]) ? explode(' ', $name)[1] : '';
        $email = $user->email;
        $phone = $user->phone ?? '+6281234567890'; // Default jika null
        $org = 'PT LRT Jakarta';
        $title = $user->position ?? 'Employee';
        $address = 'Gedung MCC, Jl. Raya Kelapa Nias, Jakarta Utara';
        $url = 'https://lrtjakarta.co.id';

        // Format vCard 3.0 String
        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "FN:" . $name . "\r\n";
        $vcard .= "N:" . $lastName . ";" . $firstName . ";;;\r\n";
        $vcard .= "ORG:" . $org . "\r\n";
        $vcard .= "TITLE:" . $title . "\r\n";
        $vcard .= "EMAIL;TYPE=INTERNET;TYPE=WORK:" . $email . "\r\n";
        $vcard .= "TEL;TYPE=CELL:" . $phone . "\r\n";
        $vcard .= "ADR;TYPE=WORK:;;" . $address . ";Jakarta;;;\r\n";
        $vcard .= "URL:" . $url . "\r\n";
        $vcard .= "END:VCARD\r\n";

        // Return response download
        $filename = str_replace(' ', '_', $name) . '.vcf';
        
        return response($vcard)
            ->header('Content-Type', 'text/vcard')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
    
    // 3. Generate QR Code (Untuk ditampilkan di Admin Panel)
    public function generateQr($id)
    {
        $url = route('vcard.show', $id);
        return QrCode::size(200)->generate($url);
    }
}