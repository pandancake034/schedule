<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Availability;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    // ==========================================
    // 1. PAGINA WEERGAVES (VIEWS)
    // ==========================================

    public function index() {
        $users = User::with('schedules')->get();

        $users->map(function($user) {
            $totalHours = 0;
            // Schatting gemiddelde duur per shift (contract uren / dagen)
            $avgShiftDuration = ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;

            foreach($user->schedules as $schedule) {
                // Voor de statistieken tellen we het gemiddelde per shift
                $totalHours += $avgShiftDuration;
            }
            
            $user->planned_hours_total = round($totalHours, 1);
            return $user;
        });

        return view('dashboard', ['stats' => $users]);
    }

    public function team() {
        $users = User::all();
        return view('team', compact('users'));
    }

    public function admin() {
        return view('admin');
    }

    // ==========================================
    // 2. API (VOOR DE KALENDER)
    // ==========================================

    public function getEvents() {
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->select('schedules.date', 'schedules.shift_type', 'users.name', 'users.contract_hours', 'users.contract_days')
            ->get();

        $events = $schedules->map(function($row) {
            // Kleuren
            if ($row->shift_type == 'AM') $color = '#0070d2'; // Blauw
            elseif ($row->shift_type == 'PM') $color = '#04844b'; // Groen
            else $color = '#d68100'; // Oranje (Dagdienst)

            // Tijden berekenen
            $times = $this->calculateShiftTimes($row->date, $row->shift_type, $row->contract_hours, $row->contract_days);

            $typeLabel = match($row->shift_type) {
                'AM' => '(Ochtend)',
                'PM' => '(Middag)',
                'DAY' => '(Dag)',
                default => ''
            };

            return [
                'title' => $row->name . ' ' . $typeLabel,
                'start' => $times['start'],
                'end'   => $times['end'],
                'color' => $color
            ];
        });

        return response()->json($events);
    }

    /**
     * Helper om start- en eindtijden te berekenen voor weergave.
     */
    private function calculateShiftTimes($dateStr, $shiftType, $contractHours, $contractDays) {
        $date = Carbon::parse($dateStr);
        $avgDuration = ($contractDays > 0) ? ($contractHours / $contractDays) : 8;

        $startTime = '00:00';

        if ($shiftType === 'DAY') {
            // Donderdag/Vrijdag dagdienst begint om 09:00
            $startTime = '09:00';
        } elseif ($shiftType === 'PM') {
            // Middagdienst begint om 14:00
            $startTime = '14:00';
        } else {
            // AM Shift
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                // Zondag begint om 06:00
                $startTime = '06:00';
            } else {
                // Andere dagen 05:00
                $startTime = '05:00';
            }
        }

        $start = Carbon::parse("$dateStr $startTime:00");
        $end = $start->copy()->addMinutes($avgDuration * 60);

        return [
            'start' => $start->toIso8601String(),
            'end'   => $end->toIso8601String()
        ];
    }

    // ==========================================
    // 3. ACTIES (OPSLAAN & GENEREREN)
    // ==========================================

    public function storeUser(Request $request) {
        $request->validate([
            'name' => 'required', 
            'email' => 'required|email|unique:users,email',
            'contract_days' => 'required|integer|min:1|max:7',
            'contract_hours' => 'required|integer|min:1',
            'shift_preference' => 'required',
            'fixed_days' => 'array'
        ]);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,
            'contract_hours' => $request->contract_hours,
            'fixed_days' => $request->fixed_days,
            'password' => bcrypt('welkom123')
        ]);

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach($days as $day) {
            DB::table('availabilities')->insert([
                'user_id' => $user->id,
                'day_of_week' => $day,
                'shift_preference' => $request->shift_preference,
                'created_at' => now(), 'updated_at' => now()
            ]);
        }

        return redirect('/nieuwegein/team')->with('success', 'Collega ' . $user->name . ' toegevoegd!');
    }

    // ==========================================
    // 4. NIEUWE GENERATE LOGICA
    // ==========================================

    public function generateSchedule(Request $request)
    {
        // 1. Weekrange bepalen (Start op Zaterdag)
        $startDate = Carbon::parse($request->start_date);
        
        if ($startDate->dayOfWeek !== Carbon::SATURDAY) {
            $startDate->previous(Carbon::SATURDAY); 
        }

        $weekStart = $startDate->copy();
        $weekEnd   = $startDate->copy()->addDays(6);

        // 2. Loop door de week
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayName = $currentDate->format('l'); // Bijv. 'Thursday'

            // --- Logica Splitsing ---
            
            if ($dayName === 'Thursday' || $dayName === 'Friday') {
                // REGEL: Donderdag & Vrijdag
                // - Alleen 'DAY' shift.
                // - Alleen VASTE medewerkers (geen opvulling tot 2).
                
                $this->scheduleFixedUsers($currentDate, ['DAY'], $weekStart, $weekEnd);
                
            } else {
                // REGEL: Andere dagen (Zaterdag t/m Woensdag)
                // - AM & PM shifts.
                // - Wel opvullen tot 2 personen.
                
                $shiftsToCheck = ['AM', 'PM'];
                
                // Stap A: Eerst vaste mensen
                $this->scheduleFixedUsers($currentDate, $shiftsToCheck, $weekStart, $weekEnd);

                // Stap B: Gaten opvullen
                foreach ($shiftsToCheck as $shiftType) {
                    $this->fillShiftGaps($currentDate, $shiftType, $weekStart, $weekEnd);
                }
            }
        }

        return redirect('/nieuwegein/schedule')->with('success', 'Rooster gegenereerd!');
    }

    /**
     * Stap A: Plan mensen in die deze dag als VASTE dag hebben.
     */
    private function scheduleFixedUsers($date, $availableShifts, $weekStart, $weekEnd)
    {
        $dayName = $date->format('l');

        // Haal gebruikers op met deze vaste dag
        $fixedUsers = User::whereJsonContains('fixed_days', $dayName)->get();

        foreach ($fixedUsers as $user) {
            // Check dubbele shift
            if ($this->userHasShiftToday($user->id, $date)) {
                continue;
            }

            // Bepaal shift type
            $targetShift = null;

            if (in_array('DAY', $availableShifts)) {
                $targetShift = 'DAY';
            } else {
                // AM/PM bepalen obv voorkeur
                $pref = Availability::where('user_id', $user->id)
                    ->where('day_of_week', $dayName)
                    ->value('shift_preference') ?? 'BOTH';

                if ($pref === 'AM') $targetShift = 'AM';
                elseif ($pref === 'PM') $targetShift = 'PM';
                else {
                    // Bij BOTH: Check rusttijd (gisteren PM -> vandaag PM)
                    if ($this->workedLateYesterday($user->id, $date)) {
                        $targetShift = 'PM';
                    } else {
                        $targetShift = 'AM';
                    }
                }
            }

            // Plan in (isFixed = true -> forceert plaatsing tenzij rusttijd geschonden wordt)
            if ($this->canWorkShift($user, $date, $targetShift, $weekStart, $weekEnd, true)) {
                $this->assignShift($date, $targetShift, $user->id);
            }
        }
    }

    /**
     * Stap B: Vul de gaten op tot we 2 man per shift hebben.
     */
    private function fillShiftGaps($date, $shiftType, $weekStart, $weekEnd)
    {
        $targetPerShift = 2; 

        $currentCount = DB::table('schedules')
            ->where('date', $date->format('Y-m-d'))
            ->where('shift_type', $shiftType)
            ->count();

        $needed = $targetPerShift - $currentCount;

        if ($needed <= 0) return;

        $dayName = $date->format('l');
        
        $candidates = User::whereHas('availability', function($q) use ($dayName, $shiftType) {
            $q->where('day_of_week', $dayName);
            if ($shiftType !== 'DAY') {
                $q->whereIn('shift_preference', [$shiftType, 'BOTH']);
            }
        })
        ->inRandomOrder()
        ->get();

        foreach ($candidates as $candidate) {
            if ($needed <= 0) break;

            if ($this->canWorkShift($candidate, $date, $shiftType, $weekStart, $weekEnd)) {
                $this->assignShift($date, $shiftType, $candidate->id);
                $needed--;
            }
        }
    }

    /**
     * De centrale check-functie voor regels (rusttijd, contracturen, max dagen).
     */
    private function canWorkShift($user, $date, $shiftType, $weekStart, $weekEnd, $isFixed = false)
    {
        // 1. Dubbele shift check
        if ($this->userHasShiftToday($user->id, $date)) {
            return false; 
        }

        // 2. Rusttijd check: Gisteren PM -> Vandaag AM mag NIET.
        if ($shiftType === 'AM' && $this->workedLateYesterday($user->id, $date)) {
            return false;
        }

        // 3. Contract Checks
        $shiftsThisWeek = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->get();

        $daysWorkedCount = $shiftsThisWeek->count();
        
        $hoursWorked = 0;
        $avgDuration = ($user->contract_days > 0) ? ($user->contract_hours / $user->contract_days) : 0;
        $hoursWorked = $daysWorkedCount * $avgDuration;

        $newTotalHours = $hoursWorked + $avgDuration;

        // Bij vaste dagen zijn we soepel met contractlimieten, anders blokkeren we.
        if (!$isFixed) {
            if ($daysWorkedCount >= $user->contract_days) {
                return false;
            }
            if ($newTotalHours > $user->contract_hours) {
                return false;
            }
        }

        return true;
    }

    // ==========================================
    // HELPER FUNCTIES (INTERN)
    // ==========================================

    private function userHasShiftToday($userId, $date) {
        return DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $date->format('Y-m-d'))
            ->exists();
    }

    private function workedLateYesterday($userId, $today) {
        $yesterday = $today->copy()->subDay()->format('Y-m-d');
        return DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $yesterday)
            ->where('shift_type', 'PM')
            ->exists();
    }

    private function assignShift($date, $shift, $userId) {
        $exists = DB::table('schedules')
            ->where('user_id', $userId)
            ->where('date', $date->format('Y-m-d'))
            ->exists();

        if (!$exists) {
            DB::table('schedules')->insert([
                'date' => $date->format('Y-m-d'),
                'shift_type' => $shift,
                'user_id' => $userId,
                'created_at' => now(), 'updated_at' => now()
            ]);
        }
    }

    public function clearSchedule() {
        DB::table('schedules')->truncate();
        return redirect('/nieuwegein/admin')->with('success', 'Het volledige rooster is gewist!');
    }

    // ==========================================
    // 5. OVERIGE ACTIES
    // ==========================================

    public function editUser($id) {
        $user = User::findOrFail($id);
        $currentAvailability = $user->availability()->first();
        $currentPreference = $currentAvailability ? $currentAvailability->shift_preference : 'BOTH';
        return view('team_edit', compact('user', 'currentPreference'));
    }

    public function updateUser(Request $request, $id) {
        $user = User::findOrFail($id);
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'contract_days' => 'required|integer|min:1|max:7',
            'contract_hours' => 'required|integer|min:1',
            'shift_preference' => 'required',
            'fixed_days' => 'array'
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,
            'contract_hours' => $request->contract_hours,
            'fixed_days' => $request->fixed_days,
        ]);

        Availability::where('user_id', $user->id)->update(['shift_preference' => $request->shift_preference]);
        return redirect('/nieuwegein/team')->with('success', 'Gegevens bijgewerkt!');
    }

    public function deleteUser($id) {
        $user = User::findOrFail($id);
        $user->delete();
        return redirect('/nieuwegein/team')->with('success', 'Collega verwijderd.');
    }

    public function getDayDetails($date) {
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->where('date', $date)
            ->select('users.name', 'schedules.shift_type', 'users.contract_hours', 'users.contract_days')
            ->orderBy('users.name')
            ->get();

        $grouped = [
            'AM' => [],
            'PM' => [],
            'DAY' => []
        ];

        foreach($schedules as $row) {
            $times = $this->calculateShiftTimes($date, $row->shift_type, $row->contract_hours, $row->contract_days);
            
            $startStr = Carbon::parse($times['start'])->format('H:i');
            $endStr   = Carbon::parse($times['end'])->format('H:i');
            $timeStr  = "$startStr - $endStr";

            $grouped[$row->shift_type][] = [
                'name' => $row->name,
                'time' => $timeStr
            ];
        }

        return response()->json($grouped);
    }
}