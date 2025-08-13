<?php

namespace App\Http\Controllers;

use App\Models\Rdv;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * List current user's appointments
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $rdvs = Rdv::with('target:id,name')
            ->where('patient_id', $user->id)
            ->orderBy('date_time', 'desc')
            ->get();

        // Normalize for frontend
        $data = $rdvs->map(function ($a) {
            return [
                'id' => $a->id,
                'doctor_id' => $a->target_user_id,
                'doctor_name' => $a->target?->name ?? 'Médecin',
                'date' => $a->date_time?->format('Y-m-d'),
                'time' => $a->date_time?->format('H:i'),
                'status' => $a->status,
                'reason' => $a->reason,
            ];
        });

        return response()->json($data);
    }

    /**
     * Book a new appointment
     */
    public function book(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $patient = $request->user();
        $doctor = User::findOrFail($request->doctor_id);

        $dateTime = Carbon::parse($request->date . ' ' . $request->time);

        $rdv = Rdv::create([
            'patient_id' => $patient->id,
            'target_user_id' => $doctor->id,
            'target_role' => 'medecin',
            'date_time' => $dateTime,
            'status' => 'pending',
            'reason' => $request->reason,
            'notes' => null,
        ]);

        return response()->json([
            'message' => 'Rendez-vous créé avec succès',
            'appointment' => [
                'id' => $rdv->id,
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name,
                'date' => $dateTime->format('Y-m-d'),
                'time' => $dateTime->format('H:i'),
                'status' => $rdv->status,
                'reason' => $rdv->reason,
            ],
        ], 201);
    }

    /**
     * Cancel an appointment
     */
    public function cancel(Request $request, $id)
    {
        $rdv = Rdv::where('id', $id)
            ->where('patient_id', $request->user()->id)
            ->firstOrFail();

        $rdv->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Rendez-vous annulé', 'id' => $rdv->id]);
    }
}
