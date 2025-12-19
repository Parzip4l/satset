<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Production\ProductionMaterial;
use App\Models\Setting\Slack;
use Carbon\Carbon;

class SendWeeklyProductionReport extends Command
{
    protected $signature = 'report:weekly-production';
    protected $description = 'Kirim laporan produksi mingguan ke Slack';

    public function handle()
    {
        Log::info("⏳ Memulai proses laporan produksi mingguan...");

        $slackChannel = Slack::where('channel', 'general')->first(); // Ganti ke channel aktual
        if (!$slackChannel) {
            Log::error('❌ Webhook Slack tidak ditemukan untuk channel "Production Monitoring".');
            return;
        }

        $slackWebhookUrl = $slackChannel->url;
        Log::info("✅ Webhook Slack ditemukan: {$slackWebhookUrl}");

        // Periode minggu lalu (Senin - Minggu)
        $endLastWeek = Carbon::yesterday()->startOfWeek()->subDay();
        $startLastWeek = $endLastWeek->copy()->startOfWeek();

        // Periode 2 minggu lalu
        $end2WeeksAgo = $startLastWeek->copy()->subDay();
        $start2WeeksAgo = $end2WeeksAgo->copy()->startOfWeek();

        // Kapasitas
        $kapasitasHarianKg = 23000;
        $kapasitasHarianTon = round($kapasitasHarianKg / 1000, 2);
        $kapasitasMingguanKg = $kapasitasHarianKg * 7;
        $kapasitasMingguanTon = round($kapasitasMingguanKg / 1000, 2);
        $batasAtasPersen = 75;
        $batasAtasKg = $kapasitasMingguanKg * ($batasAtasPersen / 100);
        $batasAtasTon = round($batasAtasKg / 1000, 2);

        // Data produksi
        $totalLastWeek = ProductionMaterial::whereHas('batch', function ($q) use ($startLastWeek, $endLastWeek) {
            $q->whereBetween('tanggal', [$startLastWeek, $endLastWeek]);
        })->sum('qty');

        $total2WeeksAgo = ProductionMaterial::whereHas('batch', function ($q) use ($start2WeeksAgo, $end2WeeksAgo) {
            $q->whereBetween('tanggal', [$start2WeeksAgo, $end2WeeksAgo]);
        })->sum('qty');

        $totalTon = round($totalLastWeek / 1000, 2);
        $avgPerDayKg = round($totalLastWeek / 7);
        $avgPerDayTon = round($avgPerDayKg / 1000, 2);
        $persenKapasitas = round(($totalLastWeek / $kapasitasMingguanKg) * 100, 2);
        $sisaKapasitas = $kapasitasMingguanKg - $totalLastWeek;
        $sisaTon = round($sisaKapasitas / 1000, 2);

        $tren = $total2WeeksAgo > 0
            ? round((($totalLastWeek - $total2WeeksAgo) / $total2WeeksAgo) * 100, 2)
            : ($totalLastWeek > 0 ? 100 : 0);

        $statusKapasitas = $persenKapasitas >= $batasAtasPersen ? 'melebihi' : 'kurang dari';
        $trenTeks = $tren >= 0 ? 'Naik' : 'Turun';
        $periodeTeks = $startLastWeek->translatedFormat('j') . '–' . $endLastWeek->translatedFormat('j F Y');

        // Produksi harian (yang tersedia)
        $produksiHarian = ProductionMaterial::selectRaw('DATE(production_batches.tanggal) as tgl, SUM(qty) as total_qty')
            ->join('production_batches', 'production_materials.production_batch_id', '=', 'production_batches.id')
            ->whereBetween('production_batches.tanggal', [$startLastWeek, $endLastWeek])
            ->groupBy('tgl')
            ->orderBy('tgl', 'asc')
            ->get();

        $dailyReport = '';
        $hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        foreach ($produksiHarian as $data) {
            $tanggal = Carbon::parse($data->tgl);
            $hari = $hariIndo[$tanggal->dayOfWeek];
            $qtyKg = $data->total_qty;
            $qtyTon = round($qtyKg / 1000, 2);
            $dailyReport .= "- {$hari} ({$tanggal->format('d M')}): " . number_format($qtyKg) . " kg ({$qtyTon} ton)\n";
        }

        // Format Slack Message
        $message  = "📦 *Production Weekly Update ({$periodeTeks})*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "\n";

        $message .= "🔵 *Total produksi:* " . number_format($totalLastWeek, 2) . " kg (" . number_format($totalTon, 2) . " ton)\n";
        $message .= "\n";
        $message .= "✅ *Rata-rata harian:* " . number_format($avgPerDayKg) . " kg (" . number_format($avgPerDayTon, 2) . " ton)\n";
        $message .= "\n";
        $message .= "📊 *Persentase kapasitas:* {$persenKapasitas}%\n";
        $message .= "\n";
        $message .= "🟠 *Batas atas:* {$batasAtasPersen}% (" . number_format($batasAtasKg) . " kg / {$batasAtasTon} ton)\n";
        $message .= "\n";
        $message .= "🧮 *Sisa kapasitas minggu ini:* " . number_format($sisaKapasitas) . " kg ({$sisaTon} ton)\n";
        $message .= "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "\n";

        $message .= "🏭 *Kapasitas maksimum harian:* " . number_format($kapasitasHarianKg) . " kg ({$kapasitasHarianTon} ton)\n";
        $message .= "\n";
        $message .= "📅 *Kapasitas maksimum mingguan:* " . number_format($kapasitasMingguanKg) . " kg ({$kapasitasMingguanTon} ton)\n";
        $message .= "\n";
        $message .= "📈 *Tren:* {$trenTeks} {$tren}%\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "\n";

        $message .= "🗓️ *Produksi Harian (yang tercatat):*\n";
        $message .= $dailyReport;
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "\n";

        $message .= "📝 *Note:* Produksi {$statusKapasitas} batas atas.\n";
        $message .= "\n";
        


        // Kirim ke Slack
        $data = ['text' => $message];
        $data_string = json_encode($data);

        $ch = curl_init($slackWebhookUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false) {
            $error = curl_error($ch);
            Log::error("❌ Gagal mengirim laporan ke Slack. Error: {$error}");
        } elseif ($httpCode !== 200) {
            Log::error("❌ Slack mengembalikan status {$httpCode}. Response: {$result}");
        } else {
            Log::info("✅ Laporan berhasil dikirim ke Slack.");
        }

        curl_close($ch);
        Log::info("✅ Proses laporan produksi mingguan selesai.");
    }
}
