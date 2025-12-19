<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Model
use App\Models\General\ListOrder;
use App\Models\General\OrderItem;
use App\Models\General\Distributor;
use App\Models\Product\Product;
use App\Models\User;
use App\Models\Setting\Slack;
use App\Models\Setting\Role;

class DeliveryOrder extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');  // Get the selected status

        // Query to get the list of orders with the option to filter by search and status
        $listorder = ListOrder::with(['distributor', 'orderItems.product'])  // Eager load distributor and product relationships
            ->when($search, function ($query, $search) {
                return $query->whereHas('distributor', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                });
            })
            ->when($status, function ($query, $status) {
                return $query->where('status', $status);  // Filter by status if provided
            })
            ->paginate(10);
        $deliverySuccessCount = ListOrder::where('status', 'Delivered')->count(); 
        $onProcessCount = ListOrder::where('status', 'On Process')->count();  
        $delayedCount = ListOrder::where('status', 'Delayed')->count(); 
        $notDeliveredCount = ListOrder::where('status', 'Cancel')->count();
        // If the request is AJAX, return the partial view with updated data
        if ($request->ajax()) {
            return view('general.delivery.index', compact('listorder','deliverySuccessCount', 'onProcessCount', 'delayedCount', 'notDeliveredCount'))->render();
        }

        

        // For regular view, return the full page
        return view('general.delivery.index', compact('listorder', 'search', 'status','deliverySuccessCount', 'onProcessCount', 'delayedCount', 'notDeliveredCount'));
    }


    public function create()
    {
        $distributor = Distributor::all();
        $product = Product::all();
        $sales = User::join('roles', 'users.role_id', '=', 'roles.id')
             ->where('roles.name', 'sales')
             ->select('users.*')
             ->get();
        return view('general.delivery.create', compact('distributor','product','sales'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tanggal_order' => 'nullable|date',
            ]);

            $listOrder = new ListOrder();
            $listOrder->customer = $request->distributor;
            $listOrder->tanggal_terima_order = $request->tanggal_order;
            $listOrder->maks_kirim = Carbon::parse($request->tanggal_order)->addDays(7);
            $listOrder->ppn = $request->ppn;
            $listOrder->ekspedisi = $request->ekspedisi;
            $listOrder->status = $request->status;
            if ($request->hasFile('delivery_attachment')) {
                $attachment = $request->file("delivery_attachment");
                $path = $image->store('delivery_attachment', 'public');
                $listOrder->delivery_attachment = $path;
            }
            $listOrder->save();

            // Now create order items using the validated data
            foreach ($request->order_items as $item) {
                $orderItem = new OrderItem();
                $orderItem->list_order_id = $listOrder->id; // Associate with the ListOrder
                $orderItem->tanggal_kirim = $request->tanggal_kirim;
                $orderItem->sales = $request->sales;
                $orderItem->nama_produk = $item['nama_produk']; // Make sure the array structure matches
                $orderItem->total_order = $item['total_order'];
                $orderItem->jumlah_kirim = $item['jumlah_kirim'];
                $orderItem->sisa_belum_kirim = $item['sisa_belum_kirim'] ?? 0; // Default to 0 if null
                $orderItem->save();
            }

            $slackChannel = Slack::where('channel', 'Pengiriman')->first();

            if (!$slackChannel) {
                return redirect()->back()->with('error', 'Slack channel not found.');
            }

            $slackWebhookUrl = $slackChannel->url;
            $today = now()->toDateString();
            $attachments = [];

            foreach ($request->order_items as $item) {
                $productName = Product::where('id',$item['nama_produk'])->first();
                $attachments[] = [
                    'title' => 'Details Data Order',
                    'fields' => [
                        [
                            'title' => 'Product',
                            'value' => $productName->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Tanggal Kirim',
                            'value' => $request->tanggal_kirim,
                            'short' => true,
                        ],
                        [
                            'title' => 'Total Order',
                            'value' => $item['total_order'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Total Kirim',
                            'value' => $item['jumlah_kirim'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Sisa Kirim',
                            'value' => $item['sisa_belum_kirim'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Lihat Detail Data Di Champoil Portal',
                            'value' => '(https://dashboard.champoil.co.id/delivery-oder)',
                            'short' => true,
                        ]
                    ],
                ];
            }
            $distributorName = Distributor::where('id',$request->distributor)->select('name')->first();
            $data = [
                'text' => "Automatic Report Delivery Order {$distributorName->name} || Sales Name : {$request->sales} || Delivery Status : {$request->status}",
                'attachments' => $attachments,
            ];

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

            if ($result === false) {
                // Penanganan kesalahan jika Curl gagal
                $error = curl_error($ch);
                curl_close($ch);
                return redirect()->back()->with('error', 'Terjadi kesalahan saat mengirim data ke Slack: ' . $error);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                // Penanganan kesalahan jika Slack merespons selain status 200 OK
                curl_close($ch);
                return redirect()->back()->with('error', 'Terjadi kesalahan saat mengirim data ke Slack. Kode status: ' . $httpCode);
            }

            curl_close($ch);

            return redirect()->route('delivery-order.index')->with('success', 'Data information saved successfully!');
        } catch (Exception $e) {
            Log::error('Error storing menu: ' . $e->getMessage());
            return redirect()->route('delivery-order.index')->with('error', 'Data information error!' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $listOrder = ListOrder::with('orderItems')->findOrFail($id);
            return view('general.delivery.edit', compact('listOrder'));
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function edit($id)
    {
        try {
            $listOrder = ListOrder::with('orderItems')->findOrFail($id);
            $distributor = Distributor::all();
            $product = Product::all();
            $sales = User::join('roles', 'users.role_id', '=', 'roles.id')
             ->where('roles.name', 'sales')
             ->select('users.*')
             ->get();
            return view('general.delivery.edit', compact('listOrder','distributor','product','sales'));
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Validate the incoming request
            $validated = $request->validate([
                'customer' => 'nullable|string',
            ]);

            // Find the ListOrder by ID and update it
            $listOrder = ListOrder::findOrFail($id);
            $listOrder->customer = $request->distributor; // Use distributor instead of customer
            $listOrder->tanggal_terima_order = $request->tanggal_order;
            $listOrder->maks_kirim = Carbon::parse($request->tanggal_order)->addDays(7); // Set max shipping date
            $listOrder->ppn = $request->ppn;
            $listOrder->ekspedisi = $request->ekspedisi;
            $listOrder->status = $request->status;
            if ($request->hasFile('delivery_attachment')) {
                $attachment = $request->file("delivery_attachment");
                $path = $attachment->store('delivery_attachment', 'public');
                $listOrder->delivery_attachment = $path;
            }
            $listOrder->save();

            // Only update order items if there are changes in the order_items field
            if ($request->has('order_items') && !empty($request->order_items)) {
                foreach ($request->order_items as $item) {
                    $orderItem = $listOrder->orderItems()
                        ->where('nama_produk', $item['nama_produk'])
                        ->where('tanggal_kirim', $item['tanggal_kirim'])
                        ->first();
        
                    if ($orderItem) {
                        // Perbarui jika ada perubahan data
                        if (
                            $orderItem->total_order != $item['total_order'] ||
                            $orderItem->jumlah_kirim != $item['jumlah_kirim'] ||
                            $orderItem->sisa_belum_kirim != $item['sisa_belum_kirim'] ||
                            $orderItem->sales != $request->sales
                        ) {
                            $orderItem->update([
                                'total_order' => $item['total_order'],
                                'jumlah_kirim' => $item['jumlah_kirim'],
                                'sisa_belum_kirim' => $item['sisa_belum_kirim'],
                                'sales' => $request->sales,
                            ]);
                        } else {
                        }
                    } else {
                        // Buat item baru
                        $newItem = $listOrder->orderItems()->create([
                            'nama_produk' => $item['nama_produk'],
                            'total_order' => $item['total_order'],
                            'jumlah_kirim' => $item['jumlah_kirim'] ?? 0,
                            'sisa_belum_kirim' => $item['sisa_belum_kirim'] ?? 0,
                            'tanggal_kirim' => $item['tanggal_kirim'] ?? null,
                            'sales' => $request->sales,
                        ]);
                    }
                }
            }

            $slackChannel = Slack::where('channel', 'Pengiriman')->first();

            if (!$slackChannel) {
                return redirect()->back()->with('error', 'Slack channel not found.');
            }

            $slackWebhookUrl = $slackChannel->url;
            $today = now()->toDateString();
            $attachments = [];

            foreach ($request->order_items as $item) {
                $productName = Product::where('id',$item['nama_produk'])->first();

                $imageUrl = $listOrder->delivery_attachment ? asset('storage/' . $listOrder->delivery_attachment) : null;

                $attachments[] = [
                    'title' => 'Details Product Order',
                    
                    'fields' => [
                        [
                            'title' => 'Product',
                            'value' => $productName->name,
                            'short' => true,
                        ],
                        [
                            'title' => 'Tanggal Kirim',
                            'value' => $item['tanggal_kirim'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Total Order',
                            'value' => $item['total_order'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Total Kirim',
                            'value' => $item['jumlah_kirim'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Sisa Kirim',
                            'value' => $item['sisa_belum_kirim'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Foto Surat Jalan',
                            'value' => $imageUrl,
                            'short' => true,
                        ],
                        [
                            'title' => 'Lihat Detail Data Di Champoil Portal',
                            'value' => '(https://dashboard.champoil.co.id/delivery-oder)',
                            'short' => true,
                        ]
                    ],
                ];
            }
            $distributorName = Distributor::where('id',$request->distributor)->select('name')->first();
            $data = [
                'text' => "Automatic Report Delivery Order {$distributorName->name} || Sales Name : {$request->sales} || Delivery Status : {$request->status}",
                'attachments' => $attachments,
            ];

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

            if ($result === false) {
                // Penanganan kesalahan jika Curl gagal
                $error = curl_error($ch);
                curl_close($ch);
                return redirect()->back()->with('error', 'Terjadi kesalahan saat mengirim data ke Slack: ' . $error);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                // Penanganan kesalahan jika Slack merespons selain status 200 OK
                curl_close($ch);
                return redirect()->back()->with('error', 'Terjadi kesalahan saat mengirim data ke Slack. Kode status: ' . $httpCode);
            }

            curl_close($ch);

        
            
            DB::commit();
            // Return a success response
            return redirect()->route('delivery-order.index')->with('success', 'Data information updated successfully!');
        } catch (Exception $e) {
            \Log::error($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $listOrder = ListOrder::findOrFail($id);
            $listOrder->delete();

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
