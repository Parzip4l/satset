<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
// Import Model
use App\Models\Master\ProblemCategory; 
use App\Models\Master\TicketCategory;
use App\Models\Master\Status;
use App\Models\Master\Priority;
use App\Models\Master\Impact;
use App\Models\Master\Urgency;

class MasterApiController extends Controller
{
    /**
     * Get List of Problem Categories
     */
    public function getProblemCategories(Request $request): JsonResponse
    {
        // Cek user yang sedang login (via Sanctum/Passport)
        // $user = $request->user();
        
        // Jika butuh validasi spesifik user
        // if (!$user) {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        // -----------------------------------------------------------
        // Logic Data
        // -----------------------------------------------------------

        $categories = ProblemCategory::all();

        // Return response JSON standar
        return response()->json([
            'success' => true,
            'message' => 'List Problem Categories',
            'data'    => $categories
        ], 200);
    }

    public function getTicketCategories(Request $request): JsonResponse
    {
        // -----------------------------------------------------------
        // TODO: Uncomment auth check nanti
        // -----------------------------------------------------------
        // $user = $request->user();
        
        // Ambil data TicketCategory
        $tickets = TicketCategory::all();

        return response()->json([
            'success' => true,
            'message' => 'List Ticket Categories',
            'data'    => $tickets
        ], 200);
    }

    public function getTicketStatus(Request $request): JsonResponse
    {
        // -----------------------------------------------------------
        // TODO: Uncomment auth check nanti
        // -----------------------------------------------------------
        // $user = $request->user();

        // Ambil data Status
        $status = Status::all();

        return response()->json([
            'success' => true,
            'message' => 'List Ticket Status',
            'data'    => $status
        ], 200);
    }

    public function getTicketPriorities(Request $request): JsonResponse
    {
        // -----------------------------------------------------------  
        $priorities = Priority::all();

        return response()->json([
            'success' => true,
            'message' => 'List Ticket Priorities',
            'data'    => $priorities
        ], 200);
    }

    Public function getImpacts(Request $request): JsonResponse
    {
        // -----------------------------------------------------------  
        $impacts = Impact::all();

        return response()->json([
            'success' => true,
            'message' => 'List Impacts',
            'data'    => $impacts
        ], 200);
    }

    public function getUrgencies(Request $request): JsonResponse
    {
        // -----------------------------------------------------------  
        $urgencies = Urgency::all();

        return response()->json([
            'success' => true,
            'message' => 'List Urgencies',
            'data'    => $urgencies
        ], 200);
    }

}