<?php
// Create a new controller for efficient dashboard data



namespace App\Http\Controllers;

use App\Models\Rdv;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get patient dashboard statistics
     */
    public function getPatientStats(Request $request)
    {
        $user = auth()->user();
        
        // Get count of upcoming appointments
        $upcomingAppointments = Rdv::where('patient_id', $user->id)
            ->where('date_rdv', '>=', now()->format('Y-m-d'))
            ->count();
            
        // Get count of completed appointments
        $completedAppointments = Rdv::where('patient_id', $user->id)
            ->where('date_rdv', '<', now()->format('Y-m-d'))
            ->count();
            
        // Get count of notifications
        $notifications = 0; // Replace with actual notification count when implemented
            
        return response()->json([
            'upcomingAppointments' => $upcomingAppointments,
            'completedAppointments' => $completedAppointments,
            'notifications' => $notifications
        ]);
    }

    /**
     * Get statistics for doctor dashboard
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function doctorStats(Request $request)
    {
        $userId = Auth::id();
        $today = now()->format('Y-m-d');
        
        // Get today's appointments
        $appointmentsToday = Rdv::where('professional_id', $userId)
            ->whereDate('date', $today)
            ->count();
        
        // Count unique patients
        $totalPatients = Rdv::where('professional_id', $userId)
            ->distinct('patient_id')
            ->count('patient_id');
        
        // Count total appointments
        $totalAppointments = Rdv::where('professional_id', $userId)
            ->count();
        
        // Calculate revenue (assuming each appointment has a price field)
        // Adjust this calculation based on your actual data model
        $revenue = Rdv::where('professional_id', $userId)
            ->where('status', 'completed')
            ->sum('price');
        
        return response()->json([
            'appointmentsToday' => $appointmentsToday,
            'totalPatients' => $totalPatients,
            'totalAppointments' => $totalAppointments,
            'revenue' => $revenue,
        ]);
    }

    /**
     * Get doctor appointments
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function doctorAppointments(Request $request)
    {
        $userId = Auth::id();
        
        $appointments = Rdv::with('patient.user')
            ->where('professional_id', $userId)
            ->orderBy('date', 'desc')
            ->orderBy('time_start', 'desc')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'date' => $appointment->date,
                    'time_start' => $appointment->time_start,
                    'time_end' => $appointment->time_end,
                    'status' => $appointment->status,
                    'patient_name' => $appointment->patient->user->name ?? 'Patient',
                    'patient_id' => $appointment->patient_id,
                    'notes' => $appointment->notes,
                    'price' => $appointment->price,
                ];
            });
        
        return response()->json($appointments);
    }
}
